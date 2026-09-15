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
            $ids = AdminPayload::selectedItemIds($request->input('item_ids'), $request->input('sels'));
            $items = $this->service->combineItems($ids)->map->toAdminArray()->values()->all();

            return $this->success('custom-ad_float::messages.items.combine_success', [
                'data' => $items,
                'meta' => ['total' => count($items)],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error('custom-ad_float::messages.items.combine_failed', 422, $e->getMessage());
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.combine_failed', 500, $e->getMessage());
        }
    }

    public function uncombine(Request $request): JsonResponse
    {
        try {
            $ids = AdminPayload::selectedItemIds($request->input('item_ids'), $request->input('sels'));
            if ($ids === []) {
                return $this->error('custom-ad_float::messages.items.uncombine_min', 422);
            }
            $items = $this->service->uncombineItems($ids)->map->toAdminArray()->values()->all();

            return $this->success('custom-ad_float::messages.items.uncombine_success', [
                'data' => $items,
                'meta' => ['total' => count($items)],
            ]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.uncombine_failed', 500, $e->getMessage());
        }
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    private function uploadedImages(Request $request): array
    {
        $out = [];
        $images = $request->file('images');
        if (is_array($images)) {
            foreach ($images as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    $out[] = $file;
                }
            }
        } elseif ($images instanceof \Illuminate\Http\UploadedFile) {
            $out[] = $images;
        }
        $single = $request->file('image');
        if ($single instanceof \Illuminate\Http\UploadedFile) {
            $path = $single->getRealPath() ?: $single->getClientOriginalName();
            $dup = false;
            foreach ($out as $file) {
                $other = $file->getRealPath() ?: $file->getClientOriginalName();
                if ($other === $path) {
                    $dup = true;
                    break;
                }
            }
            if (! $dup) {
                array_unshift($out, $single);
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
