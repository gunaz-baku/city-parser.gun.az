<?php

namespace App\Http\Support;

use App\Models\ParserRunError;

/**
 * JSON payload for grouped parser_run_errors rows (GunAz remote table).
 */
final class ParserRunErrorGroupedRowPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toApiArray(ParserRunError $record, string $locale): array
    {
        $extras = AdminApiPresenter::parserRunErrorExtras($record, $locale);
        $urls = self::splitLines((string) ($record->getAttribute('urls_blob') ?? ''));
        $summary = self::buildSummary($record, $urls);
        $links = $urls !== [] ? implode("\n", $urls) : '—';

        $pos = $record->relationLoaded('position') ? $record->position : null;
        $positionNameEn = null;
        if ($pos !== null && is_string($pos->name_en ?? null) && trim((string) $pos->name_en) !== '') {
            $positionNameEn = trim((string) $pos->name_en);
        }

        return array_merge([
            'id' => (int) $record->getAttribute('id'),
            'parser_run_id' => (int) ($record->parser_run_id ?? 0),
            'position_id' => $record->position_id !== null ? (int) $record->position_id : null,
            'source_id' => $record->source_id !== null ? (int) $record->source_id : null,
            'errors_count' => (int) ($record->getAttribute('errors_count') ?? 0),
            'stages_csv' => (string) ($record->getAttribute('stages_csv') ?? ''),
            'codes_csv' => (string) ($record->getAttribute('codes_csv') ?? ''),
            'position_name_en' => $positionNameEn,
            'source_label' => self::sourceLabel($record),
            'summary' => $summary,
            'links' => $links,
            'lines_blob' => (string) ($record->getAttribute('lines_blob') ?? ''),
            'urls_blob' => (string) ($record->getAttribute('urls_blob') ?? ''),
            'occurred_at' => optional($record->occurred_at)?->toIso8601String(),
        ], $extras);
    }

    private static function sourceLabel(ParserRunError $record): string
    {
        $s = $record->relationLoaded('source') ? $record->source : null;
        if ($s === null) {
            return '—';
        }
        $n = is_array($s->links_json) ? count($s->links_json) : 0;

        return '#'.$s->id.' '.$s->source_type.' ('.$n.' link)';
    }

    private static function buildSummary(ParserRunError $record, array $urls): string
    {
        if ($urls === []) {
            return 'Problem link tapılmadı (error_context boş və ya URL extract olunmur).';
        }

        $title = self::productTitleFromRecord($record);
        $parts = [];
        foreach ($urls as $i => $u) {
            $idx = $i + 1;
            $parts[] = "{$idx}-ci linkdə problem var — {$u}";
        }

        return $title.': '.implode(' | ', $parts);
    }

    private static function productTitleFromRecord(ParserRunError $record): string
    {
        $pos = $record->relationLoaded('position') ? $record->position : null;
        if ($pos !== null && is_string($pos->name_en ?? null) && trim((string) $pos->name_en) !== '') {
            return trim((string) $pos->name_en);
        }

        return 'Məhsul #'.(string) ($record->position_id ?? '?');
    }

    /**
     * @return list<string>
     */
    private static function splitLines(string $blob): array
    {
        $blob = trim($blob);
        if ($blob === '') {
            return [];
        }

        $parts = preg_split("/\r\n|\n|\r/", $blob) ?: [];

        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }
}
