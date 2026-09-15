<?php

namespace Modules\Custom\AdFloat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\Custom\AdFloat\Support\AdminPayload;
use Modules\Custom\AdFloat\Support\ImageUrl;

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
        return ImageUrl::publicUrl((string) $this->image_path);
    }

    public function resolvedSource(): string
    {
        $source = (string) ($this->image_source ?? '');
        if (in_array($source, [AdminPayload::SOURCE_UPLOAD, AdminPayload::SOURCE_URL], true)) {
            return $source;
        }
        $path = (string) $this->image_path;
        if (ImageUrl::isRemoteUrl($path)) {
            return AdminPayload::SOURCE_URL;
        }
        if (ImageUrl::diskRelativePath($path) !== null) {
            return AdminPayload::SOURCE_UPLOAD;
        }

        return str_starts_with(str_replace('\\', '/', $path), '/')
            ? AdminPayload::SOURCE_URL
            : AdminPayload::SOURCE_UPLOAD;
    }

    public function toAdminArray(): array
    {
        $url = $this->imageUrl();
        $row = [
            'id' => $this->id,
            'title' => $this->title,
            'alt_text' => $this->alt_text,
            'image_path' => $this->image_path,
            'image_url' => $url,
            'download_url' => $url,
            'url' => $url,
            'thumbnail_url' => $url,
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
