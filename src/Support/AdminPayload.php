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

    /**
     * Visual fields copied from 기본 설정 onto each reservation row.
     *
     * @return array<int, string>
     */
    public static function visualSettingKeys(): array
    {
        return [
            'position', 'direction', 'interval_ms', 'width_px', 'height_px',
            'radius_px', 'offset_px', 'vertical_align', 'vertical_offset_px',
            'z_index', 'max_items', 'mobile_mode',
            'autoplay', 'show_arrows', 'show_dots', 'show_close',
            'pause_on_hover', 'open_new_tab',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function visualDefaults(): array
    {
        return [
            'position' => 'right',
            'direction' => 'horizontal',
            'interval_ms' => 4000,
            'width_px' => 180,
            'height_px' => 180,
            'radius_px' => 10,
            'offset_px' => 24,
            'vertical_align' => 'middle',
            'vertical_offset_px' => 24,
            'z_index' => 9990,
            'max_items' => 20,
            'mobile_mode' => 'hide',
            'autoplay' => true,
            'show_arrows' => true,
            'show_dots' => true,
            'show_close' => true,
            'pause_on_hover' => true,
            'open_new_tab' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function scheduleNestedRules(): array
    {
        return [
            'schedules' => ['nullable', 'array'],
            'schedules.*.start_at' => ['nullable', 'date'],
            'schedules.*.end_at' => ['nullable', 'date'],
            'schedules.*.start_date' => ['nullable', 'date'],
            'schedules.*.start_time' => ['nullable', 'string', 'max:8'],
            'schedules.*.end_date' => ['nullable', 'date'],
            'schedules.*.end_time' => ['nullable', 'string', 'max:8'],
            'schedules.*.weekdays' => ['nullable', 'array'],
            'schedules.*.weekdays.*' => ['integer', 'min:0', 'max:6'],
            'schedules.*.d0' => ['nullable'],
            'schedules.*.d1' => ['nullable'],
            'schedules.*.d2' => ['nullable'],
            'schedules.*.d3' => ['nullable'],
            'schedules.*.d4' => ['nullable'],
            'schedules.*.d5' => ['nullable'],
            'schedules.*.d6' => ['nullable'],
            'schedules.*.item_ids' => ['nullable', 'array'],
            'schedules.*.item_ids.*' => ['integer', 'min:1'],
            'schedules.*.position' => ['nullable', 'in:left,right,top,bottom'],
            'schedules.*.direction' => ['nullable', 'in:horizontal,vertical'],
            'schedules.*.interval_ms' => ['nullable', 'integer', 'min:1000', 'max:60000'],
            'schedules.*.width_px' => ['nullable', 'integer', 'min:80', 'max:1200'],
            'schedules.*.height_px' => ['nullable', 'integer', 'min:80', 'max:1200'],
            'schedules.*.radius_px' => ['nullable', 'integer', 'min:0', 'max:100'],
            'schedules.*.offset_px' => ['nullable', 'integer', 'min:-500', 'max:500'],
            'schedules.*.vertical_align' => ['nullable', 'in:top,middle,bottom'],
            'schedules.*.vertical_offset_px' => ['nullable', 'integer', 'min:-500', 'max:500'],
            'schedules.*.z_index' => ['nullable', 'integer', 'min:100', 'max:2147483647'],
            'schedules.*.max_items' => ['nullable', 'integer', 'min:1', 'max:100'],
            'schedules.*.mobile_mode' => ['nullable', 'in:hide,show'],
            'schedules.*.autoplay' => ['nullable'],
            'schedules.*.show_arrows' => ['nullable'],
            'schedules.*.show_dots' => ['nullable'],
            'schedules.*.show_close' => ['nullable'],
            'schedules.*.pause_on_hover' => ['nullable'],
            'schedules.*.open_new_tab' => ['nullable'],
        ];
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
            foreach (['start_at', 'end_at', 'start_date', 'start_time', 'end_date', 'end_time'] as $key) {
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
     * @return array<int, array<string, mixed>>
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
            $start = self::combineDateTime($row['start_date'] ?? null, $row['start_time'] ?? null, $row['start_at'] ?? null);
            $end = self::combineDateTime($row['end_date'] ?? null, $row['end_time'] ?? null, $row['end_at'] ?? null);
            $weekdays = self::normalizeWeekdays($row);
            $visual = self::extractVisual($row);
            $itemIds = self::normalizeItemIds($row);
            if ($start === null && $end === null && $weekdays === [] && $visual === [] && $itemIds === []) {
                continue;
            }
            $splitStart = self::splitDateTime($start);
            $splitEnd = self::splitDateTime($end);
            $out[] = array_merge($visual, [
                'start_at' => $start,
                'end_at' => $end,
                'start_date' => $splitStart['date'],
                'start_time' => $splitStart['time'],
                'end_date' => $splitEnd['date'],
                'end_time' => $splitEnd['time'],
                'weekdays' => $weekdays,
                'item_ids' => $itemIds,
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function extractVisual(array $row, bool $fillDefaults = false): array
    {
        $defaults = self::visualDefaults();
        $out = [];
        foreach (self::visualSettingKeys() as $key) {
            if (! array_key_exists($key, $row) || self::isBlank($row[$key])) {
                if ($fillDefaults) {
                    $out[$key] = $defaults[$key];
                }
                continue;
            }
            $value = $row[$key];
            if (in_array($key, ['autoplay', 'show_arrows', 'show_dots', 'show_close', 'pause_on_hover', 'open_new_tab'], true)) {
                $out[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (in_array($key, ['interval_ms', 'width_px', 'height_px', 'radius_px', 'offset_px', 'vertical_offset_px', 'z_index', 'max_items'], true)) {
                $out[$key] = (int) $value;
            } elseif ($key === 'vertical_align') {
                $out[$key] = self::normalizeVerticalAlign($value);
            } else {
                $out[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function overlayVisual(array $base, array $row): array
    {
        return array_merge($base, self::extractVisual($row, false));
    }

    /**
     * @param  array<int, array<string, mixed>>  $schedules
     * @return array<string, mixed>|null
     */
    public static function firstMatchingSchedule(array $schedules, \DateTimeInterface $now): ?array
    {
        foreach ($schedules as $row) {
            if (is_array($row) && self::scheduleMatchesNow($row, $now)) {
                return $row;
            }
        }

        return null;
    }

    public static function combineDateTime(mixed $date, mixed $time, mixed $fallback = null): ?string
    {
        $date = self::blankToNull($date);
        $time = self::blankToNull($time);
        $fallback = self::blankToNull($fallback);
        if ($date !== null && $time !== null) {
            $time = strlen($time) === 5 ? $time.':00' : $time;

            return $date.'T'.$time;
        }
        if ($date !== null) {
            return $date.'T00:00:00';
        }
        if ($fallback !== null) {
            return $fallback;
        }

        return null;
    }

    /**
     * @return array{date:?string,time:?string}
     */
    public static function splitDateTime(?string $value): array
    {
        $value = self::blankToNull($value);
        if ($value === null) {
            return ['date' => null, 'time' => null];
        }
        $ts = strtotime($value);
        if ($ts === false) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/', $value, $m)) {
                return ['date' => $m[1], 'time' => $m[2]];
            }

            return ['date' => $value, 'time' => null];
        }

        return [
            'date' => date('Y-m-d', $ts),
            'time' => date('H:i', $ts),
        ];
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
     * @param  array<string, mixed>  $row
     * @return array<int, int>
     */
    public static function normalizeItemIds(array $row): array
    {
        $ids = [];
        if (isset($row['item_ids']) && is_array($row['item_ids'])) {
            foreach ($row['item_ids'] as $id) {
                if (is_numeric($id) && (int) $id > 0) {
                    $ids[] = (int) $id;
                }
            }
        }
        foreach ($row as $key => $value) {
            if (! is_string($key) || ! preg_match('/^sel_(\d+)$/', $key, $m)) {
                continue;
            }
            if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                $ids[] = (int) $m[1];
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
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

    public static function normalizeVerticalAlign(mixed $value): string
    {
        $align = is_string($value) ? strtolower(trim($value)) : '';
        if (in_array($align, ['top', 'middle', 'bottom'], true)) {
            return $align;
        }

        return 'middle';
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
