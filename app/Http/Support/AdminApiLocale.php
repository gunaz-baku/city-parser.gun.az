<?php

namespace App\Http\Support;

use Illuminate\Http\Request;

final class AdminApiLocale
{
    private const ALLOWED = ['az', 'en', 'ru'];

    public static function fromRequest(Request $request): string
    {
        $q = strtolower(trim((string) $request->query('locale', '')));
        if ($q !== '' && in_array($q, self::ALLOWED, true)) {
            return $q;
        }

        $header = (string) $request->header('Accept-Language', '');
        if ($header !== '') {
            $first = strtolower(trim(explode(',', $header, 2)[0]));
            $tag = strtolower(trim(explode(';', $first, 2)[0]));
            $two = strlen($tag) >= 2 ? substr($tag, 0, 2) : '';
            if ($two !== '' && in_array($two, self::ALLOWED, true)) {
                return $two;
            }
        }

        return 'az';
    }
}
