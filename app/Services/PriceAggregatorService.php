<?php

namespace App\Services;

class PriceAggregatorService
{
    /**
     * Sadə min / max / arifmetik orta (Wolt və ümumi məhsullar).
     *
     * @param  list<float|int|string|null>  $prices
     * @return array{min: float, max: float, avg: float, count: int}
     */
    public function calculateWolt(array $prices): array
    {
        return $this->calculateFromBrands($prices);
    }

    /**
     * Hər brand üçün bir qiymət (məs. Wolt multi-brand): min / max / brands üzrə sadə orta.
     *
     * @param  list<float|int|string|null>  $brandPrices
     * @return array{min: float, max: float, avg: float, count: int}
     */
    public function calculateFromBrands(array $brandPrices): array
    {
        $values = $this->normalizePositiveNumericList($brandPrices);
        $n = count($values);
        if ($n === 0) {
            return ['min' => 0.0, 'max' => 0.0, 'avg' => 0.0, 'count' => 0];
        }

        return [
            'min' => (float) min($values),
            'max' => (float) max($values),
            'avg' => round(array_sum($values) / $n, 4),
            'count' => $n,
        ];
    }

    /**
     * Trimmed mean: sıralanmış dəyərlərdən hər tərəfdən ~5% kəsilir (ümumi ~10%).
     *
     * @param  list<float|int|string|null>  $values
     * @return array{min: float, max: float, avg: float, count: int}
     */
    public function calculateBina(array $values): array
    {
        $values = $this->normalizePositiveNumericList($values);
        $n = count($values);
        if ($n === 0) {
            return ['min' => 0.0, 'max' => 0.0, 'avg' => 0.0, 'count' => 0];
        }

        sort($values);
        $trimEach = (int) floor($n * 0.05);
        $trimEach = max(0, min($trimEach, (int) floor(($n - 1) / 2)));
        $trimmed = array_slice($values, $trimEach, $n - 2 * $trimEach);
        if ($trimmed === []) {
            $trimmed = $values;
        }

        $avg = array_sum($trimmed) / count($trimmed);

        return [
            'min' => (float) min($values),
            'max' => (float) max($values),
            'avg' => round($avg, 4),
            'count' => $n,
        ];
    }

    /**
     * @param  list<float|int|string|null>  $raw
     * @return list<float>
     */
    private function normalizePositiveNumericList(array $raw): array
    {
        $out = [];
        foreach ($raw as $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (is_numeric($v)) {
                $f = (float) $v;
                if ($f > 0) {
                    $out[] = $f;
                }
            }
        }

        return $out;
    }
}
