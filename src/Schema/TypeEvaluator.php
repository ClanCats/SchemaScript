<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\PropertyNode;
use ClanCats\SchemaScript\Workbench\Str;
use ClanCats\SchemaScript\Node\Type\TypeNode;
use ClanCats\SchemaScript\Node\Type\SimpleTypeNode;
use ClanCats\SchemaScript\Node\Type\ArrayTypeNode;
use ClanCats\SchemaScript\Node\Type\NullableTypeNode;
use ClanCats\SchemaScript\Node\Type\UnionTypeNode;
use ClanCats\SchemaScript\Node\Type\InlineObjectTypeNode;
use ClanCats\SchemaScript\Node\Type\StringLiteralTypeNode;
use ClanCats\SchemaScript\Node\Type\GenericTypeNode;

class TypeEvaluator
{
    use EvaluatorErrorTrait;

    private ValueResolver $valueResolver;

    public function __construct(ValueResolver $valueResolver)
    {
        $this->valueResolver = $valueResolver;
    }

    public function evaluateType(TypeNode $node, string $nameContext, TypeScope $scope, EvaluationContext $context): Type
    {
        if ($node instanceof StringLiteralTypeNode) {
            return Type::stringLiteral($node->getValue());
        }

        if ($node instanceof SimpleTypeNode) {
            return $this->evaluateSimpleType($node, $scope, $context);
        }

        if ($node instanceof ArrayTypeNode) {
            return Type::array($this->evaluateType($node->getElementType(), $nameContext, $scope, $context));
        }

        if ($node instanceof NullableTypeNode) {
            return Type::nullable($this->evaluateType($node->getInnerType(), $nameContext, $scope, $context));
        }

        if ($node instanceof UnionTypeNode) {
            return $this->evaluateUnionType($node, $nameContext, $scope, $context);
        }

        if ($node instanceof InlineObjectTypeNode) {
            return $this->evaluateInlineObjectType($node, $nameContext, $scope, $context);
        }

        if ($node instanceof GenericTypeNode) {
            return $this->evaluateGenericType($node, $nameContext, $scope, $context);
        }

        $this->throwEvaluatorError('Unknown type node: ' . get_class($node), $node, $context);
    }

    private function evaluateSimpleType(SimpleTypeNode $node, TypeScope $scope, EvaluationContext $context): Type
    {
        $name = $node->getName();

        $entry = $scope->resolveType($name);
        if ($entry !== null && $entry->isTypeParameter()) {
            return Type::typeParameter($name);
        }

        if ($scope->isModelName($name)) {
            return Type::reference($name);
        }

        if ($entry !== null) {
            if (!$entry->hasTypeDefinition()) {
                return Type::simple($name);
            }
            return Type::alias($name);
        }

        $this->throwEvaluatorError(sprintf(
            'Unknown type "%s". Did you forget to import it or define it in [type]?',
            $name
        ), $node, $context);
    }

    private function evaluateUnionType(UnionTypeNode $node, string $nameContext, TypeScope $scope, EvaluationContext $context): Type
    {
        $types = [];
        foreach ($node->getTypes() as $t) {
            $types[] = $this->evaluateType($t, $nameContext, $scope, $context);
        }
        return Type::union($types);
    }

    private function evaluateInlineObjectType(InlineObjectTypeNode $node, string $nameContext, TypeScope $scope, EvaluationContext $context): Type
    {
        $structName = $node->hasExplicitName()
            ? (string) $node->getExplicitName()
            : $nameContext;

        if ($context->hasStruct($structName)) {
            $this->throwEvaluatorError(sprintf(
                'Inline object struct name collision: "%s" is already defined as a struct',
                $structName
            ), $node, $context);
        }

        $metadata = $this->valueResolver->evaluateMetadata($node->getMetadata(), $context);
        $properties = $this->evaluateProperties($node->getProperties(), $structName, $scope, $context);

        $context->registerStruct($structName, new Struct($structName, true, $properties, $metadata));

        return Type::reference($structName);
    }

    private function evaluateGenericType(GenericTypeNode $node, string $nameContext, TypeScope $scope, EvaluationContext $context): Type
    {
        $baseName = $node->getName();
        $typeArguments = [];
        foreach ($node->getArguments() as $argNode) {
            $typeArguments[] = $this->evaluateType($argNode, $nameContext, $scope, $context);
        }

        return Type::generic($baseName, $typeArguments);
    }

    /**
     * @param array<PropertyNode> $propertyNodes
     * @return array<StructProperty>
     */
    public function evaluateProperties(array $propertyNodes, string $namePrefix, TypeScope $scope, EvaluationContext $context): array
    {
        $result = [];
        $seenNames = [];
        foreach ($propertyNodes as $node) {
            $name = $node->getName();
            if (isset($seenNames[$name])) {
                $this->throwEvaluatorError(sprintf(
                    'Duplicate property "%s" in "%s"',
                    $name,
                    $namePrefix
                ), $node, $context);
            }
            $seenNames[$name] = true;
            $result[] = $this->evaluateProperty($node, $namePrefix, $scope, $context);
        }
        return $result;
    }

    private function evaluateProperty(PropertyNode $node, string $namePrefix, TypeScope $scope, EvaluationContext $context): StructProperty
    {
        $name = $node->getName();
        if ($context->hasIdentifierConstant($name)) {
            $name = $context->getIdentifierConstant($name);
        }

        $annotations = $this->valueResolver->evaluateAnnotations($node->getAnnotations(), $context);
        $nameContext = $namePrefix . Str::toPascalCase($name);
        $type = $this->evaluateType($node->getType(), $nameContext, $scope, $context);

        $comment = $node->getComment();

        return new StructProperty(
            $name,
            $type,
            $node->isOptional(),
            $annotations,
            $comment !== null ? $comment->getText() : null
        );
    }
}
