<?php

namespace ClanCats\SchemaScript;

class Token
{
    /**
     * The type of this token.
     *
     * @var int
     */
    protected int $type;

    /**
     * The value of this token.
     *
     * @var mixed
     */
    protected $value;

    /**
     * The line this token has been found in the code.
     *
     * @var int
     */
    protected int $line = 0;

    /**
     * The column this token starts at (1-based).
     *
     * @var int
     */
    protected int $column = 0;

    /**
     * The tokens filename
     *
     * @var string|null
     */
    protected ?string $filename;

    private bool $valueCached = false;

    /**
     * @var mixed
     */
    private $cachedValue;

    /**
     * The token types
     */
    const TOKEN_STRING = 0;
    const TOKEN_NUMBER = 1;
    const TOKEN_IDENTIFIER = 2;
    const TOKEN_METADATA_KEY = 3;
    const TOKEN_ANNOTATION = 4;
    const TOKEN_KEYWORD_NS = 5;
    const TOKEN_KEYWORD_CONST = 6;
    const TOKEN_SCOPE_OPEN = 7;
    const TOKEN_SCOPE_CLOSE = 8;
    const TOKEN_PAREN_OPEN = 9;
    const TOKEN_PAREN_CLOSE = 10;
    const TOKEN_COLON = 11;
    const TOKEN_EQUAL = 12;
    const TOKEN_QUESTION = 13;
    const TOKEN_PIPE = 14;
    const TOKEN_COMMA = 15;
    const TOKEN_DOUBLE_COLON = 16;
    const TOKEN_ARRAY_SUFFIX = 17;
    const TOKEN_COMMENT = 18;
    const TOKEN_LINE = 19;
    const TOKEN_SPACE = 20;
    const TOKEN_KEYWORD_IMPORT = 21;
    const TOKEN_SLASH = 22;
    const TOKEN_KEYWORD_PUB = 23;

    /**
     * The constructor
     *
     * @param int           $line The line the token is on.
     * @param int           $type The type of the token represented by an int.
     * @param mixed         $value The Value of the token.
     * @param string|null   $filename The filename the token belongs to.
     * @return void
     */
    public function __construct(int $line, int $type, $value, ?string $filename = null, int $column = 0)
    {
        $this->line = $line;
        $this->column = $column;
        $this->type = $type;
        $this->value = $value;
        $this->filename = $filename;
    }

    /**
     * Get the line of the token.
     *
     * @return int
     */
    public function getLine() : int
    {
        return $this->line;
    }

    public function getColumn() : int
    {
        return $this->column;
    }

    /**
     * Get the filename of the token.
     *
     * @return string|null
     */
    public function getFilename() : ?string
    {
        return $this->filename;
    }

    /**
     * Get the type of the token represented as int.
     *
     * @return int
     */
    public function getType() : int
    {
        return $this->type;
    }

    /**
     * Is the token the given type?
     *
     * @return bool
     */
    public function isType(int $type) : bool
    {
        return $this->type === $type;
    }

    /**
     * Get the tokens value as php native type.
     *
     * @return mixed
     */
    public function getValue()
    {
        if ($this->valueCached) {
            return $this->cachedValue;
        }

        $value = $this->value;

        switch ($this->type)
        {
            case self::TOKEN_STRING:
                $value = substr($value, 1, -1);
                $result = '';
                $len = strlen($value);
                for ($i = 0; $i < $len; $i++) {
                    if ($value[$i] === '\\' && $i + 1 < $len) {
                        $next = $value[$i + 1];
                        if ($next === '\\') {
                            $result .= '\\';
                            $i++;
                        } elseif ($next === "'") {
                            $result .= "'";
                            $i++;
                        } elseif ($next === '"') {
                            $result .= '"';
                            $i++;
                        } else {
                            $result .= $value[$i];
                        }
                    } else {
                        $result .= $value[$i];
                    }
                }
                $value = $result;
                break;

            case self::TOKEN_NUMBER:
                $value = $value + 0;
                break;

            case self::TOKEN_METADATA_KEY:
                $value = substr($value, 1, -1);
                break;
        }

        $this->cachedValue = $value;
        $this->valueCached = true;

        return $value;
    }

    /**
     * Is this a value token?
     *
     * @return bool
     */
    public function isValue() : bool
    {
        return
            $this->type === self::TOKEN_STRING ||
            $this->type === self::TOKEN_NUMBER;
    }
}
