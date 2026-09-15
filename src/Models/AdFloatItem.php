<?php

namespace Modules\Custom\AdFloat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Custom\AdFloat\Support\AdminPayload;

class AdFloatItem extends Model
{
    protected $table = 'custom_ad_float_items';

    protected $fillable = [
        'title', 'alt_text', 'image_path', 'image_source', 'target_url', 'sort_order', 'display_seconds', 'enabled',
        'carousel_group',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
        'display_seconds' => 'integer',
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
        if (str_starts_with($path, '/')) {
            return $path;
        }

        try {
            return Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            return '/storage/'.ltrim($path, '/');
        }
    }

    public function resolvedSource(): string
    {
        $source = (string) ($this->image_source ?? '');
        if (in_array($source, [AdminPayload::SOURCE_UPLOAD, AdminPayload::SOURCE_URL], true)) {
            return $source;
        }
        $path = (string) $this->image_path;

        return (preg_match('#^https?://#i', $path) || str_starts_with($path, '//') || str_starts_with($path, '/'))
            ? AdminPayload::SOURCE_URL
            : AdminPayload::SOURCE_UPLOAD;
    }

    public function toAdminArray(): array
    {
        $row = [
            'id' => $this->id,
            'title' => $this->title,
            'alt_text' => $this->alt_text,
            'image_path' => $this->image_path,
            'image_url' => $this->imageUrl(),
            'image_source' => $this->resolvedSource(),
            'source' => $this->resolvedSource(),
            'target_url' => $this->target_url,
            'sort_order' => (int) $this->sort_order,
            'display_seconds' => $this->display_seconds,
            'enabled' => (bool) $this->enabled,
        ];
        if (static::hasCarouselGroupColumn()) {
            $row['carousel_group'] = $this->carousel_group;
            $row['carousel_badge'] = $this->carousel_badge ?? null;
        }

        return $row;
    }

    public static function hasImageSourceColumn(): bool
    {
        try {
            return Schema::hasColumn('custom_ad_float_items', 'image_source');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function hasCarouselGroupColumn(): bool
    {
        try {
            return Schema::hasColumn('custom_ad_float_items', 'carousel_group');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
