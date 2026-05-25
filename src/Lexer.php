<?php

namespace ClanCats\SchemaScript;

use ClanCats\SchemaScript\Exception\LexerException;

class Lexer
{
    protected string $code;

    protected string $originalCode;

    protected int $length = 0;

    protected int $offset = 0;

    protected int $line = 0;

    protected int $column = 0;

    protected string $filename = 'unknown';

    /**
     * @var array<string, TokenType>
     */
    protected array $tokenMap =
    [
        // metadata key: [version], [map:local], [types], [php.mappers]
        "/\\G\\[([\\w:.]+)\\]/" => TokenType::MetadataKey,

        // numbers
        "/\\G\\d+(\\.\\d+)?/" => TokenType::Number,

        // comments
        "/\\G\\/\\/.*/" => TokenType::Comment,

        // annotations
        "/\\G@[\\w.]+/" => TokenType::Annotation,

        // markup
        "/\\G(\\r\\n|\\n|\\r)/" => TokenType::Line,
        "/\\G[ \\t]+/" => TokenType::Space,

        // keywords
        "/\\Gns(?=[\\s{])/" => TokenType::KeywordNs,
        "/\\Gconst(?=[\\s\\n])/" => TokenType::KeywordConst,
        "/\\Gimport(?=[\\s\\n])/" => TokenType::KeywordImport,
        "/\\Gpub(?=[\\s\\n])/" => TokenType::KeywordPub,

        // multi-char symbols
        "/\\G\\[\\]/" => TokenType::ArraySuffix,
        "/\\G::/" => TokenType::DoubleColon,

        // single-char symbols
        "/\\G\\{/" => TokenType::ScopeOpen,
        "/\\G\\}/" => TokenType::ScopeClose,
        "/\\G\\(/" => TokenType::ParenOpen,
        "/\\G\\)/" => TokenType::ParenClose,
        "/\\G:/" => TokenType::Colon,
        "/\\G=/" => TokenType::Equal,
        "/\\G\\?/" => TokenType::Question,
        "/\\G\\|/" => TokenType::Pipe,
        "/\\G,/" => TokenType::Comma,
        "/\\G\\//" => TokenType::Slash,

        // identifiers (must be last)
        "/\\G[\\w]+/" => TokenType::Identifier,
    ];

    public function __construct(string $code, ?string $filename = null)
    {
        $this->originalCode = $code;
        $this->code = $code;
        $this->length = strlen($this->code);

        if ($filename) {
            $this->filename = $filename;
        }
    }

    public function code() : string
    {
        return $this->code;
    }

    public function getOriginalCode(): string
    {
        return $this->originalCode;
    }

    /**
     * @throws LexerException
     * @return Token|false
     */
    protected function next()
    {
        if ($this->offset >= $this->length)
        {
            return false;
        }

        $char = $this->code[$this->offset];
        if ($char === '"' || $char === "'")
        {
            $string = $char;
            $startLine = $this->line;
            $startColumn = $this->column;
            $this->offset++;
            $this->column++;

            $closed = false;
            while ($this->offset < $this->length)
            {
                if ($this->code[$this->offset] === $char && self::isUnescapedQuote($this->code, $this->offset)) {
                    $string .= $char;
                    $closed = true;
                    break;
                }

                if ($this->code[$this->offset] === "\n") {
                    $this->line++;
                    $this->column = 0;
                } else {
                    $this->column++;
                }

                $string .= $this->code[$this->offset];
                $this->offset++;
            }

            if (!$closed) {
                $e = new LexerException(sprintf('Unterminated string literal on line %d in file %s', $startLine + 1, $this->filename));
                $e->setSourceContext($startLine + 1, $startColumn + 1, $this->filename, $this->originalCode, 1);
                throw $e;
            }

            $this->offset++;
            $this->column++;

            return new Token($startLine + 1, TokenType::String, $string, $this->filename, $startColumn + 1);
        }

        foreach ($this->tokenMap as $regex => $token)
        {
            if (preg_match($regex, $this->code, $matches, 0, $this->offset))
            {
                $tokenColumn = $this->column;
                $matchLen = strlen($matches[0]);

                if ($token === TokenType::Line) {
                    $this->line++;
                    $this->column = 0;
                } elseif ($token === TokenType::Comment) {
                    $newlines = substr_count($matches[0], "\n");
                    if ($newlines > 0) {
                        $this->line += $newlines;
                        $lastNewline = strrpos($matches[0], "\n");
                        $this->column = $matchLen - $lastNewline - 1;
                    } else {
                        $this->column += $matchLen;
                    }
                } else {
                    $this->column += $matchLen;
                }

                $this->offset += $matchLen;

                return new Token($this->line + 1, $token, $matches[0], $this->filename, $tokenColumn + 1);
            }
        }

        $e = new LexerException(sprintf('Unexpected character "%s" on line %d in file %s', $this->code[$this->offset], $this->line + 1, $this->filename));
        $e->setSourceContext($this->line + 1, $this->column + 1, $this->filename, $this->originalCode, 1);
        throw $e;
    }

    private static function isUnescapedQuote(string $code, int $pos): bool
    {
        $backslashes = 0;
        $p = $pos - 1;
        while ($p >= 0 && $code[$p] === "\\") {
            $backslashes++;
            $p--;
        }
        return $backslashes % 2 === 0;
    }

    /**
     * @return array<Token>
     */
    public function tokens() : array
    {
        $tokens = [];
        $lastType = null;

        while ($token = $this->next())
        {
            $type = $token->getType();
            if ($type === TokenType::Line && $lastType === TokenType::Line) {
                continue;
            }

            $lastType = $type;
            $tokens[] = $token;
        }

        return $tokens;
    }
}
