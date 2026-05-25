<?php

namespace ClanCats\SchemaScript\Util;

class StringEscapeParser
{
    public static function parse(string $raw): string
    {
        $result = '';
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            if ($raw[$i] === '\\' && $i + 1 < $len) {
                $next = $raw[$i + 1];
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
                    $result .= $raw[$i];
                }
            } else {
                $result .= $raw[$i];
            }
        }
        return $result;
    }
}
