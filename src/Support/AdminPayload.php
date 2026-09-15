<?php

namespace Modules\Custom\AdFloat\Support;

use Illuminate\Http\Request;

/**
 * Normalize admin JSON/form payloads before Laravel validation.
 *
 * G7 layout apiCall may send empty strings or the literals "null"/"undefined"
 * for optional fields (especially on engines that stringify null). Those values
 * fail rules such as url, integer, and date, which the admin UI surfaces as
 * 「광고 등록에 실패했습니다.」
 */
class AdminPayload
{
    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_URL = 'url';

    /**
     * @param  array<int, string>  $keys
     */
    public static function nullifyEmpty(Request $request, array $keys): void
    {
        $merge = [];
        foreach ($keys as $key) {
            if (! $request->exists($key)) {
                continue;
            }
            $value = $request->input($key);
            if (self::isBlank($value)) {
                $merge[$key] = null;
            } elseif (is_string($value)) {
                $merge[$key] = trim($value);
            }
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    public static function nullifyScheduleBlanks(Request $request): void
    {
        if (! $request->exists('schedules')) {
            return;
        }
        $raw = $request->input('schedules');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return;
        }
        $rows = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['start_at', 'end_at'] as $key) {
                if (array_key_exists($key, $row) && self::isBlank($row[$key])) {
                    $row[$key] = null;
                }
            }
            $rows[] = $row;
        }
        $request->merge(['schedules' => $rows]);
    }

    public static function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (! is_string($value)) {
            return false;
        }
        $trimmed = trim($value);

        return $trimmed === ''
            || strcasecmp($trimmed, 'null') === 0
            || strcasecmp($trimmed, 'undefined') === 0;
    }

    public static function resolveSource(Request $request, ?string $existing = null): string
    {
        $source = $request->input('source', $request->input('image_source'));
        if (is_string($source) && in_array($source, [self::SOURCE_UPLOAD, self::SOURCE_URL], true)) {
            return $source;
        }
        if ($request->hasFile('image')) {
            return self::SOURCE_UPLOAD;
        }
        if (! self::isBlank($request->input('image_url'))) {
            return self::SOURCE_URL;
        }

        return $existing === self::SOURCE_UPLOAD ? self::SOURCE_UPLOAD : self::SOURCE_URL;
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemRules(string $source, bool $isCreate): array
    {
        $imageRule = $source === self::SOURCE_UPLOAD && $isCreate
            ? ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240']
            : ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'];

        $imageUrlRule = $source === self::SOURCE_URL && $isCreate
            ? ['required', 'string', 'max:1000']
            : ['nullable', 'string', 'max:1000'];

        return [
            'title' => ['nullable', 'string', 'max:120'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'target_url' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'display_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'enabled' => ['nullable'],
            'source' => ['nullable', 'in:upload,url'],
            'image_source' => ['nullable', 'in:upload,url'],
            'image_url' => $imageUrlRule,
            'image' => $imageRule,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function itemNullableKeys(): array
    {
        return ['title', 'alt_text', 'target_url', 'image_url', 'display_seconds', 'sort_order', 'source', 'image_source'];
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{start_at:?string,end_at:?string,weekdays:array<int,int>}>
     */
    public static function normalizeSchedules(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! empty($row['_deleted']) || ! empty($row['deleted'])) {
                continue;
            }
            $start = self::blankToNull($row['start_at'] ?? null);
            $end = self::blankToNull($row['end_at'] ?? null);
            $weekdays = self::normalizeWeekdays($row);
            if ($start === null && $end === null && $weekdays === []) {
                continue;
            }
            $out[] = [
                'start_at' => $start,
                'end_at' => $end,
                'weekdays' => $weekdays,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, int>
     */
    public static function normalizeWeekdays(array $row): array
    {
        $days = [];
        if (isset($row['weekdays']) && is_array($row['weekdays'])) {
            foreach ($row['weekdays'] as $d) {
                if (is_numeric($d)) {
                    $n = (int) $d;
                    if ($n >= 0 && $n <= 6) {
                        $days[] = $n;
                    }
                }
            }
        }
        foreach ([0, 1, 2, 3, 4, 5, 6] as $d) {
            $key = 'd'.$d;
            $on = $row[$key] ?? null;
            if ($on === true || $on === 1 || $on === '1' || $on === 'true') {
                $days[] = $d;
            }
        }
        $days = array_values(array_unique($days));
        sort($days);
        if ($days === [0, 1, 2, 3, 4, 5, 6]) {
            return [];
        }

        return $days;
    }

    /**
     * Empty weekdays = all days (Sun–Sat).
     *
     * @param  array{start_at:?string,end_at:?string,weekdays:array<int,int>}  $row
     */
    public static function scheduleMatchesNow(array $row, \DateTimeInterface $now): bool
    {
        $ts = $now->getTimestamp();
        if (! empty($row['start_at'])) {
            $start = strtotime((string) $row['start_at']);
            if ($start !== false && $ts < $start) {
                return false;
            }
        }
        if (! empty($row['end_at'])) {
            $end = strtotime((string) $row['end_at']);
            if ($end !== false && $ts > $end) {
                return false;
            }
        }
        $weekdays = $row['weekdays'] ?? [];
        if ($weekdays !== []) {
            $dow = (int) $now->format('w'); // 0=Sunday … 6=Saturday
            if (! in_array($dow, $weekdays, true)) {
                return false;
            }
        }

        return true;
    }

    public static function blankToNull(mixed $value): ?string
    {
        if (self::isBlank($value)) {
            return null;
        }
        if (is_string($value)) {
            return trim($value);
        }

        return $value === null ? null : (string) $value;
    }
}
