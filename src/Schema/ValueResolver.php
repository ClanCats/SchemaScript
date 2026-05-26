<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\MetadataEntryNode;
use ClanCats\SchemaScript\Node\MetadataBlockNode;
use ClanCats\SchemaScript\Node\MetadataListNode;
use ClanCats\SchemaScript\Node\AnnotationNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\ReferenceNode;

class ValueResolver
{
    use EvaluatorErrorTrait;

    /**
     * @param array<MetadataEntryNode> $entryNodes
     * @return array<MetadataEntry>
     */
    public function evaluateMetadata(array $entryNodes, EvaluationContext $context): array
    {
        $result = [];
        foreach ($entryNodes as $node) {
            $result[] = $this->resolveMetadataEntry($node, $context);
        }
        return $result;
    }

    public function resolveMetadataEntry(MetadataEntryNode $node, EvaluationContext $context): MetadataEntry
    {
        $value = $node->getValue();
        return new MetadataEntry(
            $node->getKey(),
            $value !== null ? $this->resolveValue($value, $context) : null,
            $this->evaluateAnnotations($node->getAnnotations(), $context),
        );
    }

    /**
     * @param array<AnnotationNode> $annotationNodes
     */
    public function evaluateAnnotations(array $annotationNodes, EvaluationContext $context): AnnotationCollection
    {
        $result = [];
        foreach ($annotationNodes as $annotation) {
            $name = $annotation->getName();
            if (isset($result[$name])) {
                $this->throwEvaluatorError(sprintf('Duplicate annotation "@%s"', $name), $annotation, $context);
            }
            $args = [];
            foreach ($annotation->getArguments() as $arg) {
                $args[] = $this->resolveValue($arg, $context);
            }
            $result[$name] = new Annotation($name, $args);
        }
        return new AnnotationCollection($result);
    }

    /**
     * @return mixed
     */
    public function resolveValue(BaseNode $node, EvaluationContext $context)
    {
        if ($node instanceof ValueNode) {
            if ($node->getType() === ValueNode::TYPE_IDENTIFIER) {
                $name = (string) $node->getValue();
                if ($context->hasValueConstant($name)) {
                    $constValue = $context->getValueConstant($name)->getValue();
                    if ($constValue !== null) {
                        return $this->resolveValue($constValue, $context);
                    }
                }
                if ($context->hasIdentifierConstant($name)) {
                    return $context->getIdentifierConstant($name);
                }
            }
            return $node->getValue();
        }
        if ($node instanceof ReferenceNode) {
            $ns = $node->getNamespace();
            $const = $node->getConstant();
            $refKey = $ns . '::' . $const;

            if ($context->isResolvingRef($refKey)) {
                $this->throwEvaluatorError(sprintf('Circular constant reference detected: "%s"', $refKey), $node, $context);
            }

            if (!$context->hasNamespace($ns)) {
                $this->throwEvaluatorError(sprintf('Unknown namespace "%s" in reference "%s::%s"', $ns, $ns, $const), $node, $context);
            }
            if (!$context->hasNamespaceConstant($ns, $const)) {
                $this->throwEvaluatorError(sprintf('Unknown constant "%s" in namespace "%s"', $const, $ns), $node, $context);
            }

            $context->pushResolvingRef($refKey);
            try {
                $value = $context->getNamespaceConstant($ns, $const);
            } finally {
                $context->popResolvingRef($refKey);
            }
            return $value;
        }
        if ($node instanceof MetadataBlockNode) {
            $result = [];
            foreach ($node->getEntries() as $entry) {
                $result[] = $this->resolveMetadataEntry($entry, $context);
            }
            return $result;
        }
        if ($node instanceof MetadataListNode) {
            $result = [];
            foreach ($node->getItems() as $item) {
                $result[] = $this->resolveValue($item, $context);
            }
            return $result;
        }
        $this->throwEvaluatorError('Unexpected node type in metadata value: ' . get_class($node), $node, $context);
    }
}
