<?php

namespace Modules\Custom\AdFloat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\Custom\AdFloat\Support\AdminPayload;

class AdFloatSetting extends Model
{
    public const DEFAULT_CLOSE_COOKIE_KEY = 'g7_custom_ad_float_closed';

    protected $table = 'custom_ad_float_settings';

    protected $fillable = [
        'enabled', 'home_only', 'position', 'direction', 'interval_ms', 'width_px', 'height_px',
        'radius_px', 'offset_px', 'vertical_align', 'vertical_offset_px', 'z_index', 'autoplay', 'show_arrows', 'show_dots', 'show_close',
        'pause_on_hover', 'open_new_tab', 'link_open_mode', 'mobile_mode', 'max_items', 'close_cookie_key',
        'start_at', 'end_at', 'schedules', 'schedules_enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean', 'home_only' => 'boolean', 'autoplay' => 'boolean',
        'show_arrows' => 'boolean', 'show_dots' => 'boolean', 'show_close' => 'boolean',
        'pause_on_hover' => 'boolean', 'open_new_tab' => 'boolean',
        'schedules_enabled' => 'boolean',
        'start_at' => 'datetime', 'end_at' => 'datetime',
        'schedules' => 'array',
    ];

    public static function current(): self
    {
        $defaults = [
            'enabled' => true, 'home_only' => true, 'position' => 'right', 'direction' => 'horizontal',
            'interval_ms' => 4000, 'width_px' => 180, 'height_px' => 180, 'radius_px' => 10,
            'offset_px' => 24, 'z_index' => 9990, 'autoplay' => true, 'show_arrows' => true,
            'show_dots' => true, 'show_close' => true, 'pause_on_hover' => true, 'open_new_tab' => true,
            'mobile_mode' => 'hide', 'max_items' => 20, 'close_cookie_key' => self::DEFAULT_CLOSE_COOKIE_KEY,
        ];
        if (static::hasSchedulesColumn()) {
            $defaults['schedules'] = [];
        }
        if (static::hasSchedulesEnabledColumn()) {
            $defaults['schedules_enabled'] = false;
        }
        if (static::hasVerticalAlignColumn()) {
            $defaults['vertical_align'] = 'middle';
        }
        if (static::hasVerticalOffsetColumn()) {
            $defaults['vertical_offset_px'] = 24;
        }
        if (static::hasLinkOpenModeColumn()) {
            $defaults['link_open_mode'] = 'new_tab';
        }

        return static::query()->firstOrCreate(['id' => 1], $defaults);
    }

