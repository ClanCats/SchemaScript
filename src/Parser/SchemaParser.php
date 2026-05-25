<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Exception\ParserException;

abstract class SchemaParser
{
    /**
     * @var array<T>
     */
    protected array $tokens = [];

    protected int $index = 0;

    protected int $tokenCount = 0;

    protected bool $finished = false;

    /**
     * @param array<T> $tokens
     */
    public function __construct(array $tokens)
    {
        $this->setTokens($this->prepareTokens($tokens));
        $this->prepare();
    }

    protected function prepare(): void
    {
    }

    /**
     * @param array<T> $tokens
     */
    protected function setTokens(array $tokens): void
    {
        $this->tokens = array_values($tokens);
        $this->tokenCount = count($this->tokens);
    }

    /**
     * @return array<T>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function getTokenCount(): int
    {
        return $this->tokenCount;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Remove comments and spaces from the token stream.
     *
     * @param array<T> $tokens
     * @return array<T>
     */
    protected function prepareTokens(array $tokens): array
    {
        return array_values(array_filter($tokens, function (T $token) {
            return !$token->isType(T::TOKEN_COMMENT) && !$token->isType(T::TOKEN_SPACE);
        }));
    }

    protected function currentToken(): T
    {
        if (!isset($this->tokens[$this->index])) {
            throw $this->errorParsing("Unexpected end of token stream.");
        }

        return $this->tokens[$this->index];
    }

    protected function nextToken(int $i = 1): ?T
    {
        return $this->tokens[$this->index + $i] ?? null;
    }

    protected function skipToken(int $times = 1): void
    {
        $this->index += $times;
    }

    /**
     * @param array<int> $types
     */
    protected function skipTokenOfType(array $types): void
    {
        while (!$this->parserIsDone() && in_array($this->currentToken()->getType(), $types, true)) {
            $this->skipToken();
        }
    }

    /** @phpstan-impure */
    protected function parserIsDone(): bool
    {
        return $this->index >= $this->tokenCount;
    }

    /**
     * @return array<T>
     */
    protected function getRemainingTokens(bool $skip = false): array
    {
        $tokens = array_slice($this->tokens, $this->index);

        if ($skip) {
            $this->index = $this->tokenCount;
        }

        return $tokens;
    }

    /**
     * @return array<T>
     */
    protected function getTokensUntil(int $type): array
    {
        $tokens = [];

        while (!$this->parserIsDone() && !$this->currentToken()->isType($type)) {
            $tokens[] = $this->currentToken();
            $this->skipToken();
        }

        return $tokens;
    }

    /**
     * @return array<T>
     */
    protected function getTokensUntilClosingScope(): array
    {
        $openToken = $this->currentToken();
        if ($openToken->isType(T::TOKEN_SCOPE_OPEN)) {
            $this->skipToken();
        }

        $tokens = [];
        $depth = 1;

        while (!$this->parserIsDone()) {
            $token = $this->currentToken();

            if ($token->isType(T::TOKEN_SCOPE_OPEN)) {
                $depth++;
            } elseif ($token->isType(T::TOKEN_SCOPE_CLOSE)) {
                $depth--;
                if ($depth === 0) {
                    $this->skipToken();
                    break;
                }
            }

            $tokens[] = $token;
            $this->skipToken();
        }

        if ($depth !== 0) {
            $e = new ParserException(sprintf(
                'Unclosed scope opened on line %d, column %d in file %s',
                $openToken->getLine(),
                $openToken->getColumn(),
                $openToken->getFilename() ?? 'unknown'
            ));
            $e->setSourceContext($openToken->getLine(), $openToken->getColumn(), $openToken->getFilename(), null, 1);
            throw $e;
        }

        return $tokens;
    }

    /**
     * @param class-string<SchemaParser> $parserClassName
     * @param array<T>|null $tokens
     */
    protected function parseChild(string $parserClassName, ?array $tokens = null, bool $skip = true): BaseNode
    {
        if ($tokens === null) {
            $tokens = array_slice($this->tokens, $this->index);
        }

        /** @var SchemaParser $parser */
        $parser = new $parserClassName($tokens);
        $node = $parser->parse();

        if ($skip) {
            $this->skipToken($parser->getIndex());
        }

        return $node;
    }

    protected function expectCurrentType(int $type): T
    {
        $token = $this->currentToken();

        if (!$token->isType($type)) {
            throw $this->errorUnexpectedToken($token);
        }

        return $token;
    }

    protected function errorUnexpectedToken(T $token): ParserException
    {
        $filename = $token->getFilename() ?? 'unknown';
        $e = new ParserException(
            sprintf(
                'Unexpected token "%s" (%d) on line %d, column %d in file %s',
                $token->getValue(),
                $token->getType(),
                $token->getLine(),
                $token->getColumn(),
                $filename
            )
        );
        $e->setSourceContext($token->getLine(), $token->getColumn(), $token->getFilename(), null, strlen((string) $token->getValue()));
        return $e;
    }

    protected function errorParsing(string $message): ParserException
    {
        if (!$this->parserIsDone()) {
            $token = $this->currentToken();
            $filename = $token->getFilename() ?? 'unknown';
            $e = new ParserException(
                sprintf('%s on line %d, column %d in file %s', $message, $token->getLine(), $token->getColumn(), $filename)
            );
            $e->setSourceContext($token->getLine(), $token->getColumn(), $token->getFilename(), null, strlen((string) $token->getValue()));
            return $e;
        }

        return new ParserException($message);
    }

    protected function finish(): void
    {
        $this->finished = true;
    }

    abstract protected function next(): void;

    abstract protected function node(): BaseNode;

    public function parse(): BaseNode
    {
        while (!$this->parserIsDone() && !$this->finished) {
            $this->next();
        }

        return $this->node();
    }
}
