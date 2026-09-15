<?php

namespace Modules\Custom\AdFloat\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Modules\Custom\AdFloat\Models\AdFloatItem;
use Modules\Custom\AdFloat\Models\AdFloatStat;
use Modules\Custom\AdFloat\Support\PagePath;

class AdFloatStatService
{
    public const RANGES = ['today', '7d', '30d', 'all'];

    public function increment(string $type, int $itemId, string $pagePath): void
    {
        if (! AdFloatStat::tableReady() || $itemId < 1) {
            return;
        }

        $column = $type === 'click' ? 'clicks' : 'impressions';
        $path = PagePath::normalize($pagePath);
        $date = now()->toDateString();

        $updated = AdFloatStat::query()
            ->where('item_id', $itemId)
            ->where('page_path', $path)
            ->where('stat_date', $date)
            ->increment($column);

        if ($updated > 0) {
            return;
        }

        try {
            AdFloatStat::query()->create([
                'item_id' => $itemId,
                'page_path' => $path,
                'stat_date' => $date,
                'impressions' => $column === 'impressions' ? 1 : 0,
                'clicks' => $column === 'clicks' ? 1 : 0,
            ]);
        } catch (QueryException $e) {
            AdFloatStat::query()
                ->where('item_id', $itemId)
                ->where('page_path', $path)
                ->where('stat_date', $date)
                ->increment($column);
        }
    }

    /**
     * @return array{range: string, from: ?string, to: ?string, summary: array<string, mixed>, by_item: array<int, array<string, mixed>>, by_page: array<int, array<string, mixed>>, by_item_page: array<int, array<string, mixed>>}
     */
    public function aggregate(string $range = '7d'): array
    {
        $range = $this->normalizeRange($range);
        [$from, $to] = $this->rangeBounds($range);

        if (! AdFloatStat::tableReady()) {
            return $this->emptyPayload($range, $from, $to);
        }

        $base = AdFloatStat::query();
        if ($from !== null) {
            $base->where('stat_date', '>=', $from);
        }
        if ($to !== null) {
            $base->where('stat_date', '<=', $to);
        }

        $summaryRow = (clone $base)
            ->selectRaw('COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks')
            ->first();
        $impressions = (int) ($summaryRow->impressions ?? 0);
        $clicks = (int) ($summaryRow->clicks ?? 0);

        $byItemRows = (clone $base)
            ->selectRaw('item_id, COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks')
            ->groupBy('item_id')
            ->orderByDesc('impressions')
            ->orderByDesc('clicks')
            ->get();

        $byPageRows = (clone $base)
            ->selectRaw('page_path, COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks')
            ->groupBy('page_path')
            ->orderByDesc('impressions')
            ->orderByDesc('clicks')
            ->get();

        $byItemPageRows = (clone $base)
            ->selectRaw('item_id, page_path, COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks')
            ->groupBy('item_id', 'page_path')
            ->orderByDesc('impressions')
            ->orderByDesc('clicks')
            ->get();

        $items = $this->itemsById($byItemRows->pluck('item_id')->merge($byItemPageRows->pluck('item_id')));

        return [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'summary' => $this->metrics($impressions, $clicks),
            'by_item' => $byItemRows->map(function ($row) use ($items) {
                $itemId = (int) $row->item_id;
                $item = $items->get($itemId);
                $impressions = (int) $row->impressions;
                $clicks = (int) $row->clicks;

                return array_merge($this->metrics($impressions, $clicks), [
                    'item_id' => $itemId ?: null,
                    'title' => $item ? ($item->title ?: __('custom-ad_float::messages.stats.untitled')) : __('custom-ad_float::messages.stats.deleted_item', ['id' => $itemId]),
                    'image_url' => $item ? $item->imageUrl() : '',
                    'download_url' => $item ? $item->imageUrl() : '',
                ]);
            })->values()->all(),
            'by_page' => $byPageRows->map(function ($row) {
                $impressions = (int) $row->impressions;
                $clicks = (int) $row->clicks;

                return array_merge($this->metrics($impressions, $clicks), [
                    'page_path' => (string) $row->page_path,
                ]);
            })->values()->all(),
            'by_item_page' => $byItemPageRows->map(function ($row) use ($items) {
                $itemId = (int) $row->item_id;
                $item = $items->get($itemId);
                $impressions = (int) $row->impressions;
                $clicks = (int) $row->clicks;

                return array_merge($this->metrics($impressions, $clicks), [
                    'item_id' => $itemId ?: null,
                    'title' => $item ? ($item->title ?: __('custom-ad_float::messages.stats.untitled')) : __('custom-ad_float::messages.stats.deleted_item', ['id' => $itemId]),
                    'image_url' => $item ? $item->imageUrl() : '',
                    'download_url' => $item ? $item->imageUrl() : '',
                    'page_path' => (string) $row->page_path,
                ]);
            })->values()->all(),
        ];
    }

    public function normalizeRange(string $range): string
    {
        $range = strtolower(trim($range));

        return in_array($range, self::RANGES, true) ? $range : '7d';
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function rangeBounds(string $range): array
    {
        $today = now()->startOfDay();

        return match ($this->normalizeRange($range)) {
            'today' => [$today->toDateString(), $today->toDateString()],
            '30d' => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
            'all' => [null, null],
            default => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
        };
    }

    /**
     * @return array{impressions: int, clicks: int, ctr: string, ctr_pct: float}
     */
    public static function metrics(int $impressions, int $clicks): array
    {
        $pct = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0;

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => number_format($pct, 2, '.', '').'%',
            'ctr_pct' => $pct,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return Collection<int, AdFloatItem>
     */
    private function itemsById(Collection $ids): Collection
    {
        $ids = $ids->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return AdFloatItem::query()->whereIn('id', $ids->all())->get()->keyBy('id');
    }

    /**
     * @return array{range: string, from: ?string, to: ?string, summary: array<string, mixed>, by_item: array<int, mixed>, by_page: array<int, mixed>, by_item_page: array<int, mixed>}
     */
    private function emptyPayload(string $range, ?string $from, ?string $to): array
    {
        return [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'summary' => self::metrics(0, 0),
            'by_item' => [],
            'by_page' => [],
            'by_item_page' => [],
        ];
    }
}
