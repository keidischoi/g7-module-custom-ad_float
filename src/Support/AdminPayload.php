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
            'schedules.*.enabled' => ['nullable'],
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
            'images' => ['nullable', 'array', 'max:30'],
            'images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'image_urls' => ['nullable', 'array', 'max:30'],
            'image_urls.*' => ['nullable', 'string', 'max:1000'],
            'extra_urls' => ['nullable', 'array', 'max:30'],
            'extra_urls.*.url' => ['nullable', 'string', 'max:1000'],
            'combine' => ['nullable'],
            'carousel_group' => ['nullable', 'string', 'max:36'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemBatchRules(): array
    {
        $rules = self::itemRules(self::SOURCE_URL, false);
        $rules['image_url'] = ['nullable', 'string', 'max:1000'];
        $rules['image'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'];

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    public static function collectImageUrls(array $data): array
    {
        $urls = [];
        foreach (['image_url', 'image_urls', 'extra_urls'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (is_string($value)) {
                foreach (preg_split('/\r\n|\n|\r/', $value) ?: [] as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $urls[] = $line;
                    }
                }
            } elseif (is_array($value)) {
                foreach ($value as $row) {
                    if (is_string($row) && trim($row) !== '') {
                        $urls[] = trim($row);
                    } elseif (is_array($row)) {
                        $u = $row['url'] ?? $row['image_url'] ?? null;
                        if (is_string($u) && trim($u) !== '') {
                            $urls[] = trim($u);
                        }
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Collect selected ad ids from combine/uncombine payloads.
     *
     * G7 apiCall may send arrays, JSON strings, comma-separated ids, nested
     * objects, or top-level `sel_{id}` flags depending on the engine.
     *
     * @return array<int, int>
     */
    public static function selectedItemIds(mixed $ids, mixed $sels = null): array
    {
        $out = [];
        self::collectIdList($out, $ids);
        self::collectSelMap($out, $sels);

        return self::uniqueSortedIds($out);
    }

    /**
     * @return array<int, int>
     */
    public static function selectedItemIdsFromRequest(Request $request): array
    {
        $bag = $request->all();
        foreach (['item_ids', 'itemIds', 'ids', 'sels', 'itemSel', 'item_sel'] as $key) {
            if (! array_key_exists($key, $bag)) {
                $value = $request->input($key);
                if ($value !== null) {
                    $bag[$key] = $value;
                }
            }
        }

        return self::selectedItemIdsFromBag($bag);
    }

    /**
     * @param  array<string, mixed>  $bag
     * @return array<int, int>
     */
    public static function selectedItemIdsFromBag(array $bag): array
    {
        $out = [];
        foreach (['item_ids', 'itemIds', 'ids'] as $key) {
            if (array_key_exists($key, $bag)) {
                self::collectIdList($out, $bag[$key]);
            }
        }
        foreach (['sels', 'itemSel', 'item_sel'] as $key) {
            if (array_key_exists($key, $bag)) {
                self::collectSelMap($out, $bag[$key]);
            }
        }
        self::collectSelMap($out, $bag);

        return self::uniqueSortedIds($out);
    }

    /**
     * @param  array<int, int>  $out
     */
    private static function collectIdList(array &$out, mixed $ids): void
    {
        $ids = self::decodeJsonIfString($ids);
        if (is_bool($ids)) {
            return;
        }
        if (is_numeric($ids) && (int) $ids > 0) {
            $out[] = (int) $ids;

            return;
        }
        if (is_string($ids)) {
            $trimmed = trim($ids);
            if ($trimmed === '' || self::isBlank($trimmed) || str_contains($trimmed, '{{')) {
                return;
            }
            foreach (preg_split('/[,\s]+/', $trimmed) ?: [] as $part) {
                if (is_numeric($part) && (int) $part > 0) {
                    $out[] = (int) $part;
                }
            }

            return;
        }
        if (! is_array($ids)) {
            return;
        }
        $isList = array_is_list($ids);
        foreach ($ids as $key => $value) {
            if (is_array($value)) {
                self::collectIdList($out, $value);

                continue;
            }
            if ($isList) {
                if (! is_bool($value) && is_numeric($value) && (int) $value > 0) {
                    $out[] = (int) $value;
                }

                continue;
            }
            if (is_numeric($key) && (int) $key > 0 && self::isTruthyFlag($value)) {
                $out[] = (int) $key;
            } elseif (! is_bool($value) && is_numeric($value) && (int) $value > 0 && ! self::isTruthyFlag($value)) {
                $out[] = (int) $value;
            }
            if (is_string($key) && preg_match('/^(?:sel_|item_sel_|itemSel_)(\d+)$/', $key, $m) && self::isTruthyFlag($value)) {
                $out[] = (int) $m[1];
            }
        }
    }

    /**
     * @param  array<int, int>  $out
     */
    private static function collectSelMap(array &$out, mixed $sels): void
    {
        $sels = self::decodeJsonIfString($sels);
        if (! is_array($sels)) {
            return;
        }
        foreach ($sels as $key => $on) {
            $id = 0;
            if (is_numeric($key) && (int) $key > 0) {
                $id = (int) $key;
            } elseif (is_string($key) && preg_match('/^(?:sel_|item_sel_|itemSel_)(\d+)$/', $key, $m)) {
                $id = (int) $m[1];
            }
            if ($id > 0 && self::isTruthyFlag($on)) {
                $out[] = $id;
            }
        }
    }

    private static function decodeJsonIfString(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $trimmed = trim($value);
        if ($trimmed === '' || self::isBlank($trimmed) || str_contains($trimmed, '{{')) {
            return $value;
        }
        if (($trimmed[0] ?? '') !== '[' && ($trimmed[0] ?? '') !== '{') {
            return $value;
        }
        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : $value;
    }

    private static function isTruthyFlag(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === 1.0) {
            return true;
        }
        if (! is_string($value)) {
            return false;
        }
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['1', 'true', 'on', 'yes', 'checked'], true);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private static function uniqueSortedIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $out[] = (int) $id;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
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
                'enabled' => self::scheduleRowEnabled($row),
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
     * Every enabled reservation row whose window + weekdays match `$now`.
     *
     * @param  array<int, array<string, mixed>>  $schedules
     * @return array<int, array<string, mixed>>
     */
    public static function matchingSchedules(array $schedules, \DateTimeInterface $now): array
    {
        $out = [];
        foreach ($schedules as $row) {
            if (is_array($row) && self::scheduleMatchesNow($row, $now)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schedules
     * @return array<string, mixed>|null
     */
    public static function firstMatchingSchedule(array $schedules, \DateTimeInterface $now): ?array
    {
        $matches = self::matchingSchedules($schedules, $now);

        return $matches[0] ?? null;
    }

    /**
     * One floating window per position. The first matching row at that
     * position supplies visuals; later rows merge `item_ids` (empty = all ads).
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @param  array<string, mixed>  $baseSettings
     * @return array<int, array{id:string,settings:array<string,mixed>,item_ids:array<int,int>}>
     */
    public static function windowsFromMatchingSchedules(array $matches, array $baseSettings): array
    {
        $groups = [];
        foreach ($matches as $row) {
            if (! is_array($row)) {
                continue;
            }
            $settings = self::overlayVisual($baseSettings, $row);
            $pos = $settings['position'] ?? 'right';
            if (! in_array($pos, ['left', 'right', 'top', 'bottom'], true)) {
                $pos = 'right';
            }
            $settings['position'] = $pos;
            $settings['enabled'] = true;
            $ids = self::normalizeItemIds($row);
            if (! isset($groups[$pos])) {
                $groups[$pos] = [
                    'id' => $pos,
                    'settings' => $settings,
                    'item_ids' => $ids,
                ];

                continue;
            }
            $existing = $groups[$pos]['item_ids'];
            if ($existing === [] || $ids === []) {
                $groups[$pos]['item_ids'] = [];
            } else {
                $merged = array_values(array_unique(array_merge($existing, $ids)));
                sort($merged);
                $groups[$pos]['item_ids'] = $merged;
            }
        }

        return array_values($groups);
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
        $rawIds = $row['item_ids'] ?? null;
        if (is_string($rawIds)) {
            $decoded = json_decode($rawIds, true);
            $rawIds = is_array($decoded) ? $decoded : [];
        }
        if (is_array($rawIds)) {
            foreach ($rawIds as $id) {
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
     * Missing `enabled` on legacy rows counts as on.
     */
    public static function scheduleRowEnabled(array $row): bool
    {
        if (! array_key_exists('enabled', $row) || self::isBlank($row['enabled'])) {
            return true;
        }
        $value = $row['enabled'];
        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Empty weekdays = all days (Sun–Sat). Disabled rows never match.
     *
     * @param  array{start_at:?string,end_at:?string,weekdays:array<int,int>}  $row
     */
    public static function scheduleMatchesNow(array $row, \DateTimeInterface $now): bool
    {
        if (! self::scheduleRowEnabled($row)) {
            return false;
        }
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
