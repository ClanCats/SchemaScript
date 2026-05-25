<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\TypeAliasNode;
use ClanCats\SchemaScript\Node\NamespaceNode;
use ClanCats\SchemaScript\Node\Type\SimpleTypeNode;
use ClanCats\SchemaScript\SchemaNamespace;

class SchemaEvaluator
{
    use EvaluatorErrorTrait;

    private ImportResolver $importResolver;

    private ValueResolver $valueResolver;

    private TypeEvaluator $typeEvaluator;

    public function __construct(?SchemaNamespace $schemaNamespace = null)
    {
        $this->importResolver = new ImportResolver($schemaNamespace);
        $this->valueResolver = new ValueResolver();
        $this->typeEvaluator = new TypeEvaluator($this->valueResolver);
    }

    public function evaluate(ScopeNode $scope, ?string $sourceCode = null, ?string $filename = null): Definition
    {
        $context = new EvaluationContext();

        if ($sourceCode !== null) {
            $context->sourceCodeMap[$filename ?? ''] = $sourceCode;
        }

        $this->importResolver->processImports($scope, $context);

        $rootScope = $this->discoverNames($scope, null, '', $context);

        $this->evaluateNamespaces($scope->getNamespaces(), $context);

        $this->processScopeConstants($scope, $rootScope, $context);

        $metadata = $this->valueResolver->evaluateMetadata($scope->getMetadata(), $context);

        $context->typeAliasData = $this->evaluateTypeAliases($scope->getTypeAliases(), $rootScope, $context);

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

        return new Definition($metadata, $context->typeAliasData, $context->namespaces, $context->structs);
    }

    private function discoverNames(ScopeNode $scope, ?TypeScope $parentScope, string $namePrefix, EvaluationContext $context): TypeScope
    {
        $typeScope = new TypeScope($parentScope);

        foreach ($scope->getTypeAliases() as $alias) {
            $typeScope->registerTypeAlias($alias);
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
            $childScope->registerTypeAlias($alias);
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
            if (isset($context->namespaces[$fullName])) {
                $this->throwEvaluatorError(sprintf('Duplicate namespace definition: "%s"', $fullName), $ns, $context);
            }
            $constants = [];
            foreach ($ns->getConstants() as $constant) {
                $constName = $constant->getName();
                if (isset($constants[$constName])) {
                    $this->throwEvaluatorError(sprintf('Duplicate constant "%s" in namespace "%s"', $constName, $fullName), $constant, $context);
                }
                $constValue = $constant->getValue();
                $constants[$constName] = ($constant->hasValue() && $constValue !== null)
                    ? $this->valueResolver->resolveValue($constValue, $context)
                    : $fullName . '::' . $constName;
            }
            $context->namespaces[$fullName] = $constants;
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
                    $aliasNode = new TypeAliasNode($name);
                    $aliasNode->setTypeDefinition(new SimpleTypeNode($identifier));
                    $scope->addTypeAlias($aliasNode);
                    $rootScope->registerTypeAlias($aliasNode);
                }

                $context->identifierConstants[$name] = $identifier;
            } elseif ($value !== null) {
                $context->valueConstants[$name] = $constant;
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
                $context->typeAliasData[$aliasName] = $aliasData;
            }
        }

        $metadata = $this->valueResolver->evaluateMetadata($model->getMetadata(), $context);
        $properties = $this->typeEvaluator->evaluateProperties($model->getProperties(), $name, $scope, $context);

        $context->structs[$name] = new Struct($name, false, $properties, $metadata);

        foreach ($model->getChildModels() as $child) {
            $this->evaluateModel($child, $scope, $name, $context);
        }
    }
}
