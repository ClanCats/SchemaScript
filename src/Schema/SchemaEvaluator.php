<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\TypeAliasNode;
use ClanCats\SchemaScript\Node\NamespaceNode;

class SchemaEvaluator
{
    use EvaluatorErrorTrait;

    private ValueResolver $valueResolver;

    private TypeEvaluator $typeEvaluator;

    public function __construct()
    {
        $this->valueResolver = new ValueResolver();
        $this->typeEvaluator = new TypeEvaluator($this->valueResolver);
    }

    /**
     * @param array<string, string> $sourceCodeMap
     */
    public function evaluate(ScopeNode $scope, array $sourceCodeMap = []): Definition
    {
        $context = new EvaluationContext();

        foreach ($sourceCodeMap as $key => $code) {
            $context->setSourceCode($key, $code);
        }

        $rootScope = $this->discoverNames($scope, null, '', $context);

        $this->evaluateNamespaces($scope->getNamespaces(), $context);

        $this->processScopeConstants($scope, $rootScope, $context);

        $metadata = $this->valueResolver->evaluateMetadata($scope->getMetadata(), $context);

        $context->setTypeAliases($this->evaluateTypeAliases($scope->getTypeAliases(), $rootScope, $context));

        foreach ($context->getImplicitTypeAliases() as $name => $targetType) {
            $resolvedType = $rootScope->isModelName($targetType)
                ? Type::reference($targetType)
                : Type::alias($targetType);
            $context->registerTypeAlias($name, new TypeAlias($name, false, $resolvedType));
        }

        $collisions = array_intersect_key($rootScope->getLocalModelNames(), $rootScope->getLocalTypeAliases());
        if (!empty($collisions)) {
            $collidingName = array_key_first($collisions);
            $collidingAlias = null;
            foreach ($scope->getTypeAliases() as $a) {
                if ($a->getName() === $collidingName) {
                    $collidingAlias = $a;
                    break;
                }
            }
            $this->throwEvaluatorError(
                sprintf('Type alias name collides with model name: "%s"', implode('", "', array_keys($collisions))),
                $collidingAlias,
                $context
            );
        }

        foreach ($scope->getModels() as $model) {
            $this->evaluateModel($model, $rootScope, '', $context);
        }

        return new Definition($metadata, $context->getTypeAliases(), $context->getNamespaces(), $context->getStructs());
    }

    private function discoverNames(ScopeNode $scope, ?TypeScope $parentScope, string $namePrefix, EvaluationContext $context): TypeScope
    {
        $typeScope = new TypeScope($parentScope);

        foreach ($scope->getTypeAliases() as $alias) {
            $typeScope->registerType($alias->getName(), $alias->getTypeDefinition() !== null);
        }

        $seenModels = [];
        foreach ($scope->getModels() as $model) {
            $name = $model->getName();
            if (isset($seenModels[$name])) {
                $this->throwEvaluatorError(sprintf('Duplicate model definition: "%s"', $name), $model, $context);
            }
            $seenModels[$name] = true;

            $fullName = $namePrefix !== '' ? $namePrefix . '/' . $name : $name;
            $typeScope->registerModelName($fullName);

            if (!empty($model->getTypeAliases()) || !empty($model->getChildModels())) {
                $this->discoverModelNames($model, $typeScope, $fullName);
            }
        }

        return $typeScope;
    }

    private function discoverModelNames(ModelDefinitionNode $model, TypeScope $parentScope, string $namePrefix): TypeScope
    {
        $childScope = new TypeScope($parentScope);

        foreach ($model->getTypeAliases() as $alias) {
            $childScope->registerType($alias->getName(), $alias->getTypeDefinition() !== null);
        }

        foreach ($model->getChildModels() as $child) {
            $fullName = $namePrefix . '/' . $child->getName();
            $childScope->registerModelName($fullName);

            if (!empty($child->getTypeAliases()) || !empty($child->getChildModels())) {
                $this->discoverModelNames($child, $childScope, $fullName);
            }
        }

        return $childScope;
    }

    /**
     * @param array<TypeAliasNode> $aliasNodes
     * @return array<string, TypeAlias>
     */
    private function evaluateTypeAliases(array $aliasNodes, TypeScope $scope, EvaluationContext $context): array
    {
        $result = [];
        foreach ($aliasNodes as $alias) {
            $name = $alias->getName();
            if (isset($result[$name])) {
                $this->throwEvaluatorError(sprintf('Duplicate type alias definition: "%s"', $name), $alias, $context);
            }

            $resolvedType = null;
            $typeDef = $alias->getTypeDefinition();
            if ($typeDef !== null) {
                $resolvedType = $this->typeEvaluator->evaluateType($typeDef, $name, $scope, $context);
            }

            $result[$name] = new TypeAlias(
                $name,
                $alias->isPublic(),
                $resolvedType,
                $this->valueResolver->evaluateAnnotations($alias->getAnnotations(), $context)
            );
        }

        $cycle = $this->detectTypeAliasCycle($result);
        if ($cycle !== null) {
            $cycleNode = null;
            foreach ($aliasNodes as $a) {
                if ($a->getName() === $cycle[0]) {
                    $cycleNode = $a;
                    break;
                }
            }
            $this->throwEvaluatorError(
                sprintf('Cyclic type alias detected: %s', implode(' → ', $cycle)),
                $cycleNode,
                $context
            );
        }

        return $result;
    }

    /**
     * @param array<NamespaceNode> $namespaceNodes
     */
    private function evaluateNamespaces(array $namespaceNodes, EvaluationContext $context): void
    {
        foreach ($namespaceNodes as $ns) {
            $this->evaluateNamespaceRecursive($ns, '', $context);
        }
    }

