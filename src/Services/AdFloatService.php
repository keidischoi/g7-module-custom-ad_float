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
        $row = AdFloatSetting::current();
        $resolved = $row->resolvePublicSettings(now());
        $settings = $resolved['settings'];

        if (! empty($resolved['hide']) || empty($settings['enabled'])) {
            return ['settings' => ['enabled' => false], 'items' => []];
        }

        $itemsQuery = AdFloatItem::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id');
        $itemIds = $resolved['item_ids'] ?? [];
        if (is_array($itemIds) && $itemIds !== []) {
            $itemsQuery->whereIn('id', $itemIds);
        }
        $items = $itemsQuery
            ->limit(max(1, (int) ($settings['max_items'] ?? $row->max_items)))
            ->get();

        return [
            'settings' => $settings,
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
            'show_close', 'pause_on_hover', 'open_new_tab', 'schedules_enabled',
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
        if (array_key_exists('schedules_enabled', $data) && ! AdFloatSetting::hasSchedulesEnabledColumn()) {
            unset($data['schedules_enabled']);
        }
        if (array_key_exists('schedules', $data)) {
            $schedules = AdminPayload::normalizeSchedules($data['schedules']);
            if (AdFloatSetting::hasSchedulesColumn()) {
                $data['schedules'] = $schedules;
            } else {
                unset($data['schedules']);
            }
            $starts = array_values(array_filter(array_column($schedules, 'start_at')));
            $ends = array_values(array_filter(array_column($schedules, 'end_at')));
            $data['start_at'] = $starts[0] ?? null;
            $data['end_at'] = $ends !== [] ? $ends[count($ends) - 1] : null;
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
        $source = $this->sourceFromData($data, $image);
        $data = $this->applyImage($data, $image, $source, requireImage: true);
        if (empty($data['image_path'])) {
            throw new \InvalidArgumentException(__('custom-ad_float::messages.items.image_required'));
        }
        $data = $this->sanitizeItemFields($data, $source, true);

        return AdFloatItem::create($data);
    }

    public function updateItem(AdFloatItem $item, array $data, ?UploadedFile $image = null): AdFloatItem
    {
        $source = $this->sourceFromData($data, $image, $item->resolvedSource());
        if ($image || ($source === AdminPayload::SOURCE_URL && ! empty($data['image_url']))) {
            $this->deleteStoredImage($item);
        }
        $data = $this->applyImage($data, $image, $source, requireImage: false);
        $data = $this->sanitizeItemFields($data, $source, false);
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
    private function sanitizeItemFields(array $data, string $source, bool $isCreate): array
    {
        unset($data['image'], $data['image_url'], $data['source']);
        if (AdFloatItem::hasImageSourceColumn()) {
            $data['image_source'] = $source;
        } else {
            unset($data['image_source']);
        }
        if (array_key_exists('enabled', $data)) {
            $data['enabled'] = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN);
        } elseif ($isCreate) {
            $data['enabled'] = true;
        } else {
            unset($data['enabled']);
        }
        if (array_key_exists('sort_order', $data) || $isCreate) {
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        } else {
            unset($data['sort_order']);
        }
        if (array_key_exists('display_seconds', $data) && AdminPayload::isBlank($data['display_seconds'])) {
            $data['display_seconds'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sourceFromData(array $data, ?UploadedFile $image, ?string $fallback = null): string
    {
        $source = $data['source'] ?? $data['image_source'] ?? null;
        if (is_string($source) && in_array($source, [AdminPayload::SOURCE_UPLOAD, AdminPayload::SOURCE_URL], true)) {
            return $source;
        }
        if ($image) {
            return AdminPayload::SOURCE_UPLOAD;
        }
        if (! empty($data['image_url'])) {
            return AdminPayload::SOURCE_URL;
        }

        return $fallback ?: AdminPayload::SOURCE_URL;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyImage(array $data, ?UploadedFile $image, string $source, bool $requireImage = true): array
    {
        if ($source === AdminPayload::SOURCE_UPLOAD && $image) {
            try {
                $data['image_path'] = $image->store('custom-ad-float', 'public');
            } catch (\Throwable $e) {
                throw new \RuntimeException('Failed to store uploaded image: '.$e->getMessage(), 0, $e);
            }
        } elseif ($source === AdminPayload::SOURCE_URL && ! empty($data['image_url']) && is_string($data['image_url'])) {
            $data['image_path'] = trim($data['image_url']);
        } elseif ($requireImage) {
            $data['image_path'] = $data['image_path'] ?? '';
        }

        return $data;
    }

    private function deleteStoredImage(AdFloatItem $item): void
    {
        $path = (string) $item->image_path;
        if ($path === '' || preg_match('#^https?://#i', $path) || str_starts_with($path, '//') || str_starts_with($path, '/')) {
            return;
        }
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
