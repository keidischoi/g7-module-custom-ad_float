<?php

namespace Modules\Custom\AdFloat\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            return [
                'settings' => ['enabled' => false],
                'items' => [],
                'windows' => [],
            ];
        }

        $allowedIds = $resolved['item_ids'] ?? [];
        $allItems = $this->orderItemsForCarousel(
            AdFloatItem::query()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get()
        );
        if (is_array($allowedIds) && $allowedIds !== []) {
            $allow = array_fill_keys(array_map('intval', $allowedIds), true);
            $allItems = $allItems->filter(fn (AdFloatItem $item) => isset($allow[(int) $item->id]))->values();
        }

        $placements = $row->normalizedPlacements();
        // Reservation visuals would force every window to one position. With
        // placements, schedule match only gates which items are allowed.
        $windowBase = $placements === [] ? $settings : $row->toPublicSettingsArray();
        $windowBase['enabled'] = true;

        $windows = $this->payloadWindows($placements, $windowBase, $allItems);
        if ($windows === []) {
            return [
                'settings' => ['enabled' => false],
                'items' => [],
                'windows' => [],
            ];
        }
        $first = $windows[0];

        return [
            'settings' => $first['settings'],
            'items' => $first['items'],
            'windows' => $windows,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $placements
     * @param  Collection<int, AdFloatItem>  $allItems
     * @param  array<string, mixed>  $settings
     * @return array<int, array{id:string,settings:array<string,mixed>,items:array<int, array<string, mixed>>}>
     */
    private function payloadWindows(array $placements, array $settings, Collection $allItems): array
    {
        if ($placements === []) {
            $items = $allItems->take(max(1, (int) ($settings['max_items'] ?? 20)));

            return [[
                'id' => 'default',
                'settings' => $settings,
                'items' => $items->map(fn (AdFloatItem $item) => $this->publicItemArray($item))->values()->all(),
            ]];
        }

        $windows = [];
        foreach ($placements as $placement) {
            if (empty($placement['enabled'])) {
                continue;
            }
            $windowSettings = AdminPayload::overlayVisual($settings, $placement);
            $windowSettings['enabled'] = true;
            $windowSettings['home_only'] = (bool) ($settings['home_only'] ?? true);
            $windowSettings['close_cookie_key'] = (string) ($settings['close_cookie_key'] ?? AdFloatSetting::DEFAULT_CLOSE_COOKIE_KEY);
            $ids = $placement['item_ids'] ?? [];
            $items = $allItems;
            if (is_array($ids) && $ids !== []) {
                $allow = array_fill_keys(array_map('intval', $ids), true);
                $items = $items->filter(fn (AdFloatItem $item) => isset($allow[(int) $item->id]))->values();
            }
            $items = $this->orderItemsForCarousel($items)
                ->take(max(1, (int) ($windowSettings['max_items'] ?? 20)));
            if ($items->isEmpty()) {
                continue;
            }
            $windows[] = [
                'id' => (string) ($placement['id'] ?? 'p'.(count($windows) + 1)),
                'settings' => $windowSettings,
                'items' => $items->map(fn (AdFloatItem $item) => $this->publicItemArray($item))->values()->all(),
            ];
        }

        return $windows;
    }

    /**
     * @return array<string, mixed>
     */
    private function publicItemArray(AdFloatItem $item): array
    {
        $row = [
            'id' => $item->id,
            'title' => $item->title,
            'image_url' => $item->imageUrl(),
            'target_url' => $item->target_url,
            'alt_text' => $item->alt_text ?: $item->title,
            'display_seconds' => $item->display_seconds,
            'sort_order' => (int) $item->sort_order,
        ];
        if (AdFloatItem::hasCarouselGroupColumn()) {
            $row['carousel_group'] = $item->carousel_group;
        }

        return $row;
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
        if (array_key_exists('vertical_align', $data)) {
            if (! AdFloatSetting::hasVerticalAlignColumn()) {
                unset($data['vertical_align']);
            } else {
                $data['vertical_align'] = AdminPayload::normalizeVerticalAlign($data['vertical_align']);
            }
        }
        if (array_key_exists('vertical_offset_px', $data) && ! AdFloatSetting::hasVerticalOffsetColumn()) {
            unset($data['vertical_offset_px']);
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
        if (array_key_exists('placements', $data)) {
            if (AdFloatSetting::hasPlacementsColumn()) {
                $data['placements'] = AdminPayload::normalizePlacements($data['placements']);
            } else {
                unset($data['placements']);
            }
        }
        $settings->update($data);

        return $settings->fresh();
    }

    public function resetClosedState(): AdFloatSetting
    {
        $settings = AdFloatSetting::current();
        $current = (string) ($settings->close_cookie_key ?: '');
        do {
            $key = AdFloatSetting::generateCloseCookieKey();
        } while ($key === $current);

        $settings->update(['close_cookie_key' => $key]);

        return $settings->fresh();
    }

    public function listItems()
    {
        $items = AdFloatItem::query()->orderBy('sort_order')->orderBy('id')->get();
        $labels = $this->carouselBadges($items);
        foreach ($items as $item) {
            $item->carousel_badge = $labels[$item->id] ?? null;
        }

        return $items;
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     * @return array<int, AdFloatItem>
     */
    public function createItemsFromRequest(array $data, array $files): array
    {
        $jobs = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $jobs[] = ['source' => AdminPayload::SOURCE_UPLOAD, 'file' => $file, 'image_url' => null];
            }
        }
        foreach (AdminPayload::collectImageUrls($data) as $url) {
            $jobs[] = ['source' => AdminPayload::SOURCE_URL, 'file' => null, 'image_url' => $url];
        }
        if ($jobs === []) {
            throw new \InvalidArgumentException(__('custom-ad_float::messages.items.image_required'));
        }

        $combine = filter_var($data['combine'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $group = ($combine && count($jobs) > 1 && AdFloatItem::hasCarouselGroupColumn())
            ? (string) Str::uuid()
            : null;
        $sort = (int) ($data['sort_order'] ?? 0);
        $baseTitle = is_string($data['title'] ?? null) ? trim((string) $data['title']) : '';
        $created = [];
        foreach ($jobs as $i => $job) {
            $row = $data;
            $row['source'] = $job['source'];
            $row['image_source'] = $job['source'];
            $row['image_url'] = $job['image_url'];
            $row['sort_order'] = $sort + $i;
            if ($group) {
                $row['carousel_group'] = $group;
            }
            if ($baseTitle !== '' && count($jobs) > 1) {
                $row['title'] = $baseTitle.' ('.($i + 1).'/'.count($jobs).')';
            }
            $created[] = $this->createItem($row, $job['file']);
        }

        return $created;
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, AdFloatItem>
     */
    public function combineItems(array $ids): Collection
    {
        if (! AdFloatItem::hasCarouselGroupColumn()) {
            throw new \InvalidArgumentException(__('custom-ad_float::messages.items.combine_unavailable'));
        }
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => (int) $id > 0)));
        $items = AdFloatItem::query()->whereIn('id', $ids)->orderBy('sort_order')->orderBy('id')->get();
        if ($items->count() < 2) {
            throw new \InvalidArgumentException(__('custom-ad_float::messages.items.combine_min'));
        }
        $group = (string) Str::uuid();
        foreach ($items as $item) {
            $item->update(['carousel_group' => $group]);
        }

        return $this->listItems();
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, AdFloatItem>
     */
    public function uncombineItems(array $ids): Collection
    {
        if (! AdFloatItem::hasCarouselGroupColumn()) {
            return $this->listItems();
        }
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => (int) $id > 0)));
        if ($ids !== []) {
            AdFloatItem::query()->whereIn('id', $ids)->update(['carousel_group' => null]);
        }

        return $this->listItems();
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
        unset($data['combine'], $data['extra_urls'], $data['image_urls'], $data['images'], $data['item_ids'], $data['sels']);
        if (AdFloatItem::hasCarouselGroupColumn()) {
            if (array_key_exists('carousel_group', $data)) {
                $group = $data['carousel_group'];
                $data['carousel_group'] = (is_string($group) && trim($group) !== '') ? trim($group) : null;
            } elseif ($isCreate) {
                $data['carousel_group'] = $data['carousel_group'] ?? null;
            }
        } else {
            unset($data['carousel_group']);
        }

        return $data;
    }

    /**
     * Flatten order: groups by (min sort_order, min id), then sort_order, id.
     * Ungrouped items are their own group.
     *
     * @param  Collection<int, AdFloatItem>  $items
     * @return Collection<int, AdFloatItem>
     */
    private function orderItemsForCarousel(Collection $items): Collection
    {
        if ($items->isEmpty() || ! AdFloatItem::hasCarouselGroupColumn()) {
            return $items->values();
        }

        $groupMin = [];
        foreach ($items as $item) {
            $key = $this->carouselKey($item);
            $so = (int) $item->sort_order;
            $id = (int) $item->id;
            if (! isset($groupMin[$key]) || $so < $groupMin[$key]['so'] || ($so === $groupMin[$key]['so'] && $id < $groupMin[$key]['id'])) {
                $groupMin[$key] = ['so' => $so, 'id' => $id];
            }
        }

        return $items->sort(function (AdFloatItem $a, AdFloatItem $b) use ($groupMin) {
            $ka = $this->carouselKey($a);
            $kb = $this->carouselKey($b);
            $ga = $groupMin[$ka];
            $gb = $groupMin[$kb];
            if ($ga['so'] !== $gb['so']) {
                return $ga['so'] <=> $gb['so'];
            }
            if ($ga['id'] !== $gb['id']) {
                return $ga['id'] <=> $gb['id'];
            }
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }
            if ((int) $a->sort_order !== (int) $b->sort_order) {
                return (int) $a->sort_order <=> (int) $b->sort_order;
            }

            return (int) $a->id <=> (int) $b->id;
        })->values();
    }

    private function carouselKey(AdFloatItem $item): string
    {
        $group = is_string($item->carousel_group) ? trim($item->carousel_group) : '';

        return $group !== '' ? 'g:'.$group : 'id:'.$item->id;
    }

    /**
     * @param  Collection<int, AdFloatItem>  $items
     * @return array<int, string>
     */
    private function carouselBadges(Collection $items): array
    {
        if (! AdFloatItem::hasCarouselGroupColumn()) {
            return [];
        }
        $order = [];
        foreach ($items as $item) {
            $group = is_string($item->carousel_group) ? trim($item->carousel_group) : '';
            if ($group === '' || isset($order[$group])) {
                continue;
            }
            $order[$group] = count($order);
        }
        $out = [];
        foreach ($items as $item) {
            $group = is_string($item->carousel_group) ? trim($item->carousel_group) : '';
            if ($group === '' || ! isset($order[$group])) {
                continue;
            }
            $n = $order[$group];
            $out[$item->id] = $n < 26 ? chr(65 + $n) : (string) ($n + 1);
        }

        return $out;
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
