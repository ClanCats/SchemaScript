<?php

namespace ClanCats\SchemaScript;

use ClanCats\SchemaScript\Util\StringEscapeParser;

class Token
{
    protected TokenType $type;

    /**
     * @var mixed
     */
    protected $value;

    protected int $line = 0;

    protected int $column = 0;

    protected ?string $filename;

    private bool $valueCached = false;

    /**
     * @var mixed
     */
    private $cachedValue;

    /**
     * @param mixed $value
     */
    public function __construct(int $line, TokenType $type, $value, ?string $filename = null, int $column = 0)
    {
        $this->line = $line;
        $this->column = $column;
        $this->type = $type;
        $this->value = $value;
        $this->filename = $filename;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function getType(): TokenType
    {
        return $this->type;
    }

    public function isType(TokenType $type): bool
    {
        return $this->type === $type;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        if ($this->valueCached) {
            return $this->cachedValue;
        }

        $value = $this->value;

        switch ($this->type) {
            case TokenType::String:
                $value = StringEscapeParser::parse(substr($value, 1, -1));
                break;

            case TokenType::Number:
                $value = $value + 0;
                break;

            case TokenType::MetadataKey:
                $value = substr($value, 1, -1);
                break;
        }

        $this->cachedValue = $value;
        $this->valueCached = true;

        return $value;
    }

    public function isValue(): bool
    {
        return
            $this->type === TokenType::String ||
            $this->type === TokenType::Number;
    }
}
