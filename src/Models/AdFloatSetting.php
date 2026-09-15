<?php

namespace Modules\Custom\AdFloat\Models;

use Illuminate\Database\Eloquent\Model;

class AdFloatSetting extends Model
{
    protected $table = 'custom_ad_float_settings';

    protected $fillable = [
        'enabled', 'home_only', 'position', 'direction', 'interval_ms', 'width_px', 'height_px',
        'radius_px', 'offset_px', 'z_index', 'autoplay', 'show_arrows', 'show_dots', 'show_close',
        'pause_on_hover', 'open_new_tab', 'mobile_mode', 'max_items', 'close_cookie_key', 'start_at', 'end_at',
    ];

    protected $casts = [
        'enabled' => 'boolean', 'home_only' => 'boolean', 'autoplay' => 'boolean',
        'show_arrows' => 'boolean', 'show_dots' => 'boolean', 'show_close' => 'boolean',
        'pause_on_hover' => 'boolean', 'open_new_tab' => 'boolean',
        'start_at' => 'datetime', 'end_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'enabled' => true, 'home_only' => true, 'position' => 'right', 'direction' => 'horizontal',
                'interval_ms' => 4000, 'width_px' => 180, 'height_px' => 180, 'radius_px' => 10,
                'offset_px' => 24, 'z_index' => 9990, 'autoplay' => true, 'show_arrows' => true,
                'show_dots' => true, 'show_close' => true, 'pause_on_hover' => true, 'open_new_tab' => true,
                'mobile_mode' => 'hide', 'max_items' => 20, 'close_cookie_key' => 'g7_custom_ad_float_closed',
            ]
        );
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
        ];
    }
}
