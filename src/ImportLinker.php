<?php

namespace ClanCats\SchemaScript;

use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Parser\ScopeParser;
use ClanCats\SchemaScript\Exception\EvaluatorException;

class ImportLinker
{
    private const MAX_IMPORT_DEPTH = 50;

    private ?SchemaNamespace $schemaNamespace;

    /**
     * @var array<string, true>
     */
    private array $importedFiles = [];

    /**
     * @var array<string, string>
     */
    private array $sourceCodeMap = [];

    public function __construct(?SchemaNamespace $schemaNamespace)
    {
        $this->schemaNamespace = $schemaNamespace;
    }

    public function link(ScopeNode $scope, ?string $sourceCode = null, ?string $filename = null): LinkedScope
    {
        $this->importedFiles = [];
        $this->sourceCodeMap = [];

        if ($sourceCode !== null) {
            $this->sourceCodeMap[$filename ?? ''] = $sourceCode;
        }

        $this->resolveImports($scope, []);

        return new LinkedScope($scope, $this->sourceCodeMap);
    }

    /**
     * @param array<int, string> $importStack
     */
    private function resolveImports(ScopeNode $scope, array $importStack): void
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

            if (in_array($absPath, $importStack, true)) {
                $idx = array_search($absPath, $importStack, true);
                $cycle = array_slice($importStack, $idx !== false ? $idx : 0);
                $cycle[] = $absPath;
                throw new EvaluatorException(sprintf(
                    'Circular import detected: %s',
                    implode(' -> ', array_map('basename', $cycle))
                ));
            }

            if (count($importStack) >= self::MAX_IMPORT_DEPTH) {
                throw new EvaluatorException(sprintf(
                    'Maximum import depth exceeded (%d). Check for circular imports.',
                    self::MAX_IMPORT_DEPTH
                ));
            }

            if (isset($this->importedFiles[$absPath])) {
                continue;
            }
            $this->importedFiles[$absPath] = true;

            $code = $this->schemaNamespace->getCode($path);
            $this->sourceCodeMap[$path] = $code;
            $tokens = (new Lexer($code, $path))->tokens();
            /** @var ScopeNode $importedScope */
            $importedScope = (new ScopeParser($tokens))->parse();

            $this->resolveImports($importedScope, [...$importStack, $absPath]);
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
        foreach ($source->getConstants() as $constant) {
            $target->addConstant($constant);
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
