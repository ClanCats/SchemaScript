<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Generator\GeneratorInterface;
use ClanCats\SchemaScript\Generator\GeneratorResult;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\ResolvedDefinition;
use ClanCats\SchemaScript\Schema\Struct;

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

        $resolved = new ResolvedDefinition($definition, [$ctx->mapFrom, $ctx->mapTo]);

        foreach ($resolved->getModels() as $struct) {
            $code = $this->generateMapClass($ctx, $struct, $resolved, $namespace);
            $result->addFile($struct->getName() . 'Map.php', $code);
        }

        return $result;
    }

    private function generateMapClass(PhpSamgContext $ctx, Struct $struct, ResolvedDefinition $resolved, ?string $namespace): string
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
            $this->generateFullMapping($ctx, $struct, $resolved, 'localToInterface'),
            $this->generateFullMapping($ctx, $struct, $resolved, 'localToInterfaceMapOnly'),
            $this->generateFullMapping($ctx, $struct, $resolved, 'interfaceToLocal'),
            $this->generateFullMapping($ctx, $struct, $resolved, 'interfaceToLocalMapOnly'),
            $this->generatePartialMapping($ctx, $struct, $resolved, 'localToPartialInterface'),
            $this->generatePartialMapping($ctx, $struct, $resolved, 'localToPartialInterfaceMapOnly'),
            $this->generatePartialMapping($ctx, $struct, $resolved, 'interfaceToPartialLocal'),
            $this->generatePartialMapping($ctx, $struct, $resolved, 'interfaceToPartialLocalMapOnly'),
            $this->generateCastMethod($ctx, $struct, $resolved, 'castLocal'),
            $this->generateCastMethod($ctx, $struct, $resolved, 'castInterface'),
        ];

        $lines[] = implode("\n\n", $methods);
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function generateFullMapping(PhpSamgContext $ctx, Struct $struct, ResolvedDefinition $resolved, string $methodName): string
    {
        $direction = $this->resolveDirection($methodName);
        $withCast = !str_ends_with($methodName, 'MapOnly');
        $mapFrom = $ctx->mapFrom ?? 'self';
        $mapTo = $ctx->mapTo ?? 'api';

        $lines = [];
        $lines[] = "    /**";
        $lines[] = "     * @param array<mixed> \$array";
        $lines[] = "     * @return array<mixed>";
        $lines[] = "     */";
        $lines[] = "    public static function {$methodName}(array \$array): array";
        $lines[] = '    {';
        $lines[] = '        return [';

        foreach ($struct->getProperties() as $prop) {
            [$readKey, $writeKey] = $resolved->getResolvedReadWriteKeys(
                $struct->getName(), $prop->getName(), $mapFrom, $mapTo, $direction !== 'localToInterface',
            );
            $access = "\$array['{$readKey}']";

            if ($withCast) {
                $expr = $prop->getType()->accept(new PhpSamgCastVisitor($resolved, $direction, $access));
            } else {
                $expr = "{$access} ?? null";
            }

            $lines[] = "            '{$writeKey}' => {$expr},";
        }

        $lines[] = '        ];';
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    private function generatePartialMapping(PhpSamgContext $ctx, Struct $struct, ResolvedDefinition $resolved, string $methodName): string
    {
        $direction = $this->resolveDirection($methodName);
        $withCast = !str_ends_with($methodName, 'MapOnly');
        $mapFrom = $ctx->mapFrom ?? 'self';
        $mapTo = $ctx->mapTo ?? 'api';

        $lines = [];
        $lines[] = "    /**";
        $lines[] = "     * @param array<mixed> \$array";
        $lines[] = "     * @return array<mixed>";
        $lines[] = "     */";
        $lines[] = "    public static function {$methodName}(array \$array): array";
        $lines[] = '    {';
        $lines[] = '        $buffer = [];';

        foreach ($struct->getProperties() as $prop) {
            [$readKey, $writeKey] = $resolved->getResolvedReadWriteKeys(
                $struct->getName(), $prop->getName(), $mapFrom, $mapTo, $direction !== 'localToInterface',
            );
            $access = "\$array['{$readKey}']";

            if ($withCast) {
                if ($prop->getType()->isNullable()) {
                    $inner = $prop->getType()->getInnerType();
                    $innerCast = $inner !== null ? $inner->accept(new PhpSamgCastVisitor($resolved, $direction, $access)) : $access;
                    $expr = "({$access} === null) ? null : {$innerCast}";
                } else {
                    $expr = $prop->getType()->accept(new PhpSamgCastVisitor($resolved, $direction, $access));
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

    private function generateCastMethod(PhpSamgContext $ctx, Struct $struct, ResolvedDefinition $resolved, string $methodName): string
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
            $key = $resolved->getResolvedKey($struct->getName(), $prop->getName(), $mapName);
            $access = "\$array['{$key}']";

            $expr = $prop->getType()->accept(new PhpSamgCastVisitor($resolved, 'localToInterface', $access));
            $lines[] = "        \$array['{$key}'] = {$expr};";
        }

        $lines[] = '    }';

        return implode("\n", $lines);
    }

    private function resolveDirection(string $methodName): string
    {
        if (str_starts_with($methodName, 'localTo')) {
            return 'localToInterface';
        }
        return 'interfaceToLocal';
    }

}
