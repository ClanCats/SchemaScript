<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;
use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Exception\ParserException;

abstract class SchemaParser
{
    protected TokenStream $stream;

    protected bool $finished = false;

    /**
     * @param array<T> $tokens
     */
    public function __construct(array $tokens)
    {
        $this->stream = new TokenStream($this->prepareTokens($tokens));
        $this->prepare();
    }

    protected function prepare(): void
    {
    }

    /**
     * @return array<T>
     */
    public function getTokens(): array
    {
        return $this->stream->getTokens();
    }

    public function getTokenCount(): int
    {
        return $this->stream->getTokenCount();
    }

    public function getIndex(): int
    {
        return $this->stream->getIndex();
    }

    /**
     * Remove spaces from the token stream. Comments are kept so parsers
     * can attach them to AST nodes (e.g. property comments).
     *
     * @param array<T> $tokens
     * @return array<T>
     */
    protected function prepareTokens(array $tokens): array
    {
        return array_values(array_filter($tokens, function (T $token) {
            return !$token->isType(TokenType::Space);
        }));
    }

    protected function currentToken(): T
    {
        return $this->stream->current();
    }

    protected function nextToken(int $i = 1): ?T
    {
        return $this->stream->peek($i);
    }

    protected function skipToken(int $times = 1): void
    {
        $this->stream->skip($times);
    }

    /**
     * @param array<TokenType> $types
     */
    protected function skipTokenOfType(array $types): void
    {
        $this->stream->skipOfType($types);
    }

    /** @phpstan-impure */
    protected function parserIsDone(): bool
    {
        return $this->stream->isDone();
    }

    /**
     * @return array<T>
     */
    protected function getRemainingTokens(bool $skip = false): array
    {
        return $this->stream->remaining($skip);
    }

    /**
     * @return array<T>
     */
    protected function getTokensUntil(TokenType $type): array
    {
        return $this->stream->until($type);
    }

    /**
     * @return array<T>
     */
    protected function getTokensUntilClosingScope(): array
    {
        return $this->stream->untilClosingScope();
    }

    /**
     * @param class-string<SchemaParser> $parserClassName
     * @param array<T>|null $tokens
     */
    protected function parseChild(string $parserClassName, ?array $tokens = null, bool $skip = true): BaseNode
    {
        if ($tokens === null) {
            $tokens = array_slice($this->stream->getTokens(), $this->stream->getIndex());
        }

        /** @var SchemaParser $parser */
        $parser = new $parserClassName($tokens);
        $node = $parser->parse();

        if ($skip) {
            $this->stream->skip($parser->getIndex());
        }

        return $node;
    }

    /**
     * @return array<string>
     */
    protected function parseDoubleColonSeparatedIdentifiers(string $firstPart): array
    {
        return $this->stream->parseDoubleColonSeparatedIdentifiers($firstPart);
    }

    protected function expectCurrentType(TokenType $type): T
    {
        return $this->stream->expectType($type);
    }

    protected function errorUnexpectedToken(T $token): ParserException
    {
        return TokenStream::unexpectedTokenError($token);
    }

    protected function errorParsing(string $message): ParserException
    {
        if (!$this->stream->isDone()) {
            return $this->errorParsingAt($message, $this->stream->current());
        }

        return new ParserException($message);
    }

    protected function errorParsingAt(string $message, T $token): ParserException
    {
        return TokenStream::parsingError($message, $token);
    }

    protected function capturePosition(BaseNode $node, T $token): void
    {
        $node->setSourcePosition($token->getLine(), $token->getColumn(), $token->getFilename());
    }

    protected function finish(): void
    {
        $this->finished = true;
    }

    abstract protected function next(): void;

    abstract protected function node(): BaseNode;

    public function parse(): BaseNode
    {
        while (!$this->stream->isDone() && !$this->finished) {
            $this->next();
        }

        return $this->node();
    }
}
