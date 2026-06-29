<?php

namespace ClanCats\SchemaScript\Writer;

use ClanCats\SchemaScript\Schema\Annotation;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\MetadataEntry;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\Type;

class ScscWriter
{
    public function write(Definition $definition): string
    {
        $lines = [];
        $lines[] = 'import scsc/base';
        $lines[] = '';

        $this->writeMetadata($definition, $lines);
        $this->writeModels($definition, $lines);

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> &$lines
     */
    private function writeMetadata(Definition $definition, array &$lines): void
    {
        foreach ($definition->getMetadata() as $entry) {
            foreach ($entry->getAttributes()->all() as $annotation) {
                $lines[] = $this->formatAnnotation($annotation);
            }
            $lines[] = '[' . $entry->getKey() . '] = ' . $this->formatMetadataValue($entry->getValue());
            $lines[] = '';
        }
    }

    /**
     * @param list<string> &$lines
     */
    private function writeModels(Definition $definition, array &$lines): void
    {
        foreach ($definition->getAllModels() as $struct) {
            foreach ($struct->getAnnotations()->all() as $annotation) {
                $lines[] = $this->formatAnnotation($annotation);
            }
            $privatePrefix = $struct->isPrivate() ? 'private ' : '';
            $typeParamSuffix = '';
            if ($struct->isGeneric()) {
                $typeParamSuffix = '<' . implode(', ', $struct->getTypeParameters()) . '>';
            }
            $lines[] = $privatePrefix . $struct->getName() . $typeParamSuffix . ' {';

            foreach ($struct->getProperties() as $prop) {
                $this->writeProperty($prop, $lines);
            }

            $lines[] = '}';
            $lines[] = '';
        }
    }

    /**
     * @param list<string> &$lines
     */
    private function writeProperty(StructProperty $prop, array &$lines): void
    {
        foreach ($prop->getAnnotations()->all() as $annotation) {
            $lines[] = '    ' . $this->formatAnnotation($annotation);
        }

        $optional = $prop->isOptional() ? '?' : '';
        $lines[] = '    ' . $prop->getName() . $optional . ': ' . $this->formatType($prop->getType());
    }

    private function formatAnnotation(Annotation $annotation): string
    {
        $args = $annotation->getArguments();
        if (empty($args)) {
            return '@' . $annotation->getName();
        }

        $formatted = array_map(fn($a) => $this->formatAnnotationArg($a), $args);
        return '@' . $annotation->getName() . '(' . implode(', ', $formatted) . ')';
    }

    private function formatAnnotationArg(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return "'" . addslashes((string) $value) . "'";
    }

    private function formatType(Type $type): string
    {
        return $type->accept(new ScscTypeStringVisitor());
    }

    private function formatMetadataValue(mixed $value): string
    {
        if (is_string($value)) {
            if (str_contains($value, '::') || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
                return $value;
            }
            return "'" . addslashes($value) . "'";
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return $this->formatMetadataBlock($value);
        }
        return "'" . addslashes((string) $value) . "'";
    }

    /**
     * @param array<mixed> $entries
     */
    private function formatMetadataBlock(array $entries, int $indent = 0): string
    {
        $prefix = str_repeat('  ', $indent);
        $inner = str_repeat('  ', $indent + 1);
        $lines = ['{'];

        foreach ($entries as $entry) {
            if ($entry instanceof MetadataEntry) {
                foreach ($entry->getAttributes()->all() as $annotation) {
                    $lines[] = $inner . $this->formatAnnotation($annotation);
                }

                $val = $entry->getValue();
                $key = $entry->getKey();
                $isBracketedKey = is_array($val) && str_contains($key, '.');
                $formattedKey = $isBracketedKey ? '[' . $key . ']' : $key;

                if (is_array($val)) {
                    $lines[] = $inner . $formattedKey . ' = ' . $this->formatMetadataBlock($val, $indent + 1);
                } else {
                    $lines[] = $inner . $formattedKey . ' = ' . $this->formatMetadataValue($val);
                }
            }
        }

        $lines[] = $prefix . '}';
        return implode("\n", $lines);
    }
}
