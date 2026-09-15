<?php

namespace Modules\Custom\AdFloat\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdFloat\Models\AdFloatItem;
use Modules\Custom\AdFloat\Services\AdFloatService;
use Modules\Custom\AdFloat\Support\AdminPayload;

class AdFloatItemController extends AdminBaseController
{
    public function __construct(private AdFloatService $service)
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        try {
            $items = $this->service->listItems()->map->toAdminArray()->values()->all();

            return $this->success('custom-ad_float::messages.items.fetch_success', [
                'data' => $items,
                'meta' => ['total' => count($items)],
            ]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.fetch_failed', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            AdminPayload::nullifyEmpty($request, AdminPayload::itemNullableKeys());
            $files = $this->uploadedImages($request);
            $source = AdminPayload::resolveSource($request);
            $preview = $request->all();
            $urls = AdminPayload::collectImageUrls($preview);
            $isBatch = count($files) > 1 || count($urls) > 1 || $request->boolean('combine');

            if ($isBatch) {
                $data = $request->validate(AdminPayload::itemBatchRules());
                $data['image_source'] = $source;
                $items = $this->service->createItemsFromRequest($data, $files);
                $payload = array_map(fn ($item) => $item->toAdminArray(), $items);

                return $this->success('custom-ad_float::messages.items.create_success', [
                    'data' => $payload,
                    'meta' => ['total' => count($payload)],
                ]);
            }

            $data = $request->validate(AdminPayload::itemRules($source, true));
            $data['image_source'] = $source;

            if ($source === AdminPayload::SOURCE_UPLOAD && $files === []) {
                return $this->error('custom-ad_float::messages.items.file_required', 422);
            }
            if ($source === AdminPayload::SOURCE_URL && $urls === []) {
                return $this->error('custom-ad_float::messages.items.url_required', 422);
            }

            $item = $this->service->createItem($data, $files[0] ?? $request->file('image'));

            return $this->success('custom-ad_float::messages.items.create_success', $item->toAdminArray());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return $this->error('custom-ad_float::messages.items.create_failed', 422, $e->getMessage());
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.create_failed', 500, $e->getMessage());
        }
    }

    public function combine(Request $request): JsonResponse
    {
        try {
            if (! AdFloatItem::hasCarouselGroupColumn()) {
                return $this->error(
                    'custom-ad_float::messages.items.combine_unavailable',
                    422,
                    [__('custom-ad_float::messages.items.combine_unavailable')]
                );
            }
            $ids = AdminPayload::selectedItemIdsFromRequest($request);
            $items = $this->service->combineItems($ids)->map->toAdminArray()->values()->all();

            return $this->success('custom-ad_float::messages.items.combine_success', [
                'data' => $items,
                'meta' => ['total' => count($items)],
            ]);
        } catch (\InvalidArgumentException $e) {
            $key = $this->itemExceptionMessageKey($e, 'combine_failed');

            return $this->error($key, 422, [$e->getMessage()]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.combine_failed', 500, [$e->getMessage()]);
        }
    }

    public function uncombine(Request $request): JsonResponse
    {
        try {
            $ids = AdminPayload::selectedItemIdsFromRequest($request);
            if ($ids === []) {
                return $this->error(
                    'custom-ad_float::messages.items.uncombine_min',
                    422,
                    [__('custom-ad_float::messages.items.uncombine_min')]
                );
            }
            $items = $this->service->uncombineItems($ids)->map->toAdminArray()->values()->all();

            return $this->success('custom-ad_float::messages.items.uncombine_success', [
                'data' => $items,
                'meta' => ['total' => count($items)],
            ]);
        } catch (\InvalidArgumentException $e) {
            $key = $this->itemExceptionMessageKey($e, 'uncombine_failed');

            return $this->error($key, 422, [$e->getMessage()]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.uncombine_failed', 500, [$e->getMessage()]);
        }
    }

    private function itemExceptionMessageKey(\InvalidArgumentException $e, string $fallback): string
    {
        $msg = $e->getMessage();
        foreach (['combine_min', 'combine_unavailable', 'uncombine_min'] as $name) {
            $key = 'custom-ad_float::messages.items.'.$name;
            if ($msg === __($key)) {
                return $key;
            }
        }

        return 'custom-ad_float::messages.items.'.$fallback;
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    private function uploadedImages(Request $request): array
    {
        $out = [];
        $seen = [];
        $add = function ($file) use (&$out, &$seen) {
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                return;
            }
            $token = $file->getRealPath() ?: ($file->getClientOriginalName().':'.$file->getSize());
            if (isset($seen[$token])) {
                return;
            }
            $seen[$token] = true;
            $out[] = $file;
        };

        foreach ($request->allFiles() as $key => $file) {
            $name = is_string($key) ? $key : '';
            if ($name !== 'image' && $name !== 'images' && ! str_starts_with($name, 'images')) {
                continue;
            }
            if (is_array($file)) {
                foreach ($file as $one) {
                    $add($one);
                }
            } else {
                $add($file);
            }
        }

        return $out;
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $item = AdFloatItem::query()->findOrFail($id);
            AdminPayload::nullifyEmpty($request, AdminPayload::itemNullableKeys());
            $source = AdminPayload::resolveSource($request, $item->resolvedSource());
            $data = $request->validate(AdminPayload::itemRules($source, false));
            $data['image_source'] = $source;

            if ($source === AdminPayload::SOURCE_UPLOAD && ! $request->hasFile('image') && $item->resolvedSource() !== AdminPayload::SOURCE_UPLOAD) {
                return $this->error('custom-ad_float::messages.items.file_required', 422);
            }
            if ($source === AdminPayload::SOURCE_URL && empty($data['image_url']) && $item->resolvedSource() !== AdminPayload::SOURCE_URL) {
                return $this->error('custom-ad_float::messages.items.url_required', 422);
            }

            $item = $this->service->updateItem($item, $data, $request->file('image'));

            return $this->success('custom-ad_float::messages.items.update_success', $item->toAdminArray());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('custom-ad_float::messages.items.not_found');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return $this->error('custom-ad_float::messages.items.update_failed', 422, $e->getMessage());
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.update_failed', 500, $e->getMessage());
        }
    }

    public function toggle(int $id): JsonResponse
    {
        try {
            $item = AdFloatItem::query()->findOrFail($id);
            $item = $this->service->toggleItem($item);

            return $this->success('custom-ad_float::messages.items.toggle_success', $item->toAdminArray());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('custom-ad_float::messages.items.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.update_failed', 500, $e->getMessage());
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $item = AdFloatItem::query()->findOrFail($id);
            $this->service->deleteItem($item);

            return $this->success('custom-ad_float::messages.items.delete_success');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('custom-ad_float::messages.items.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.delete_failed', 500, $e->getMessage());
        }
    }
}
