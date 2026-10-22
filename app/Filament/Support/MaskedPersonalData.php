<?php

namespace App\Filament\Support;

use Illuminate\Support\Str;

final class MaskedPersonalData
{
    public static function phone(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        return Str::mask($value, '•', 3, max(0, Str::length($value) - 6));
    }

    public static function email(mixed $value): string
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $value, 2);

        return Str::mask($local, '•', 1).'@'.$domain;
    }

    public static function identifier(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        return Str::mask($value, '•', 0, max(0, Str::length($value) - 4));
    }
}
