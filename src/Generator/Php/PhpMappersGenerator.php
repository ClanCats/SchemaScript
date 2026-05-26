<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Generator\GeneratorInterface;
use ClanCats\SchemaScript\Generator\GeneratorResult;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\ResolvedDefinition;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Workbench\Str;

class PhpMappersGenerator implements GeneratorInterface
{
    public function getName(): string
    {
        return 'php.mappers';
    }

    public function getDescription(): string
    {
        return 'Generates PHP mapper classes with fromArray/toArray methods';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function generate(Definition $definition, array $options = []): GeneratorResult
    {
        $result = new GeneratorResult();
        $includeComments = (bool) ($options['include_comments'] ?? false);
        $namespace = $options['namespace'] ?? null;

        $ctx = new PhpMappersContext();
        $ctx->mapFrom = $options['map_from'] ?? null;
        $ctx->mapTo = $options['map_to'] ?? null;

        $mapFrom = $ctx->mapFrom ?? 'self';
        $mapTo = $ctx->mapTo ?? 'self';
        $resolved = new ResolvedDefinition($definition, [$mapFrom, $mapTo]);

        foreach ($resolved->getPublicStructAliasMap() as $structName => $aliasName) {
            $ctx->pubStructToMapper[$structName] = Str::toPascalCase($aliasName);
        }

        foreach ($ctx->pubStructToMapper as $structName => $mapperName) {
            $struct = $resolved->getStruct($structName);
            if ($struct !== null) {
                $code = $this->generateMapper($ctx, $struct, $resolved, $namespace, $includeComments, $mapperName);
                $result->addFile($mapperName . 'Mapper.php', $code);
            }
        }

        foreach ($resolved->getModels() as $struct) {
            $code = $this->generateMapper($ctx, $struct, $resolved, $namespace, $includeComments);
            $result->addFile($struct->getName() . 'Mapper.php', $code);
        }

        return $result;
    }

    private function generateMapper(PhpMappersContext $ctx, Struct $struct, ResolvedDefinition $resolved, ?string $namespace, bool $includeComments, ?string $nameOverride = null): string
    {
        $name = $nameOverride ?? str_replace('/', '', $struct->getName());
        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        if ($namespace !== null) {
            $lines[] = 'namespace ' . rtrim($namespace, '\\') . ';';
            $lines[] = '';
        }
        $lines[] = "class {$name}Mapper";
        $lines[] = '{';
        $lines[] = $this->generateMethod($ctx, 'fromArray', $struct, $resolved, $includeComments);
        $lines[] = '';
        $lines[] = $this->generateMethod($ctx, 'toArray', $struct, $resolved, $includeComments);
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function generateMethod(PhpMappersContext $ctx, string $direction, Struct $struct, ResolvedDefinition $resolved, bool $includeComments): string
    {
        $lines = [];
        $lines[] = "    public static function {$direction}(array \$data): array";
        $lines[] = '    {';
        $lines[] = '        $result = [];';

        $mapFrom = $ctx->mapFrom ?? 'self';
        $mapTo = $ctx->mapTo ?? 'self';

        foreach ($struct->getProperties() as $prop) {
            $lines[] = '';
            if ($includeComments && $prop->getComment() !== null) {
                foreach (explode("\n", $prop->getComment()) as $commentLine) {
                    $lines[] = '        // ' . $commentLine;
                }
            }

            [$readKey, $writeKey] = $resolved->getResolvedReadWriteKeys(
                $struct->getName(), $prop->getName(), $mapFrom, $mapTo, $direction === 'fromArray',
            );

            $varAccess = "\$data['{$readKey}']";
            try {
                $cast = $prop->getType()->accept($this->createCastVisitor($ctx, $resolved, $direction, $varAccess));
            } catch (GeneratorException $e) {
                throw (new GeneratorException($e->getMessage(), 0, $e))
                    ->setStructContext($struct->getName(), $prop->getName());
            }

            if ($prop->isOptional()) {
                $lines[] = "        if (array_key_exists('{$readKey}', \$data)) {";
                $lines[] = '            $result[\'' . $writeKey . '\'] = ' . $cast . ';';
                $lines[] = '        }';
            } else {
                $lines[] = '        $result[\'' . $writeKey . '\'] = ' . $cast . ';';
            }
        }

        $lines[] = '';
        $lines[] = '        return $result;';
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    private function createCastVisitor(PhpMappersContext $ctx, ResolvedDefinition $resolved, string $direction, string $access): PhpMappersCastVisitor
    {
        return new PhpMappersCastVisitor(
            $ctx,
            $resolved,
            $direction,
            $access,
            fn(Struct $struct, string $inlineAccess) => $this->generateInlineCast($ctx, $struct, $inlineAccess, $resolved, $direction),
        );
    }

    private function generateInlineCast(PhpMappersContext $ctx, Struct $struct, string $access, ResolvedDefinition $resolved, string $direction): string
    {
        $mapFrom = $ctx->mapFrom ?? 'self';
        $mapTo = $ctx->mapTo ?? 'self';

        $entries = [];
        foreach ($struct->getProperties() as $prop) {
            [$readKey, $writeKey] = $resolved->getResolvedReadWriteKeys(
                $struct->getName(), $prop->getName(), $mapFrom, $mapTo, $direction === 'fromArray',
            );

            $fieldAccess = $access . "['{$readKey}']";
            $cast = $prop->getType()->accept($this->createCastVisitor($ctx, $resolved, $direction, $fieldAccess));
            $entries[] = "'{$writeKey}' => {$cast}";
        }
        return '[' . implode(', ', $entries) . ']';
    }

}
