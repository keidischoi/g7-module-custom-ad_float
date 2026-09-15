<?php

namespace Modules\Custom\AdFloat\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Custom\AdFloat\Models\AdFloatItem;
use Modules\Custom\AdFloat\Models\AdFloatSetting;
use Modules\Custom\AdFloat\Support\AdminPayload;

class AdFloatService
{
    public function payload(): array
    {
        $settings = AdFloatSetting::current();

        $now = now();
        if (($settings->start_at && $now->lt($settings->start_at)) || ($settings->end_at && $now->gt($settings->end_at))) {
            return ['settings' => ['enabled' => false], 'items' => []];
        }

        $items = AdFloatItem::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(max(1, (int) $settings->max_items))
            ->get();

        return [
            'settings' => [
                'enabled' => (bool) $settings->enabled,
                'home_only' => (bool) $settings->home_only,
                'position' => $settings->position,
                'direction' => $settings->direction,
                'interval_ms' => (int) $settings->interval_ms,
                'width_px' => (int) $settings->width_px,
                'height_px' => (int) $settings->height_px,
                'radius_px' => (int) $settings->radius_px,
                'offset_px' => (int) $settings->offset_px,
                'z_index' => (int) $settings->z_index,
                'autoplay' => (bool) $settings->autoplay,
                'show_arrows' => (bool) $settings->show_arrows,
                'show_dots' => (bool) $settings->show_dots,
                'pause_on_hover' => (bool) $settings->pause_on_hover,
                'open_new_tab' => (bool) $settings->open_new_tab,
                'mobile_mode' => $settings->mobile_mode,
                'max_items' => (int) $settings->max_items,
                'show_close' => (bool) $settings->show_close,
                'close_cookie_key' => $settings->close_cookie_key ?: 'g7_custom_ad_float_closed',
                'start_at' => optional($settings->start_at)->toIso8601String(),
                'end_at' => optional($settings->end_at)->toIso8601String(),
            ],
            'items' => $items->map(fn (AdFloatItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'image_url' => $item->imageUrl(),
                'target_url' => $item->target_url,
                'alt_text' => $item->alt_text ?: $item->title,
                'display_seconds' => $item->display_seconds,
            ])->values()->all(),
        ];
    }

    public function updateSettings(array $data): AdFloatSetting
    {
        $settings = AdFloatSetting::current();
        foreach ([
            'enabled', 'home_only', 'autoplay', 'show_arrows', 'show_dots',
            'show_close', 'pause_on_hover', 'open_new_tab',
        ] as $boolKey) {
            if (array_key_exists($boolKey, $data)) {
                $data[$boolKey] = filter_var($data[$boolKey], FILTER_VALIDATE_BOOLEAN);
            }
        }
        foreach (['start_at', 'end_at'] as $dateKey) {
            if (array_key_exists($dateKey, $data) && AdminPayload::isBlank($data[$dateKey])) {
                $data[$dateKey] = null;
            }
        }
        $settings->update($data);

        return $settings->fresh();
    }

    public function listItems()
    {
        return AdFloatItem::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function createItem(array $data, ?UploadedFile $image = null): AdFloatItem
    {
        $data = $this->applyImage($data, $image);
        if (empty($data['image_path'])) {
            throw new \InvalidArgumentException(__('custom-ad_float::messages.items.image_required'));
        }
        unset($data['image'], $data['image_url']);
        $data['enabled'] = array_key_exists('enabled', $data)
            ? filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        if (array_key_exists('display_seconds', $data) && ($data['display_seconds'] === '' || $data['display_seconds'] === null)) {
            $data['display_seconds'] = null;
        }

        return AdFloatItem::create($data);
    }

    public function updateItem(AdFloatItem $item, array $data, ?UploadedFile $image = null): AdFloatItem
    {
        if ($image) {
            $this->deleteStoredImage($item);
        }
        $data = $this->applyImage($data, $image, requireImage: false);
        unset($data['image'], $data['image_url']);
        if (array_key_exists('enabled', $data)) {
            $data['enabled'] = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('display_seconds', $data) && ($data['display_seconds'] === '' || $data['display_seconds'] === null)) {
            $data['display_seconds'] = null;
        }
        $item->update($data);

        return $item->fresh();
    }

    public function toggleItem(AdFloatItem $item): AdFloatItem
    {
        $item->update(['enabled' => ! $item->enabled]);

        return $item->fresh();
    }

    public function deleteItem(AdFloatItem $item): void
    {
        $this->deleteStoredImage($item);
        $item->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyImage(array $data, ?UploadedFile $image, bool $requireImage = true): array
    {
        if ($image) {
            try {
                $data['image_path'] = $image->store('custom-ad-float', 'public');
            } catch (\Throwable $e) {
                throw new \RuntimeException('Failed to store uploaded image: '.$e->getMessage(), 0, $e);
            }
        } elseif (! empty($data['image_url']) && is_string($data['image_url'])) {
            $data['image_path'] = trim($data['image_url']);
        } elseif ($requireImage) {
            $data['image_path'] = '';
        }

        return $data;
    }

    private function deleteStoredImage(AdFloatItem $item): void
    {
        $path = (string) $item->image_path;
        if ($path === '' || preg_match('#^https?://#i', $path) || str_starts_with($path, '//')) {
            return;
        }
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
