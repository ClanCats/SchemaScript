<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Lexer;
use ClanCats\SchemaScript\SchemaNamespace;
use ClanCats\SchemaScript\Parser\ScopeParser;

class ImportResolver
{
    use EvaluatorErrorTrait;

    private const MAX_IMPORT_DEPTH = 50;

    private ?SchemaNamespace $schemaNamespace;

    public function __construct(?SchemaNamespace $schemaNamespace)
    {
        $this->schemaNamespace = $schemaNamespace;
    }

    /**
     * @param array<int, string> $importStack
     */
    public function processImports(ScopeNode $scope, EvaluationContext $context, array $importStack = []): void
    {
        foreach ($scope->getImports() as $import) {
            $path = $import->getPath();

            if ($this->schemaNamespace === null) {
                $this->throwEvaluatorError(sprintf(
                    'Cannot resolve import "%s": no SchemaNamespace provided',
                    $path
                ), $import, $context);
            }

            $absPath = $this->schemaNamespace->getPath($path);

            if (in_array($absPath, $importStack, true)) {
                $idx = array_search($absPath, $importStack, true);
                $cycle = array_slice($importStack, $idx !== false ? $idx : 0);
                $cycle[] = $absPath;
                $this->throwEvaluatorError(sprintf(
                    'Circular import detected: %s',
                    implode(' -> ', array_map('basename', $cycle))
                ), $import, $context);
            }

            if (count($importStack) >= self::MAX_IMPORT_DEPTH) {
                $this->throwEvaluatorError(sprintf(
                    'Maximum import depth exceeded (%d). Check for circular imports.',
                    self::MAX_IMPORT_DEPTH
                ), $import, $context);
            }

            if (isset($context->importedFiles[$absPath])) {
                continue;
            }
            $context->importedFiles[$absPath] = true;

            $code = $this->schemaNamespace->getCode($path);
            $context->sourceCodeMap[$path] = $code;
            $tokens = (new Lexer($code, $path))->tokens();
            /** @var ScopeNode $importedScope */
            $importedScope = (new ScopeParser($tokens))->parse();

            $this->processImports($importedScope, $context, [...$importStack, $absPath]);
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
