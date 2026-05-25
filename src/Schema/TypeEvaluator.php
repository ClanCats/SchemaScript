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
            $name = $node->getName();

            if ($scope->isModelName($name)) {
                return Type::reference($name);
            }

            $alias = $scope->resolveType($name);
            if ($alias !== null) {
                if ($alias->getTypeDefinition() === null) {
                    return Type::simple($name);
                }
                return Type::alias($name);
            }

            $this->throwEvaluatorError(sprintf(
                'Unknown type "%s". Did you forget to import it or define it in [type]?',
                $name
            ), $node, $context);
        }

        if ($node instanceof ArrayTypeNode) {
            return Type::array($this->evaluateType($node->getElementType(), $nameContext, $scope, $context));
        }

        if ($node instanceof NullableTypeNode) {
            return Type::nullable($this->evaluateType($node->getInnerType(), $nameContext, $scope, $context));
        }

        if ($node instanceof UnionTypeNode) {
            $types = [];
            foreach ($node->getTypes() as $t) {
                $types[] = $this->evaluateType($t, $nameContext, $scope, $context);
            }
            return Type::union($types);
        }

        if ($node instanceof InlineObjectTypeNode) {
            $structName = $node->hasExplicitName()
                ? (string) $node->getExplicitName()
                : $nameContext;

            if (isset($context->structs[$structName])) {
                $this->throwEvaluatorError(sprintf(
                    'Inline object struct name collision: "%s" is already defined as a struct',
                    $structName
                ), $node, $context);
            }

            $metadata = $this->valueResolver->evaluateMetadata($node->getMetadata(), $context);
            $properties = $this->evaluateProperties($node->getProperties(), $structName, $scope, $context);

            $context->structs[$structName] = new Struct($structName, true, $properties, $metadata);

            return Type::reference($structName);
        }

        $this->throwEvaluatorError('Unknown type node: ' . get_class($node), $node, $context);
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
        if (isset($context->identifierConstants[$name])) {
            $name = $context->identifierConstants[$name];
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
