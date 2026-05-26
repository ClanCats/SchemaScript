<?php

namespace ClanCats\SchemaScript\Node;

use ClanCats\SchemaScript\Node\Type\SimpleTypeNode;
use ClanCats\SchemaScript\Node\Type\ArrayTypeNode;
use ClanCats\SchemaScript\Node\Type\NullableTypeNode;
use ClanCats\SchemaScript\Node\Type\UnionTypeNode;
use ClanCats\SchemaScript\Node\Type\InlineObjectTypeNode;
use ClanCats\SchemaScript\Node\Type\StringLiteralTypeNode;
use ClanCats\SchemaScript\Node\Type\GenericTypeNode;

interface NodeVisitorInterface
{
    public function visitScope(ScopeNode $node): void;
    public function visitModelDefinition(ModelDefinitionNode $node): void;
    public function visitProperty(PropertyNode $node): void;
    public function visitAnnotation(AnnotationNode $node): void;
    public function visitValue(ValueNode $node): void;
    public function visitMetadataEntry(MetadataEntryNode $node): void;
    public function visitMetadataBlock(MetadataBlockNode $node): void;
    public function visitMetadataList(MetadataListNode $node): void;
    public function visitNamespace(NamespaceNode $node): void;
    public function visitConstant(ConstantNode $node): void;
    public function visitReference(ReferenceNode $node): void;
    public function visitTypeAlias(TypeAliasNode $node): void;
    public function visitSimpleType(SimpleTypeNode $node): void;
    public function visitArrayType(ArrayTypeNode $node): void;
    public function visitNullableType(NullableTypeNode $node): void;
    public function visitUnionType(UnionTypeNode $node): void;
    public function visitImport(ImportNode $node): void;
    public function visitInlineObjectType(InlineObjectTypeNode $node): void;
    public function visitStringLiteralType(StringLiteralTypeNode $node): void;
    public function visitComment(CommentNode $node): void;
    public function visitGenericType(GenericTypeNode $node): void;
}
