<?php

namespace Modules\Custom\AdFloat\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdFloat\Models\AdFloatItem;
use Modules\Custom\AdFloat\Services\AdFloatService;

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
            $data = $request->validate([
                'title' => ['nullable', 'string', 'max:120'],
                'alt_text' => ['nullable', 'string', 'max:255'],
                'target_url' => ['nullable', 'url', 'max:1000'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
                'display_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
                'enabled' => ['nullable'],
                'image_url' => ['nullable', 'string', 'max:1000'],
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            ]);

            if (! $request->hasFile('image') && empty($data['image_url'])) {
                return $this->error('custom-ad_float::messages.items.image_required', 422);
            }

            $item = $this->service->createItem($data, $request->file('image'));

            return $this->success('custom-ad_float::messages.items.create_success', $item->toAdminArray(), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.create_failed', 500, $e->getMessage());
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $item = AdFloatItem::query()->findOrFail($id);
            $data = $request->validate([
                'title' => ['nullable', 'string', 'max:120'],
                'alt_text' => ['nullable', 'string', 'max:255'],
                'target_url' => ['nullable', 'url', 'max:1000'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
                'display_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
                'enabled' => ['nullable'],
                'image_url' => ['nullable', 'string', 'max:1000'],
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            ]);

            $item = $this->service->updateItem($item, $data, $request->file('image'));

            return $this->success('custom-ad_float::messages.items.update_success', $item->toAdminArray());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('custom-ad_float::messages.items.not_found');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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
