<?php

namespace ClanCats\SchemaScript;

enum TokenType: int
{
    case String = 0;
    case Number = 1;
    case Identifier = 2;
    case MetadataKey = 3;
    case Annotation = 4;
    case KeywordNs = 5;
    case KeywordConst = 6;
    case ScopeOpen = 7;
    case ScopeClose = 8;
    case ParenOpen = 9;
    case ParenClose = 10;
    case Colon = 11;
    case Equal = 12;
    case Question = 13;
    case Pipe = 14;
    case Comma = 15;
    case DoubleColon = 16;
    case ArraySuffix = 17;
    case Comment = 18;
    case Line = 19;
    case Space = 20;
    case KeywordImport = 21;
    case Slash = 22;
    case KeywordPub = 23;
}
