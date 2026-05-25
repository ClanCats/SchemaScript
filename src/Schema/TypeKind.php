<?php

namespace ClanCats\SchemaScript\Schema;

enum TypeKind: string
{
    case Simple = 'simple';
    case Reference = 'reference';
    case Alias = 'alias';
    case Array = 'array';
    case Nullable = 'nullable';
    case Union = 'union';
    case StringLiteral = 'string_literal';
}
