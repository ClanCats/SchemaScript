<?php

namespace ClanCats\SchemaScript\Visitor;

use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\NodeVisitorInterface;
use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;
use ClanCats\SchemaScript\Node\PropertyNode;
use ClanCats\SchemaScript\Node\AnnotationNode;
use ClanCats\SchemaScript\Node\CommentNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\MetadataEntryNode;
use ClanCats\SchemaScript\Node\MetadataBlockNode;
use ClanCats\SchemaScript\Node\MetadataListNode;
use ClanCats\SchemaScript\Node\NamespaceNode;
use ClanCats\SchemaScript\Node\ConstantNode;
use ClanCats\SchemaScript\Node\ReferenceNode;
use ClanCats\SchemaScript\Node\ImportNode;
use ClanCats\SchemaScript\Node\TypeAliasNode;
use ClanCats\SchemaScript\Node\Type\TypeNode;
use ClanCats\SchemaScript\Node\Type\SimpleTypeNode;
use ClanCats\SchemaScript\Node\Type\ArrayTypeNode;
use ClanCats\SchemaScript\Node\Type\NullableTypeNode;
use ClanCats\SchemaScript\Node\Type\UnionTypeNode;
use ClanCats\SchemaScript\Node\Type\InlineObjectTypeNode;
use ClanCats\SchemaScript\Node\Type\StringLiteralTypeNode;

class ASTPrinter implements NodeVisitorInterface
{
    private int $depth = 0;
    private string $output = '';

    public function print(BaseNode $node): string
    {
        $this->depth = 0;
        $this->output = '';
        $node->accept($this);
        return $this->output;
    }

    private function line(string $text): void
    {
        $this->output .= str_repeat('  ', $this->depth) . $text . "\n";
    }

    private function typeToString(TypeNode $type): string
    {
        if ($type instanceof SimpleTypeNode) {
            return $type->getName();
        }

        if ($type instanceof ArrayTypeNode) {
            return $this->typeToString($type->getElementType()) . '[]';
        }

        if ($type instanceof NullableTypeNode) {
            return $this->typeToString($type->getInnerType()) . '?';
        }

        if ($type instanceof UnionTypeNode) {
            return implode('|', array_map(
                fn(TypeNode $t) => $this->typeToString($t),
                $type->getTypes()
            ));
        }

        if ($type instanceof InlineObjectTypeNode) {
            return '{...}';
        }

        if ($type instanceof StringLiteralTypeNode) {
            return "'" . $type->getValue() . "'";
        }

        return '?';
    }

    private function valueToString(BaseNode $node): string
    {
        if ($node instanceof ValueNode) {
            if ($node->getType() === ValueNode::TYPE_STRING) {
                return '"' . $node->getValue() . '"';
            }
            if ($node->getType() === ValueNode::TYPE_BOOLEAN) {
                return $node->getValue() ? 'true' : 'false';
            }
            if ($node->getType() === ValueNode::TYPE_IDENTIFIER) {
                return (string) $node->getValue();
            }
            return (string) $node->getValue();
        }

        if ($node instanceof ReferenceNode) {
            return $node->getNamespace() . '::' . $node->getConstant();
        }

        if ($node instanceof MetadataBlockNode) {
            return '{...}';
        }

        if ($node instanceof MetadataListNode) {
            $items = array_map(fn(BaseNode $item) => $this->valueToString($item), $node->getItems());
            return '{' . implode(', ', $items) . '}';
        }

        return '?';
    }

    public function visitScope(ScopeNode $node): void
    {
        $this->line('Scope');
        $this->depth++;
        foreach ($node->getImports() as $import) {
            $import->accept($this);
        }
        foreach ($node->getMetadata() as $metadata) {
            $metadata->accept($this);
        }
        foreach ($node->getTypeAliases() as $alias) {
            $alias->accept($this);
        }
        foreach ($node->getConstants() as $constant) {
            $constant->accept($this);
        }
        foreach ($node->getNamespaces() as $namespace) {
            $namespace->accept($this);
        }
        foreach ($node->getModels() as $model) {
            $model->accept($this);
        }
        $this->depth--;
    }

    public function visitModelDefinition(ModelDefinitionNode $node): void
    {
        $this->line('Model: ' . $node->getName());
        $this->depth++;
        foreach ($node->getMetadata() as $metadata) {
            $metadata->accept($this);
        }
        foreach ($node->getTypeAliases() as $alias) {
            $alias->accept($this);
        }
        foreach ($node->getProperties() as $property) {
            $property->accept($this);
        }
        foreach ($node->getChildModels() as $child) {
            $child->accept($this);
        }
        $this->depth--;
    }

