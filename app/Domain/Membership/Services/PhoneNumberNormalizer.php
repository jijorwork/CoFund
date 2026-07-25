<?php

namespace App\Domain\Membership\Services;

use Illuminate\Support\Str;

class PhoneNumberNormalizer
{
    public function normalize(?string $phone): ?string
    {
        if ($phone === null || Str::of($phone)->trim()->isEmpty()) {
            return null;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $phone);

        if ($normalized === null) {
            return null;
        }

        if (str_starts_with($normalized, '00')) {
            return '+'.substr($normalized, 2);
        }

        if (str_starts_with($normalized, '0')) {
            return '+233'.substr($normalized, 1);
        }

        if (! str_starts_with($normalized, '+')) {
            return '+'.$normalized;
        }

        return $normalized;
    }
}
