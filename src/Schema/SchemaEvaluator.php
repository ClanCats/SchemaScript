<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;
use ClanCats\SchemaScript\Node\PropertyNode;
use ClanCats\SchemaScript\Node\MetadataEntryNode;
use ClanCats\SchemaScript\Node\MetadataBlockNode;
use ClanCats\SchemaScript\Node\MetadataListNode;
use ClanCats\SchemaScript\Node\AnnotationNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\ReferenceNode;
use ClanCats\SchemaScript\Node\TypeAliasNode;
use ClanCats\SchemaScript\Node\NamespaceNode;
use ClanCats\SchemaScript\Node\Type\TypeNode;
use ClanCats\SchemaScript\Node\Type\SimpleTypeNode;
use ClanCats\SchemaScript\Node\Type\ArrayTypeNode;
use ClanCats\SchemaScript\Node\Type\NullableTypeNode;
use ClanCats\SchemaScript\Node\Type\UnionTypeNode;
use ClanCats\SchemaScript\Node\Type\InlineObjectTypeNode;
use ClanCats\SchemaScript\Node\Type\StringLiteralTypeNode;
use ClanCats\SchemaScript\Lexer;
use ClanCats\SchemaScript\SchemaNamespace;
use ClanCats\SchemaScript\Parser\ScopeParser;
use ClanCats\SchemaScript\Exception\EvaluatorException;

class SchemaEvaluator
{
    /**
     * @var array<string, Struct>
     */
    private array $structs = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $typeAliasData = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $namespaces = [];

    /**
     * @var array<string, true>
     */
    private array $importedFiles = [];

    /**
     * @var array<string, true>
     */
    private array $resolvingRefs = [];

    private ?SchemaNamespace $schemaNamespace;

    public function __construct(?SchemaNamespace $schemaNamespace = null)
    {
        $this->schemaNamespace = $schemaNamespace;
    }

    public function evaluate(ScopeNode $scope): Definition
    {
        $this->structs = [];
        $this->typeAliasData = [];
        $this->namespaces = [];
        $this->importedFiles = [];
        $this->resolvingRefs = [];

        $this->processImports($scope);

        // Pass 1: Discover all names (types + models) before validation
        $rootScope = $this->discoverNames($scope, null, '');

        // Evaluate namespaces and metadata
        $this->namespaces = $this->evaluateNamespaces($scope->getNamespaces());
        $metadata = $this->evaluateMetadata($scope->getMetadata());

        // Evaluate type aliases (with resolved types)
        $this->typeAliasData = $this->evaluateTypeAliases($scope->getTypeAliases(), $rootScope);

        // Check for collisions between type aliases and model names
        $collisions = array_intersect_key($rootScope->getLocalModelNames(), $rootScope->getLocalTypeAliases());
        if (!empty($collisions)) {
            throw new EvaluatorException(sprintf(
                'Type alias name collides with model name: "%s"',
                implode('", "', array_keys($collisions))
            ));
        }

        // Pass 2: Evaluate all models
        foreach ($scope->getModels() as $model) {
            $this->evaluateModel($model, $rootScope, '');
        }

        return new Definition($metadata, $this->typeAliasData, $this->namespaces, $this->structs);
    }