    public function visitProperty(PropertyNode $node): void
    {
        $opt = $node->isOptional() ? '?' : '';
        $this->line('Property: ' . $node->getName() . $opt . ': ' . $this->typeToString($node->getType()));
        $this->depth++;
        if ($node->getComment() !== null) {
            $node->getComment()->accept($this);
        }
        foreach ($node->getAnnotations() as $annotation) {
            $annotation->accept($this);
        }
        if ($node->getType() instanceof InlineObjectTypeNode) {
            $node->getType()->accept($this);
        }
        $this->depth--;
    }

    public function visitAnnotation(AnnotationNode $node): void
    {
        $args = '';
        if (count($node->getArguments()) > 0) {
            $args = '(' . implode(', ', array_map(
                fn(BaseNode $v) => $this->valueToString($v),
                $node->getArguments()
            )) . ')';
        }
        $this->line('Annotation: @' . $node->getName() . $args);
    }

    public function visitValue(ValueNode $node): void
    {
        $this->line('Value: ' . $this->valueToString($node));
    }

    public function visitMetadataEntry(MetadataEntryNode $node): void
    {
        $value = $node->getValue();

        if ($value instanceof MetadataBlockNode) {
            $this->line('Metadata: [' . $node->getKey() . '] = {');
            $this->depth++;
            foreach ($node->getAnnotations() as $annotation) {
                $annotation->accept($this);
            }
            foreach ($value->getEntries() as $entry) {
                $entry->accept($this);
            }
            $this->depth--;
            $this->line('}');
            return;
        }

        $valueStr = $value !== null ? $this->valueToString($value) : 'null';
        $this->line('Metadata: [' . $node->getKey() . '] = ' . $valueStr);
        $this->depth++;
        foreach ($node->getAnnotations() as $annotation) {
            $annotation->accept($this);
        }
        $this->depth--;
    }

    public function visitMetadataBlock(MetadataBlockNode $node): void
    {
        $this->line('MetadataBlock {');
        $this->depth++;
        foreach ($node->getEntries() as $entry) {
            $entry->accept($this);
        }
        $this->depth--;
        $this->line('}');
    }

    public function visitMetadataList(MetadataListNode $node): void
    {
        $items = array_map(fn(BaseNode $item) => $this->valueToString($item), $node->getItems());
        $this->line('MetadataList: {' . implode(', ', $items) . '}');
    }

    public function visitNamespace(NamespaceNode $node): void
    {
        $this->line('Namespace: ' . $node->getName());
        $this->depth++;
        foreach ($node->getConstants() as $constant) {
            $constant->accept($this);
        }
        foreach ($node->getChildren() as $child) {
            $child->accept($this);
        }
        $this->depth--;
    }

    public function visitConstant(ConstantNode $node): void
    {
        $value = $node->getValue();
        $suffix = $value !== null ? ' = ' . $this->valueToString($value) : '';
        $this->line('Constant: ' . $node->getName() . $suffix);
    }

    public function visitReference(ReferenceNode $node): void
    {
        $this->line('Reference: ' . $node->getNamespace() . '::' . $node->getConstant());
    }

    public function visitTypeAlias(TypeAliasNode $node): void
    {
        $typeDef = $node->getTypeDefinition();
        $suffix = $typeDef !== null ? ' = ' . $this->typeToString($typeDef) : '';
        $prefix = $node->isPublic() ? 'pub ' : '';
        $this->line($prefix . 'TypeAlias: ' . $node->getName() . $suffix);
        $this->depth++;
        foreach ($node->getAnnotations() as $annotation) {
            $annotation->accept($this);
        }
        if ($typeDef instanceof InlineObjectTypeNode) {
            $typeDef->accept($this);
        }
        $this->depth--;
    }

    public function visitSimpleType(SimpleTypeNode $node): void
    {
        $this->line('SimpleType: ' . $node->getName());
    }

    public function visitArrayType(ArrayTypeNode $node): void
    {
        $this->line('ArrayType');
        $this->depth++;
        $node->getElementType()->accept($this);
        $this->depth--;
    }

    public function visitNullableType(NullableTypeNode $node): void
    {
        $this->line('NullableType');
        $this->depth++;
        $node->getInnerType()->accept($this);
        $this->depth--;
    }

    public function visitUnionType(UnionTypeNode $node): void
    {
        $this->line('UnionType');
        $this->depth++;
        foreach ($node->getTypes() as $type) {
            $type->accept($this);
        }
        $this->depth--;
    }

    public function visitImport(ImportNode $node): void
    {
        $this->line('Import: ' . $node->getPath());
    }

    public function visitInlineObjectType(InlineObjectTypeNode $node): void
    {
        foreach ($node->getMetadata() as $metadata) {
            $metadata->accept($this);
        }
        foreach ($node->getProperties() as $property) {
            $property->accept($this);
        }
    }

    public function visitStringLiteralType(StringLiteralTypeNode $node): void
    {
        $this->line("StringLiteral: '" . $node->getValue() . "'");
    }

    public function visitComment(CommentNode $node): void
    {
        $this->line('Comment: ' . $node->getText());
    }
}
