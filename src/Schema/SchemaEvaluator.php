<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\TypeAliasNode;
use ClanCats\SchemaScript\Node\NamespaceNode;
use ClanCats\SchemaScript\Node\Type\SimpleTypeNode;
use ClanCats\SchemaScript\Node\Type\GenericTypeNode;

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

        $sortedModels = $this->topologicalSortModels($scope->getModels(), $context);
        foreach ($sortedModels as $model) {
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
        $typeParameters = $model->getTypeParameters();

        $scope = $parentScope;
        $needsChildScope = !empty($model->getTypeAliases()) || !empty($model->getChildModels()) || !empty($typeParameters);

        if ($needsChildScope) {
            $scope = $this->discoverModelNames($model, $parentScope, $name);

            if (count($typeParameters) !== count(array_unique($typeParameters))) {
                $this->throwEvaluatorError(sprintf('Duplicate type parameter in model "%s"', $name), $model, $context);
            }

            foreach ($typeParameters as $typeParam) {
                $scope->registerTypeParameter($typeParam);
            }

            $scopedAliases = $this->evaluateTypeAliases($model->getTypeAliases(), $scope, $context);
            foreach ($scopedAliases as $aliasName => $aliasData) {
                $context->registerTypeAlias($aliasName, $aliasData);
            }
        }

        $annotations = $this->valueResolver->evaluateAnnotations($model->getAnnotations(), $context);
        $metadata = $this->valueResolver->evaluateMetadata($model->getMetadata(), $context);
        $ownProperties = $this->typeEvaluator->evaluateProperties($model->getProperties(), $name, $scope, $context);

        $inheritedProperties = [];
        $inheritedMetadata = [];
        $inheritedAnnotations = [];
        $seenPropertyNames = [];

        foreach ($model->getParentTypes() as $parentTypeNode) {
            $parentStructName = null;
            $typeArgumentMap = [];

            if ($parentTypeNode instanceof SimpleTypeNode) {
                $parentStructName = $parentTypeNode->getName();
            } elseif ($parentTypeNode instanceof GenericTypeNode) {
                $parentStructName = $parentTypeNode->getName();
            }

            if ($parentStructName === null) {
                $this->throwEvaluatorError('Invalid parent type', $parentTypeNode, $context);
            }

            if ($scope->isModelName($parentStructName)) {
                // already fine
            } else {
                $this->throwEvaluatorError(sprintf('Parent type "%s" is not a known model', $parentStructName), $parentTypeNode, $context);
            }

            $parentStruct = $context->getStruct($parentStructName);
            if ($parentStruct === null) {
                $this->throwEvaluatorError(sprintf('Parent model "%s" is not yet evaluated (possible circular inheritance)', $parentStructName), $parentTypeNode, $context);
            }

            if ($parentTypeNode instanceof GenericTypeNode) {
                $parentTypeParams = $parentStruct->getTypeParameters();
                $argNodes = $parentTypeNode->getArguments();
                if (count($parentTypeParams) !== count($argNodes)) {
                    $this->throwEvaluatorError(
                        sprintf('Type argument count mismatch for "%s": expected %d, got %d', $parentStructName, count($parentTypeParams), count($argNodes)),
                        $parentTypeNode,
                        $context
                    );
                }
                $resolvedArgs = [];
                foreach ($argNodes as $argNode) {
                    $resolvedArgs[] = $this->typeEvaluator->evaluateType($argNode, $name, $scope, $context);
                }
                $typeArgumentMap = array_combine($parentTypeParams, $resolvedArgs);
            }

            foreach ($parentStruct->getProperties() as $prop) {
                $propName = $prop->getName();
                if (isset($seenPropertyNames[$propName])) {
                    $this->throwEvaluatorError(
                        sprintf('Duplicate property "%s" inherited from multiple parents in "%s"', $propName, $name),
                        $parentTypeNode,
                        $context
                    );
                }
                $seenPropertyNames[$propName] = true;

                $propType = $prop->getType();
                if (!empty($typeArgumentMap)) {
                    $propType = $this->substituteTypeParameters($propType, $typeArgumentMap);
                }
                $inheritedProperties[] = new StructProperty(
                    $propName,
                    $propType,
                    $prop->isOptional(),
                    $prop->getAnnotations(),
                    $prop->getComment()
                );
            }

            foreach ($parentStruct->getMetadata() as $entry) {
                $inheritedMetadata[$entry->getKey()] = $entry;
            }
            foreach ($parentStruct->getAnnotations()->all() as $annName => $ann) {
                $inheritedAnnotations[$annName] = $ann;
            }
        }

        if (!empty($model->getParentTypes())) {
            foreach ($ownProperties as $prop) {
                if (isset($seenPropertyNames[$prop->getName()])) {
                    $this->throwEvaluatorError(
                        sprintf('Property "%s" in "%s" conflicts with an inherited property', $prop->getName(), $name),
                        $model,
                        $context
                    );
                }
            }

            $allProperties = array_merge($inheritedProperties, $ownProperties);

            foreach ($metadata as $entry) {
                $inheritedMetadata[$entry->getKey()] = $entry;
            }
            $allMetadata = array_values($inheritedMetadata);

            foreach ($annotations->all() as $annName => $ann) {
                $inheritedAnnotations[$annName] = $ann;
            }
            $allAnnotations = new AnnotationCollection($inheritedAnnotations);
        } else {
            $allProperties = $ownProperties;
            $allMetadata = $metadata;
            $allAnnotations = $annotations;
        }

        $context->registerStruct($name, new Struct($name, false, $allProperties, $allMetadata, $allAnnotations, $typeParameters, $model->isPrivate()));

        $sortedChildren = $this->topologicalSortModels($model->getChildModels(), $context);
        foreach ($sortedChildren as $child) {
            $this->evaluateModel($child, $scope, $name, $context);
        }
    }

    /**
     * @param array<ModelDefinitionNode> $models
     * @return array<ModelDefinitionNode>
     */
    private function topologicalSortModels(array $models, EvaluationContext $context): array
    {
        if (count($models) <= 1) {
            return $models;
        }

        $modelsByName = [];
        $deps = [];
        foreach ($models as $model) {
            $modelName = $model->getName();
            $modelsByName[$modelName] = $model;
            $deps[$modelName] = [];
            foreach ($model->getParentTypes() as $parentTypeNode) {
                if ($parentTypeNode instanceof SimpleTypeNode) {
                    $deps[$modelName][] = $parentTypeNode->getName();
                } elseif ($parentTypeNode instanceof GenericTypeNode) {
                    $deps[$modelName][] = $parentTypeNode->getName();
                }
            }
        }

        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach (array_keys($modelsByName) as $name) {
            if (!isset($visited[$name])) {
                $this->topoSortVisit($name, $deps, $modelsByName, $visited, $visiting, $sorted, $context);
            }
        }

        return $sorted;
    }

    /**
     * @param array<string, array<string>> $deps
     * @param array<string, ModelDefinitionNode> $modelsByName
     * @param array<string, true> $visited
     * @param array<string, true> $visiting
     * @param array<ModelDefinitionNode> $sorted
     */
    private function topoSortVisit(string $name, array $deps, array $modelsByName, array &$visited, array &$visiting, array &$sorted, EvaluationContext $context): void
    {
        if (isset($visiting[$name])) {
            $this->throwEvaluatorError(
                sprintf('Circular inheritance detected involving "%s"', $name),
                $modelsByName[$name] ?? null,
                $context
            );
        }

        if (isset($visited[$name])) {
            return;
        }

        $visiting[$name] = true;

        foreach ($deps[$name] ?? [] as $dep) {
            if (isset($modelsByName[$dep])) {
                $this->topoSortVisit($dep, $deps, $modelsByName, $visited, $visiting, $sorted, $context);
            }
        }

        unset($visiting[$name]);
        $visited[$name] = true;

        if (isset($modelsByName[$name])) {
            $sorted[] = $modelsByName[$name];
        }
    }

    /**
     * @param array<string, Type> $map
     */
    private function substituteTypeParameters(Type $type, array $map): Type
    {
        return match ($type->getKind()) {
            TypeKind::TypeParameter => $map[$type->getName() ?? ''] ?? $type,
            TypeKind::Array => Type::array($this->substituteTypeParameters($type->getInnerType() ?? Type::simple('mixed'), $map)),
            TypeKind::Nullable => Type::nullable($this->substituteTypeParameters($type->getInnerType() ?? Type::simple('mixed'), $map)),
            TypeKind::Union => Type::union(array_map(
                fn(Type $t) => $this->substituteTypeParameters($t, $map),
                $type->getUnionTypes()
            )),
            TypeKind::Generic => Type::generic(
                $type->getName() ?? '',
                array_map(fn(Type $t) => $this->substituteTypeParameters($t, $map), $type->getTypeArguments())
            ),
            default => $type,
        };
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

        foreach ($type->getTypeArguments() as $argType) {
            $this->collectAliasReferences($argType, $refs);
        }

        return $refs;
    }
}
