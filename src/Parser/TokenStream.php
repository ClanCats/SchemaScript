<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;
use ClanCats\SchemaScript\Exception\ParserException;

class TokenStream
{
    /**
     * @var array<T>
     */
    private array $tokens;

    private int $index = 0;

    private int $tokenCount;

    /**
     * @param array<T> $tokens
     */
    public function __construct(array $tokens)
    {
        $this->tokens = array_values($tokens);
        $this->tokenCount = count($this->tokens);
    }

    public function current(): T
    {
        if (!isset($this->tokens[$this->index])) {
            throw new ParserException("Unexpected end of token stream.");
        }

        return $this->tokens[$this->index];
    }

    public function peek(int $offset = 1): ?T
    {
        return $this->tokens[$this->index + $offset] ?? null;
    }

    public function skip(int $times = 1): void
    {
        $this->index += $times;
    }

    /**
     * @param array<TokenType> $types
     */
    public function skipOfType(array $types): void
    {
        while (!$this->isDone() && in_array($this->current()->getType(), $types, true)) {
            $this->skip();
        }
    }

    /** @phpstan-impure */
    public function isDone(): bool
    {
        return $this->index >= $this->tokenCount;
    }

    public function expectType(TokenType $type): T
    {
        $token = $this->current();

        if (!$token->isType($type)) {
            throw self::unexpectedTokenError($token);
        }

        return $token;
    }

    /**
     * @return array<T>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getTokenCount(): int
    {
        return $this->tokenCount;
    }

    /**
     * @return array<T>
     */
    public function remaining(bool $skip = false): array
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
    public function until(TokenType $type): array
    {
        $tokens = [];

        while (!$this->isDone() && !$this->current()->isType($type)) {
            $tokens[] = $this->current();
            $this->skip();
        }

        return $tokens;
    }

    /**
     * @return array<T>
     */
    public function untilClosingScope(): array
    {
        $openToken = $this->current();
        if ($openToken->isType(TokenType::ScopeOpen)) {
            $this->skip();
        }

        $tokens = [];
        $depth = 1;

        while (!$this->isDone()) {
            $token = $this->current();

            if ($token->isType(TokenType::ScopeOpen)) {
                $depth++;
            } elseif ($token->isType(TokenType::ScopeClose)) {
                $depth--;
                if ($depth === 0) {
                    $this->skip();
                    break;
                }
            }

            $tokens[] = $token;
            $this->skip();
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
     * @return array<string>
     */
    public function parseDoubleColonSeparatedIdentifiers(string $firstPart): array
    {
        $parts = [$firstPart];
        while (!$this->isDone() && $this->current()->isType(TokenType::DoubleColon)) {
            $this->skip();
            $parts[] = $this->expectType(TokenType::Identifier)->getValue();
            $this->skip();
        }
        return $parts;
    }

    public static function unexpectedTokenError(T $token): ParserException
    {
        $filename = $token->getFilename() ?? 'unknown';
        $e = new ParserException(
            sprintf(
                'Unexpected token "%s" (%s) on line %d, column %d in file %s',
                $token->getValue(),
                $token->getType()->name,
                $token->getLine(),
                $token->getColumn(),
                $filename
            )
        );
        $e->setSourceContext($token->getLine(), $token->getColumn(), $token->getFilename(), null, strlen((string) $token->getValue()));
        return $e;
    }

    public static function parsingError(string $message, ?T $token = null): ParserException
    {
        if ($token !== null) {
            $filename = $token->getFilename() ?? 'unknown';
            $e = new ParserException(
                sprintf('%s on line %d, column %d in file %s', $message, $token->getLine(), $token->getColumn(), $filename)
            );
            $e->setSourceContext($token->getLine(), $token->getColumn(), $token->getFilename(), null, strlen((string) $token->getValue()));
            return $e;
        }

        return new ParserException($message);
    }
}