    private function evaluateNamespaceRecursive(NamespaceNode $ns, string $prefix, EvaluationContext $context): void
    {
        $fullName = $prefix !== '' ? $prefix . '::' . $ns->getName() : $ns->getName();

        if (!empty($ns->getConstants())) {
            if ($context->hasNamespace($fullName)) {
                $this->throwEvaluatorError(sprintf('Duplicate namespace definition: "%s"', $fullName), $ns, $context);
            }
            $constants = [];
            foreach ($ns->getConstants() as $constant) {
                $constName = $constant->getName();
                if (isset($constants[$constName])) {
                    $this->throwEvaluatorError(sprintf('Duplicate constant "%s" in namespace "%s"', $constName, $fullName), $constant, $context);
                }
                $constValue = $constant->getValue();
                $hasExplicitValue = $constant->hasValue() && $constValue !== null;
                $resolvedValue = $hasExplicitValue
                    ? $this->valueResolver->resolveValue($constValue, $context)
                    : $fullName . '::' . $constName;
                $constants[$constName] = new NamespaceConstant($constName, $resolvedValue, $hasExplicitValue);
            }
            $context->registerNamespace($fullName, new NamespaceDefinition($fullName, $constants));
        }

        foreach ($ns->getChildren() as $child) {
            $this->evaluateNamespaceRecursive($child, $fullName, $context);
        }
    }

    private function processScopeConstants(ScopeNode $scope, TypeScope $rootScope, EvaluationContext $context): void
    {
        foreach ($scope->getConstants() as $constant) {
            $name = $constant->getName();
            $value = $constant->getValue();

            if ($value instanceof ValueNode && $value->getType() === ValueNode::TYPE_IDENTIFIER) {
                $identifier = (string) $value->getValue();

                if ($rootScope->resolveType($identifier) !== null || $rootScope->isModelName($identifier)) {
                    $rootScope->registerType($name, true);
                    $context->registerImplicitTypeAlias($name, $identifier);
                }

                $context->setIdentifierConstant($name, $identifier);
            } elseif ($value !== null) {
                $context->setValueConstant($name, $constant);
            }
        }
    }

    private function evaluateModel(ModelDefinitionNode $model, TypeScope $parentScope, string $namePrefix, EvaluationContext $context): void
    {
        $name = $namePrefix !== '' ? $namePrefix . '/' . $model->getName() : $model->getName();

        $scope = $parentScope;
        if (!empty($model->getTypeAliases()) || !empty($model->getChildModels())) {
            $scope = $this->discoverModelNames($model, $parentScope, $name);

            $scopedAliases = $this->evaluateTypeAliases($model->getTypeAliases(), $scope, $context);
            foreach ($scopedAliases as $aliasName => $aliasData) {
                $context->registerTypeAlias($aliasName, $aliasData);
            }
        }

        $annotations = $this->valueResolver->evaluateAnnotations($model->getAnnotations(), $context);
        $metadata = $this->valueResolver->evaluateMetadata($model->getMetadata(), $context);
        $properties = $this->typeEvaluator->evaluateProperties($model->getProperties(), $name, $scope, $context);

        $context->registerStruct($name, new Struct($name, false, $properties, $metadata, $annotations));

        foreach ($model->getChildModels() as $child) {
            $this->evaluateModel($child, $scope, $name, $context);
        }
    }

    /**
     * @param array<string, TypeAlias> $aliases
     * @return array<string>|null Cycle path if found, null otherwise
     */
    private function detectTypeAliasCycle(array $aliases): ?array
    {
        $graph = [];
        foreach ($aliases as $name => $alias) {
            $type = $alias->getResolvedType();
            $graph[$name] = $type !== null ? $this->collectAliasReferences($type) : [];
        }

        $visited = [];
        $visiting = [];

        foreach (array_keys($graph) as $node) {
            if (isset($visited[$node])) {
                continue;
            }
            $path = [];
            $cycle = $this->dfsDetectCycle($node, $graph, $visited, $visiting, $path);
            if ($cycle !== null) {
                return $cycle;
            }
        }

        return null;
    }

    /**
     * @param array<string, array<string>> $graph
     * @param array<string, true> $visited
     * @param array<string, true> $visiting
     * @param array<string> $path
     * @return array<string>|null
     */
    private function dfsDetectCycle(string $node, array $graph, array &$visited, array &$visiting, array &$path): ?array
    {
        if (isset($visiting[$node])) {
            $cycleStart = (int) array_search($node, $path);
            $cycle = array_slice($path, $cycleStart);
            $cycle[] = $node;
            return $cycle;
        }

        if (isset($visited[$node]) || !isset($graph[$node])) {
            return null;
        }

        $visiting[$node] = true;
        $path[] = $node;

        foreach ($graph[$node] as $neighbor) {
            $cycle = $this->dfsDetectCycle($neighbor, $graph, $visited, $visiting, $path);
            if ($cycle !== null) {
                return $cycle;
            }
        }

        array_pop($path);
        unset($visiting[$node]);
        $visited[$node] = true;

        return null;
    }

    /**
     * @param array<string> $refs
     * @return array<string>
     */
    private function collectAliasReferences(Type $type, array &$refs = []): array
    {
        if ($type->getKind() === TypeKind::Alias && $type->getName() !== null) {
            $refs[] = $type->getName();
        }

        if ($type->getInnerType() !== null) {
            $this->collectAliasReferences($type->getInnerType(), $refs);
        }

        foreach ($type->getUnionTypes() as $unionType) {
            $this->collectAliasReferences($unionType, $refs);
        }

        return $refs;
    }
}
