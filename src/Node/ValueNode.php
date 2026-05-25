<?php

namespace ClanCats\SchemaScript\Node;

use ClanCats\SchemaScript\Token;
use ClanCats\SchemaScript\TokenType;

class ValueNode extends BaseNode
{
    const TYPE_STRING = 0;
    const TYPE_NUMBER = 1;
    const TYPE_IDENTIFIER = 2;
    const TYPE_BOOLEAN = 3;

    protected int $type;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @param mixed $value
     */
    public function __construct(int $type, $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    public static function fromToken(Token $token): self
    {
        $type = $token->isType(TokenType::Number) ? self::TYPE_NUMBER : self::TYPE_STRING;
        return new self($type, $token->getValue());
    }

    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitValue($this);
    }
}
