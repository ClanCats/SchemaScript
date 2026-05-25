<?php

namespace ClanCats\SchemaScript;

use ClanCats\SchemaScript\Exception\SchemaNamespaceException;

class SchemaNamespace
{
    /**
     * @var array<string, string>
     */
    private array $paths = [];

    public function importDirectory(string $dir, string $prefix = '', string $ext = '.scsc'): void
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);

        if (!is_dir($dir)) {
            throw new SchemaNamespaceException(sprintf('Import directory does not exist: "%s"', $dir));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();
            if (!str_ends_with($filename, $ext)) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($dir) + 1);
            $name = substr($relativePath, 0, -strlen($ext));
            $name = str_replace(DIRECTORY_SEPARATOR, '/', $name);

            if ($prefix !== '') {
                $name = $prefix . '/' . $name;
            }

            $this->paths[$name] = $file->getRealPath();
        }
    }

    public function importStdlib(): void
    {
        $this->importDirectory(__DIR__ . '/stdlib', 'scsc');
    }

    public function has(string $name): bool
    {
        return isset($this->paths[$name]);
    }

    public function getPath(string $name): string
    {
        if (!isset($this->paths[$name])) {
            throw new SchemaNamespaceException(sprintf('Unknown import: "%s"', $name));
        }

        return $this->paths[$name];
    }

    public function getCode(string $name): string
    {
        $content = file_get_contents($this->getPath($name));
        if ($content === false) {
            throw new SchemaNamespaceException(sprintf('Could not read file for import: "%s"', $name));
        }
        return $content;
    }
}
