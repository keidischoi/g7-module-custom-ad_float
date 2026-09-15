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
        'start_at', 'end_at', 'schedules',
    ];

    protected $casts = [
        'enabled' => 'boolean', 'home_only' => 'boolean', 'autoplay' => 'boolean',
        'show_arrows' => 'boolean', 'show_dots' => 'boolean', 'show_close' => 'boolean',
        'pause_on_hover' => 'boolean', 'open_new_tab' => 'boolean',
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
                ], 0)];
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
                $rows[] = self::scheduleToForm($row, $i);
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
            ], 0)];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function scheduleToForm(array $row, int $index = 0): array
    {
        $weekdays = AdminPayload::normalizeWeekdays($row);
        $form = [
            'id' => $row['id'] ?? ('s'.$index),
            'start_at' => AdminPayload::blankToNull($row['start_at'] ?? null) ?? '',
            'end_at' => AdminPayload::blankToNull($row['end_at'] ?? null) ?? '',
            'weekdays' => $weekdays,
        ];
        $selected = $weekdays === [] ? [0, 1, 2, 3, 4, 5, 6] : $weekdays;
        foreach ([0, 1, 2, 3, 4, 5, 6] as $d) {
            $form['d'.$d] = in_array($d, $selected, true);
        }

        return $form;
    }

    public function isWithinSchedule(?\DateTimeInterface $now = null): bool
    {
        $now = $now ?? now();
        $schedules = static::hasSchedulesColumn()
            ? AdminPayload::normalizeSchedules($this->schedules)
            : [];
        if ($schedules === []) {
            if ($this->start_at && $now < $this->start_at) {
                return false;
            }
            if ($this->end_at && $now > $this->end_at) {
                return false;
            }

            return true;
        }
        foreach ($schedules as $row) {
            if (AdminPayload::scheduleMatchesNow($row, $now)) {
                return true;
            }
        }

        return false;
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
}