    /**
     * Pass 1: Recursively discover all type alias names and model names.
     */
    private function discoverNames(ScopeNode $scope, ?TypeScope $parentScope, string $namePrefix): TypeScope
    {
        $typeScope = new TypeScope($parentScope);

        foreach ($scope->getTypeAliases() as $alias) {
            $typeScope->registerTypeAlias($alias);
        }

        $seenModels = [];
        foreach ($scope->getModels() as $model) {
            $name = $model->getName();
            if (isset($seenModels[$name])) {
                throw new EvaluatorException(sprintf('Duplicate model definition: "%s"', $name));
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
     * @param array<MetadataEntryNode> $entryNodes
     * @return array<array{key: string, value: mixed, attributes: array<string, mixed[]>}>
     */
    private function evaluateMetadata(array $entryNodes): array
    {
        $result = [];
        foreach ($entryNodes as $node) {
            $result[] = $this->resolveMetadataEntry($node);
        }
        return $result;
    }

    /**
     * @return array{key: string, value: mixed, attributes: array<string, mixed[]>}
     */
    private function resolveMetadataEntry(MetadataEntryNode $node): array
    {
        $value = $node->getValue();
        return [
            'key' => $node->getKey(),
            'value' => $value !== null ? $this->resolveValue($value) : null,
            'attributes' => $this->evaluateAnnotations($node->getAnnotations()),
        ];
    }

    /**
     * @param array<TypeAliasNode> $aliasNodes
     * @return array<string, array<string, mixed>>
     */
    private function evaluateTypeAliases(array $aliasNodes, TypeScope $scope): array
    {
        $result = [];
        foreach ($aliasNodes as $alias) {
            $name = $alias->getName();
            if (isset($result[$name])) {
                throw new EvaluatorException(sprintf('Duplicate type alias definition: "%s"', $name));
            }

            $resolvedType = null;
            $typeDef = $alias->getTypeDefinition();
            if ($typeDef !== null) {
                $resolvedType = $this->evaluateType($typeDef, $name, $scope);
            }

            $result[$name] = [
                'annotations' => $this->evaluateAnnotations($alias->getAnnotations()),
                'resolvedType' => $resolvedType,
            ];
        }
        return $result;
    }

    /**
     * @param array<NamespaceNode> $namespaceNodes
     * @return array<string, array<string, mixed>>
     */
    private function evaluateNamespaces(array $namespaceNodes): array
    {
        foreach ($namespaceNodes as $ns) {
            $name = $ns->getName();
            if (isset($this->namespaces[$name])) {
                throw new EvaluatorException(sprintf('Duplicate namespace definition: "%s"', $name));
            }
            $constants = [];
            foreach ($ns->getConstants() as $constant) {
                $constName = $constant->getName();
                if (isset($constants[$constName])) {
                    throw new EvaluatorException(sprintf('Duplicate constant "%s" in namespace "%s"', $constName, $name));
                }
                $constValue = $constant->getValue();
                $constants[$constName] = ($constant->hasValue() && $constValue !== null)
                    ? $this->resolveValue($constValue)
                    : $name . '::' . $constName;
            }
            $this->namespaces[$name] = $constants;
        }
        return $this->namespaces;
    }

    private function evaluateModel(ModelDefinitionNode $model, TypeScope $parentScope, string $namePrefix): void
    {
        $name = $namePrefix !== '' ? $namePrefix . '/' . $model->getName() : $model->getName();

        // Create child scope if model has scoped types or child models
        $scope = $parentScope;
        if (!empty($model->getTypeAliases()) || !empty($model->getChildModels())) {
            $scope = $this->discoverModelNames($model, $parentScope, $name);

            // Evaluate scoped type aliases
            $scopedAliases = $this->evaluateTypeAliases($model->getTypeAliases(), $scope);
            foreach ($scopedAliases as $aliasName => $aliasData) {
                $this->typeAliasData[$aliasName] = $aliasData;
            }
        }

        $metadata = $this->evaluateMetadata($model->getMetadata());
        $properties = $this->evaluateProperties($model->getProperties(), $name, $scope);

        $this->structs[$name] = new Struct($name, false, $properties, $metadata);

        // Evaluate child models
        foreach ($model->getChildModels() as $child) {
            $this->evaluateModel($child, $scope, $name);
        }
    }

    /**
     * @param array<PropertyNode> $propertyNodes
     * @return array<StructProperty>
     */
    private function evaluateProperties(array $propertyNodes, string $namePrefix, TypeScope $scope): array
    {
        $result = [];
        $seenNames = [];
        foreach ($propertyNodes as $node) {
            $name = $node->getName();
            if (isset($seenNames[$name])) {
                throw new EvaluatorException(sprintf(
                    'Duplicate property "%s" in "%s"',
                    $name,
                    $namePrefix
                ));
            }
            $seenNames[$name] = true;
            $result[] = $this->evaluateProperty($node, $namePrefix, $scope);
        }
        return $result;
    }

    private function evaluateProperty(PropertyNode $node, string $namePrefix, TypeScope $scope): StructProperty
    {
        $annotations = $this->evaluateAnnotations($node->getAnnotations());
        $nameContext = $namePrefix . $this->toPascalCase($node->getName());
        $type = $this->evaluateType($node->getType(), $nameContext, $scope);

        return new StructProperty(
            $node->getName(),
            $type,
            $node->isOptional(),
            $annotations
        );
    }

    private function evaluateType(TypeNode $node, string $nameContext, TypeScope $scope): Type
    {
        if ($node instanceof StringLiteralTypeNode) {
            return Type::stringLiteral($node->getValue());
        }

        if ($node instanceof SimpleTypeNode) {
            $name = $node->getName();

            if ($scope->isModelName($name)) {
                return Type::reference($name);
            }

            $alias = $scope->resolveType($name);
            if ($alias !== null) {
                $aliasAnnotations = $this->evaluateAnnotations($alias->getAnnotations());
                if (empty($aliasAnnotations)) {
                    return Type::simple($name);
                }
                return Type::alias($name);
            }

            throw new EvaluatorException(sprintf(
                'Unknown type "%s". Did you forget to import it or define it in [type]?',
                $name
            ));
        }

        if ($node instanceof ArrayTypeNode) {
            return Type::array($this->evaluateType($node->getElementType(), $nameContext, $scope));
        }

        if ($node instanceof NullableTypeNode) {
            return Type::nullable($this->evaluateType($node->getInnerType(), $nameContext, $scope));
        }

        if ($node instanceof UnionTypeNode) {
            $types = [];
            foreach ($node->getTypes() as $t) {
                $types[] = $this->evaluateType($t, $nameContext, $scope);
            }
            return Type::union($types);
        }

        if ($node instanceof InlineObjectTypeNode) {
            $structName = $node->hasExplicitName()
                ? (string) $node->getExplicitName()
                : $nameContext;

            if (isset($this->structs[$structName])) {
                throw new EvaluatorException(sprintf(
                    'Inline object struct name collision: "%s" is already defined as a struct',
                    $structName
                ));
            }

            $metadata = $this->evaluateMetadata($node->getMetadata());
            $properties = $this->evaluateProperties($node->getProperties(), $structName, $scope);

            $this->structs[$structName] = new Struct($structName, true, $properties, $metadata);

            return Type::reference($structName);
        }

        throw new EvaluatorException('Unknown type node: ' . get_class($node));
    }

    /**
     * @param array<AnnotationNode> $annotationNodes
     * @return array<string, mixed[]>
     */
    private function evaluateAnnotations(array $annotationNodes): array
    {
        $result = [];
        foreach ($annotationNodes as $annotation) {
            $name = $annotation->getName();
            if (isset($result[$name])) {
                throw new EvaluatorException(sprintf('Duplicate annotation "@%s"', $name));
            }
            $args = [];
            foreach ($annotation->getArguments() as $arg) {
                $args[] = $arg->getValue();
            }
            $result[$name] = $args;
        }
        return $result;
    }

    /**
     * @return mixed
     */
    private function resolveValue(BaseNode $node)
    {
        if ($node instanceof ValueNode) {
            return $node->getValue();
        }
        if ($node instanceof ReferenceNode) {
            $ns = $node->getNamespace();
            $const = $node->getConstant();
            $refKey = $ns . '::' . $const;

            if (isset($this->resolvingRefs[$refKey])) {
                throw new EvaluatorException(sprintf('Circular constant reference detected: "%s"', $refKey));
            }

            if (!isset($this->namespaces[$ns])) {
                throw new EvaluatorException(sprintf('Unknown namespace "%s" in reference "%s::%s"', $ns, $ns, $const));
            }
            if (!isset($this->namespaces[$ns][$const])) {
                throw new EvaluatorException(sprintf('Unknown constant "%s" in namespace "%s"', $const, $ns));
            }

            $this->resolvingRefs[$refKey] = true;
            try {
                $value = $this->namespaces[$ns][$const];
            } finally {
                unset($this->resolvingRefs[$refKey]);
            }
            return $value;
        }
        if ($node instanceof MetadataBlockNode) {
            $result = [];
            foreach ($node->getEntries() as $entry) {
                $result[] = $this->resolveMetadataEntry($entry);
            }
            return $result;
        }
        if ($node instanceof MetadataListNode) {
            $result = [];
            foreach ($node->getItems() as $item) {
                $result[] = $this->resolveValue($item);
            }
            return $result;
        }
        throw new EvaluatorException('Unexpected node type in metadata value: ' . get_class($node));
    }

    private function toPascalCase(string $name): string
    {
        $name = (string) preg_replace('/_{2,}/', '_', $name);
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    private function processImports(ScopeNode $scope): void
    {
        foreach ($scope->getImports() as $import) {
            $path = $import->getPath();

            if ($this->schemaNamespace === null) {
                throw new EvaluatorException(sprintf(
                    'Cannot resolve import "%s": no SchemaNamespace provided',
                    $path
                ));
            }

            $absPath = $this->schemaNamespace->getPath($path);

            if (isset($this->importedFiles[$absPath])) {
                continue;
            }
            $this->importedFiles[$absPath] = true;

            $code = $this->schemaNamespace->getCode($path);
            $tokens = (new Lexer($code, $path))->tokens();
            /** @var ScopeNode $importedScope */
            $importedScope = (new ScopeParser($tokens))->parse();

            $this->processImports($importedScope);
            $this->mergeScope($scope, $importedScope);
        }
    }

    private function mergeScope(ScopeNode $target, ScopeNode $source): void
    {
        foreach ($source->getModels() as $model) {
            $target->addModel($model);
        }
        foreach ($source->getNamespaces() as $namespace) {
            $target->addNamespace($namespace);
        }
        foreach ($source->getMetadata() as $metadata) {
            $target->addMetadata($metadata);
        }
        $existingNames = [];
        foreach ($target->getTypeAliases() as $a) {
            $existingNames[$a->getName()] = true;
        }
        foreach ($source->getTypeAliases() as $alias) {
            if (isset($existingNames[$alias->getName()])) {
                continue;
            }
            $target->addTypeAlias($alias);
        }
    }
}
