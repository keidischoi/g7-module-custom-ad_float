<?php

namespace Modules\Custom\AdFloat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\Custom\AdFloat\Support\AdminPayload;

class AdFloatSetting extends Model
{
    protected $table = 'custom_ad_float_settings';

    protected $fillable = [
        'enabled', 'home_only', 'position', 'direction', 'interval_ms', 'width_px', 'height_px',
        'radius_px', 'offset_px', 'z_index', 'autoplay', 'show_arrows', 'show_dots', 'show_close',
        'pause_on_hover', 'open_new_tab', 'mobile_mode', 'max_items', 'close_cookie_key',
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
            'mobile_mode' => 'hide', 'max_items' => 20, 'close_cookie_key' => 'g7_custom_ad_float_closed',
        ];
        if (static::hasSchedulesColumn()) {
            $defaults['schedules'] = [];
        }
        if (static::hasSchedulesEnabledColumn()) {
            $defaults['schedules_enabled'] = false;
        }

        return static::query()->firstOrCreate(['id' => 1], $defaults);
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

        return (bool) $this->schedules_enabled;
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
        $match = AdminPayload::firstMatchingSchedule($schedules, $now);
        if ($match === null) {
            $settings['enabled'] = false;

            return ['hide' => true, 'settings' => $settings, 'item_ids' => []];
        }

        return [
            'hide' => false,
            'settings' => AdminPayload::overlayVisual($settings, $match),
            'item_ids' => AdminPayload::normalizeItemIds($match),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicSettingsArray(): array
    {
        return [
            'enabled' => (bool) $this->enabled,
            'home_only' => (bool) $this->home_only,
            'position' => $this->position,
            'direction' => $this->direction,
            'interval_ms' => (int) $this->interval_ms,
            'width_px' => (int) $this->width_px,
            'height_px' => (int) $this->height_px,
            'radius_px' => (int) $this->radius_px,
            'offset_px' => (int) $this->offset_px,
            'z_index' => (int) $this->z_index,
            'autoplay' => (bool) $this->autoplay,
            'show_arrows' => (bool) $this->show_arrows,
            'show_dots' => (bool) $this->show_dots,
            'pause_on_hover' => (bool) $this->pause_on_hover,
            'open_new_tab' => (bool) $this->open_new_tab,
            'mobile_mode' => $this->mobile_mode,
            'max_items' => (int) $this->max_items,
            'show_close' => (bool) $this->show_close,
            'close_cookie_key' => $this->close_cookie_key ?: 'g7_custom_ad_float_closed',
            'start_at' => optional($this->start_at)->toIso8601String(),
            'end_at' => optional($this->end_at)->toIso8601String(),
        ];
    }

    public function isWithinSchedule(?\DateTimeInterface $now = null): bool
    {
        return ! $this->resolvePublicSettings($now)['hide'];
    }

    public function toAdminArray(): array
    {
        return [
            'enabled' => (bool) $this->enabled,
            'home_only' => (bool) $this->home_only,
            'position' => $this->position,
            'direction' => $this->direction,
            'interval_ms' => (int) $this->interval_ms,
            'width_px' => (int) $this->width_px,
            'height_px' => (int) $this->height_px,
            'radius_px' => (int) $this->radius_px,
            'offset_px' => (int) $this->offset_px,
            'z_index' => (int) $this->z_index,
            'max_items' => (int) $this->max_items,
            'autoplay' => (bool) $this->autoplay,
            'show_arrows' => (bool) $this->show_arrows,
            'show_dots' => (bool) $this->show_dots,
            'show_close' => (bool) $this->show_close,
            'pause_on_hover' => (bool) $this->pause_on_hover,
            'open_new_tab' => (bool) $this->open_new_tab,
            'mobile_mode' => $this->mobile_mode,
            'close_cookie_key' => $this->close_cookie_key,
            'start_at' => optional($this->start_at)->format('Y-m-d\TH:i'),
            'end_at' => optional($this->end_at)->format('Y-m-d\TH:i'),
            'schedules_enabled' => $this->schedulesEnabled(),
            'schedules' => $this->schedulesForAdmin(),
        ];
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
}
