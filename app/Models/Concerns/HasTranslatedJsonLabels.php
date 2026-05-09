<?php

namespace App\Models\Concerns;

trait HasTranslatedJsonLabels
{
    public function getNameEnAttribute(): ?string
    {
        $n = $this->name;
        if (! is_array($n)) {
            return null;
        }

        $en = $n['en'] ?? null;
        if (is_string($en)) {
            $t = trim($en);
            if ($t !== '') {
                return $t;
            }
        }

        $az = $n['az'] ?? null;
        if (is_string($az)) {
            $t = trim($az);
            if ($t !== '') {
                return $t;
            }
        }

        return null;
    }
}
