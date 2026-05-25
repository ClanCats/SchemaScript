<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Generator\GeneratorInterface;
use ClanCats\SchemaScript\Generator\GeneratorResult;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\Type;

class PhpSamgGenerator implements GeneratorInterface
{
    public function getName(): string
    {
        return 'php.samg';
    }

    public function getDescription(): string
    {
        return 'Generates PHP SAMG v2 mapper classes with 10 mapping methods';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function generate(Definition $definition, array $options = []): GeneratorResult
    {
        $result = new GeneratorResult();
        $namespace = $options['namespace'] ?? null;

        $ctx = new PhpSamgContext();
        $ctx->mapFrom = $options['map_from'] ?? 'self';
        $ctx->mapTo = $options['map_to'] ?? 'api';

        foreach ($definition->getModels() as $struct) {
            $code = $this->generateMapClass($ctx, $struct, $definition, $namespace);
            $result->addFile($struct->getName() . 'Map.php', $code);
        }

        return $result;
    }

    private function generateMapClass(PhpSamgContext $ctx, Struct $struct, Definition $definition, ?string $namespace): string
    {
        $name = str_replace('/', '', $struct->getName());
        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        if ($namespace !== null) {
            $lines[] = 'namespace ' . rtrim($namespace, '\\') . ';';
            $lines[] = '';
        }
        $lines[] = "class {$name}Map";
        $lines[] = '{';

        $methods = [
            $this->generateFullMapping($ctx, $struct, $definition, 'localToInterface'),
            $this->generateFullMapping($ctx, $struct, $definition, 'localToInterfaceMapOnly'),
            $this->generateFullMapping($ctx, $struct, $definition, 'interfaceToLocal'),
            $this->generateFullMapping($ctx, $struct, $definition, 'interfaceToLocalMapOnly'),
            $this->generatePartialMapping($ctx, $struct, $definition, 'localToPartialInterface'),
            $this->generatePartialMapping($ctx, $struct, $definition, 'localToPartialInterfaceMapOnly'),
            $this->generatePartialMapping($ctx, $struct, $definition, 'interfaceToPartialLocal'),
            $this->generatePartialMapping($ctx, $struct, $definition, 'interfaceToPartialLocalMapOnly'),
            $this->generateCastMethod($ctx, $struct, $definition, 'castLocal'),
            $this->generateCastMethod($ctx, $struct, $definition, 'castInterface'),
        ];

        $lines[] = implode("\n\n", $methods);
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function generateFullMapping(PhpSamgContext $ctx, Struct $struct, Definition $definition, string $methodName): string
    {
        $direction = $this->resolveDirection($methodName);
        $withCast = !str_ends_with($methodName, 'MapOnly');

        $lines = [];
        $lines[] = "    /**";
        $lines[] = "     * @param array<mixed> \$array";
        $lines[] = "     * @return array<mixed>";
        $lines[] = "     */";
        $lines[] = "    public static function {$methodName}(array \$array): array";
        $lines[] = '    {';
        $lines[] = '        return [';

        foreach ($struct->getProperties() as $prop) {
            [$readKey, $writeKey] = $this->resolveKeys($ctx, $prop, $definition, $direction);
            $access = "\$array['{$readKey}']";

            if ($withCast) {
                $expr = $this->castExpression($prop->getType(), $access, $definition, $direction);
            } else {
                $expr = "{$access} ?? null";
            }

            $lines[] = "            '{$writeKey}' => {$expr},";
        }

        $lines[] = '        ];';
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    private function generatePartialMapping(PhpSamgContext $ctx, Struct $struct, Definition $definition, string $methodName): string
    {
        $direction = $this->resolveDirection($methodName);
        $withCast = !str_ends_with($methodName, 'MapOnly');

        $lines = [];
        $lines[] = "    /**";
        $lines[] = "     * @param array<mixed> \$array";
        $lines[] = "     * @return array<mixed>";
        $lines[] = "     */";
        $lines[] = "    public static function {$methodName}(array \$array): array";
        $lines[] = '    {';
        $lines[] = '        $buffer = [];';

        foreach ($struct->getProperties() as $prop) {
            [$readKey, $writeKey] = $this->resolveKeys($ctx, $prop, $definition, $direction);
            $access = "\$array['{$readKey}']";

            if ($withCast) {
                if ($prop->getType()->isNullable()) {
                    $inner = $prop->getType()->getInnerType();
                    $innerCast = $inner !== null ? $this->castExpression($inner, $access, $definition, $direction) : $access;
                    $expr = "({$access} === null) ? null : {$innerCast}";
                } else {
                    $expr = $this->castExpression($prop->getType(), $access, $definition, $direction);
                }
            } else {
                $expr = $access;
            }

            $lines[] = "        if (array_key_exists('{$readKey}', \$array)) {";
            $lines[] = "            \$buffer['{$writeKey}'] = {$expr};";
            $lines[] = '        }';
        }

        $lines[] = '        return $buffer;';
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    private function generateCastMethod(PhpSamgContext $ctx, Struct $struct, Definition $definition, string $methodName): string
    {
        $isLocal = $methodName === 'castLocal';
        $mapName = $isLocal ? ($ctx->mapFrom ?? 'self') : ($ctx->mapTo ?? 'api');

        $lines = [];
        $lines[] = "    /**";
        $lines[] = "     * @param array<mixed> \$array";
        $lines[] = "     */";
        $lines[] = "    public static function {$methodName}(array &\$array): void";
        $lines[] = '    {';

        foreach ($struct->getProperties() as $prop) {
            $key = $definition->resolveMapKey($mapName, $prop->getName(), $prop->getAnnotations());
            $access = "\$array['{$key}']";

            $expr = $this->castExpression($prop->getType(), $access, $definition, 'localToInterface');
            $lines[] = "        \$array['{$key}'] = {$expr};";
        }

        $lines[] = '    }';

        return implode("\n", $lines);
    }

    /**
     * @return array{string, string}
     */
    private function resolveKeys(PhpSamgContext $ctx, StructProperty $prop, Definition $definition, string $direction): array
    {
        $localKey = $definition->resolveMapKey($ctx->mapFrom ?? 'self', $prop->getName(), $prop->getAnnotations());
        $interfaceKey = $definition->resolveMapKey($ctx->mapTo ?? 'api', $prop->getName(), $prop->getAnnotations());

        if ($direction === 'localToInterface') {
            return [$localKey, $interfaceKey];
        }

        return [$interfaceKey, $localKey];
    }

    private function resolveDirection(string $methodName): string
    {
        if (str_starts_with($methodName, 'localTo')) {
            return 'localToInterface';
        }
        return 'interfaceToLocal';
    }

    private function castExpression(Type $type, string $access, Definition $definition, string $direction): string
    {
        if ($type->isNullable()) {
            $inner = $type->getInnerType();
            if ($inner === null) {
                return "{$access} ?? null";
            }
            $innerCast = $this->castExpression($inner, $access, $definition, $direction);
            return "(!isset({$access})) ? null : {$innerCast}";
        }

        if ($type->isArray()) {
            $inner = $type->getInnerType();
            if ($inner === null) {
                return "{$access} ?? []";
            }
            if ($inner->isReference()) {
                $modelName = str_replace('/', '', $inner->getName() ?? '');
                $mapMethod = $direction === 'localToInterface' ? 'localToInterface' : 'interfaceToLocal';
                return "array_map(fn(\$v) => {$modelName}Map::{$mapMethod}(\$v), {$access} ?? [])";
            }
            $elementCast = $this->castExpression($inner, '$v', $definition, $direction);
            return "array_map(fn(\$v) => {$elementCast}, {$access} ?? [])";
        }

        if ($type->isReference()) {
            $modelName = str_replace('/', '', $type->getName() ?? '');
            $mapMethod = $direction === 'localToInterface' ? 'localToInterface' : 'interfaceToLocal';
            return "{$modelName}Map::{$mapMethod}({$access})";
        }

        if ($type->isSimple()) {
            $name = $type->getName();
            return match ($name) {
                'int' => "(int) ({$access} ?? null)",
                'float' => "(float) ({$access} ?? null)",
                'string' => "(string) ({$access} ?? null)",
                'bool' => "(bool) ({$access} ?? null)",
                default => "{$access} ?? null",
            };
        }

        if ($type->isAlias()) {
            $alias = $definition->getTypeAlias($type->getName() ?? '');
            $phpType = $alias?->getLangType('php');
            if ($phpType !== null) {
                return match ($phpType) {
                    'int' => "(int) ({$access} ?? null)",
                    'float' => "(float) ({$access} ?? null)",
                    'string' => "(string) ({$access} ?? null)",
                    'bool' => "(bool) ({$access} ?? null)",
                    default => "{$access} ?? null",
                };
            }
            $resolvedType = $definition->getTypeAliasResolvedType($type->getName() ?? '');
            if ($resolvedType !== null) {
                return $this->castExpression($resolvedType, $access, $definition, $direction);
            }
        }

        return "{$access} ?? null";
    }
}
