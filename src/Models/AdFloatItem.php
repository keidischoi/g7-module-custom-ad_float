<?php

namespace Modules\Custom\AdFloat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AdFloatItem extends Model
{
    protected $table = 'custom_ad_float_items';

    protected $fillable = [
        'title', 'alt_text', 'image_path', 'target_url', 'sort_order', 'display_seconds', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function imageUrl(): string
    {
        $path = (string) $this->image_path;
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path) || str_starts_with($path, '//')) {
            return $path;
        }

        try {
            return Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            return '/storage/'.ltrim($path, '/');
        }
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'alt_text' => $this->alt_text,
            'image_path' => $this->image_path,
            'image_url' => $this->imageUrl(),
            'target_url' => $this->target_url,
            'sort_order' => (int) $this->sort_order,
            'display_seconds' => $this->display_seconds,
            'enabled' => (bool) $this->enabled,
        ];
    }
}