    /**
     * Unique cookie/localStorage key so previous visitor close flags are ignored.
     */
    public static function generateCloseCookieKey(): string
    {
        return self::DEFAULT_CLOSE_COOKIE_KEY.'_'.time().'_'.bin2hex(random_bytes(3));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function schedulesForAdmin(): array
    {
        if (! static::hasSchedulesColumn()) {
            if ($this->start_at || $this->end_at) {
                return [self::scheduleToForm([
                    'start_at' => optional($this->start_at)->format('Y-m-d\TH:i'),
                    'end_at' => optional($this->end_at)->format('Y-m-d\TH:i'),
                    'weekdays' => [],
                ], 0, $this->visualBase())];
            }

            return [];
        }

        $schedules = $this->schedules;
        if (is_string($schedules)) {
            $decoded = json_decode($schedules, true);
            $schedules = is_array($decoded) ? $decoded : [];
        }
        if (is_array($schedules) && $schedules !== []) {
            $rows = [];
            foreach (array_values($schedules) as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = self::scheduleToForm($row, $i, $this->visualBase());
            }
            if ($rows !== []) {
                return $rows;
            }
        }
        if ($this->start_at || $this->end_at) {
            return [self::scheduleToForm([
                'start_at' => optional($this->start_at)->format('Y-m-d\TH:i'),
                'end_at' => optional($this->end_at)->format('Y-m-d\TH:i'),
                'weekdays' => [],
            ], 0, $this->visualBase())];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function scheduleToForm(array $row, int $index = 0, array $base = []): array
    {
        $weekdays = AdminPayload::normalizeWeekdays($row);
        $start = AdminPayload::combineDateTime($row['start_date'] ?? null, $row['start_time'] ?? null, $row['start_at'] ?? null);
        $end = AdminPayload::combineDateTime($row['end_date'] ?? null, $row['end_time'] ?? null, $row['end_at'] ?? null);
        $splitStart = AdminPayload::splitDateTime($start);
        $splitEnd = AdminPayload::splitDateTime($end);
        $visual = AdminPayload::extractVisual(array_merge(AdminPayload::visualDefaults(), $base, $row), true);
        $itemIds = AdminPayload::normalizeItemIds($row);
        $form = array_merge($visual, [
            'id' => $row['id'] ?? ('s'.$index),
            'enabled' => AdminPayload::scheduleRowEnabled($row),
            'start_at' => $start ?? '',
            'end_at' => $end ?? '',
            'start_date' => $splitStart['date'] ?? '',
            'start_time' => $splitStart['time'] ?? '',
            'end_date' => $splitEnd['date'] ?? '',
            'end_time' => $splitEnd['time'] ?? '',
            'weekdays' => $weekdays,
            'item_ids' => $itemIds,
            '_deleted' => false,
        ]);
        foreach ($itemIds as $id) {
            $form['sel_'.$id] = true;
        }
        $selected = $weekdays === [] ? [0, 1, 2, 3, 4, 5, 6] : $weekdays;
        foreach ([0, 1, 2, 3, 4, 5, 6] as $d) {
            $form['d'.$d] = in_array($d, $selected, true);
        }

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    public function visualBase(): array
    {
        $row = [];
        foreach (AdminPayload::visualSettingKeys() as $key) {
            $row[$key] = $this->{$key} ?? null;
        }

        return AdminPayload::extractVisual($row, true);
    }

    public function schedulesEnabled(): bool
    {
        if (! static::hasSchedulesEnabledColumn()) {
            return false;
        }

        return $this->rawFlag('schedules_enabled', false);
    }

    /**
     * @return array{hide:bool,settings:array<string,mixed>,item_ids:array<int,int>}
     */
    public function resolvePublicSettings(?\DateTimeInterface $now = null): array
    {
        $now = $now ?? now();
        $settings = $this->toPublicSettingsArray();
        if (! $this->schedulesEnabled()) {
            return ['hide' => false, 'settings' => $settings, 'item_ids' => []];
        }
        $schedules = static::hasSchedulesColumn()
            ? AdminPayload::normalizeSchedules($this->schedules)
            : [];
        $matches = AdminPayload::matchingSchedules($schedules, $now);
        if ($matches === []) {
            $settings['enabled'] = false;

            return ['hide' => true, 'settings' => $settings, 'item_ids' => []];
        }
        $first = $matches[0];

        return [
            'hide' => false,
            'settings' => AdminPayload::overlayVisual($settings, $first),
            'item_ids' => AdminPayload::normalizeItemIds($first),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicSettingsArray(): array
    {
        return AdminPayload::coerceSettingFlags([
            'enabled' => $this->rawFlag('enabled', true),
            'home_only' => $this->rawFlag('home_only', true),
            'position' => $this->position,
            'direction' => $this->direction,
            'interval_ms' => (int) $this->interval_ms,
            'width_px' => (int) $this->width_px,
            'height_px' => (int) $this->height_px,
            'radius_px' => (int) $this->radius_px,
            'offset_px' => (int) $this->offset_px,
            'vertical_align' => AdminPayload::normalizeVerticalAlign($this->vertical_align ?? null),
            'vertical_offset_px' => (int) ($this->vertical_offset_px ?? 24),
            'z_index' => (int) $this->z_index,
            'autoplay' => $this->rawFlag('autoplay', true),
            'show_arrows' => $this->rawFlag('show_arrows', true),
            'show_dots' => $this->rawFlag('show_dots', true),
            'pause_on_hover' => $this->rawFlag('pause_on_hover', true),
            'open_new_tab' => $this->linkOpenMode() === 'new_tab',
            'link_open_mode' => $this->linkOpenMode(),
            'mobile_mode' => $this->mobile_mode,
            'max_items' => (int) $this->max_items,
            'show_close' => $this->rawFlag('show_close', true),
            'close_cookie_key' => $this->close_cookie_key ?: self::DEFAULT_CLOSE_COOKIE_KEY,
            'start_at' => optional($this->start_at)->toIso8601String(),
            'end_at' => optional($this->end_at)->toIso8601String(),
        ]);
    }

    public function isWithinSchedule(?\DateTimeInterface $now = null): bool
    {
        return ! $this->resolvePublicSettings($now)['hide'];
    }

    public function toAdminArray(): array
    {
        return AdminPayload::coerceSettingFlags([
            'enabled' => $this->rawFlag('enabled', true),
            'home_only' => $this->rawFlag('home_only', true),
            'position' => $this->position,
            'direction' => $this->direction,
            'interval_ms' => (int) $this->interval_ms,
            'width_px' => (int) $this->width_px,
            'height_px' => (int) $this->height_px,
            'radius_px' => (int) $this->radius_px,
            'offset_px' => (int) $this->offset_px,
            'vertical_align' => AdminPayload::normalizeVerticalAlign($this->vertical_align ?? null),
            'vertical_offset_px' => (int) ($this->vertical_offset_px ?? 24),
            'z_index' => (int) $this->z_index,
            'max_items' => (int) $this->max_items,
            'autoplay' => $this->rawFlag('autoplay', true),
            'show_arrows' => $this->rawFlag('show_arrows', true),
            'show_dots' => $this->rawFlag('show_dots', true),
            'show_close' => $this->rawFlag('show_close', true),
            'pause_on_hover' => $this->rawFlag('pause_on_hover', true),
            'open_new_tab' => $this->linkOpenMode() === 'new_tab',
            'link_open_mode' => $this->linkOpenMode(),
            'mobile_mode' => $this->mobile_mode,
            'close_cookie_key' => $this->close_cookie_key,
            'start_at' => optional($this->start_at)->format('Y-m-d\TH:i'),
            'end_at' => optional($this->end_at)->format('Y-m-d\TH:i'),
            'schedules_enabled' => $this->schedulesEnabled(),
            'schedules' => $this->schedulesForAdmin(),
        ]);
    }

    /**
     * Read the raw DB/attribute value so string "0" is not cast with `(bool)` (truthy in PHP).
     */
    private function rawFlag(string $key, bool $default = true): bool
    {
        $attrs = $this->getAttributes();
        if (array_key_exists($key, $attrs)) {
            return AdminPayload::toBool($attrs[$key], $default);
        }

        return AdminPayload::toBool($this->{$key} ?? null, $default);
    }

    /**
     * same | new_tab | modal. Falls back to open_new_tab when the column is missing.
     */
    public function linkOpenMode(): string
    {
        $openNewTab = $this->rawFlag('open_new_tab', true);
        if (static::hasLinkOpenModeColumn()) {
            $attrs = $this->getAttributes();
            if (array_key_exists('link_open_mode', $attrs) && ! AdminPayload::isBlank($attrs['link_open_mode'])) {
                return AdminPayload::normalizeLinkOpenMode($attrs['link_open_mode'], $openNewTab);
            }
        }

        return AdminPayload::normalizeLinkOpenMode(null, $openNewTab);
    }

    public static function hasLinkOpenModeColumn(): bool
    {
        try {
            return Schema::hasColumn('custom_ad_float_settings', 'link_open_mode');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function hasSchedulesColumn(): bool
    {
        try {
            return Schema::hasColumn('custom_ad_float_settings', 'schedules');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function hasSchedulesEnabledColumn(): bool
    {
        try {
            return Schema::hasColumn('custom_ad_float_settings', 'schedules_enabled');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function hasVerticalAlignColumn(): bool
    {
        try {
            return Schema::hasColumn('custom_ad_float_settings', 'vertical_align');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function hasVerticalOffsetColumn(): bool
    {
        try {
            return Schema::hasColumn('custom_ad_float_settings', 'vertical_offset_px');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
