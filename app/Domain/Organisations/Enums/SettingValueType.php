<?php

namespace App\Domain\Organisations\Enums;

enum SettingValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Decimal = 'decimal';
    case Json = 'json';
}
