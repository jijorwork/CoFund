<?php

namespace App\Filament\Support;

final class FormatsMoney
{
    public static function format(int $minorUnits, string $currencyCode): string
    {
        $absolute = abs($minorUnits);
        $major = intdiv($absolute, 100);
        $minor = $absolute % 100;
        $sign = $minorUnits < 0 ? '-' : '';

        return sprintf('%s%s %s.%02d', $sign, $currencyCode, number_format($major), $minor);
    }
}
