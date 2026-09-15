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
            AdminPayload::dropNonFileUploadFields($request);
            $files = AdminPayload::collectUploadedFiles($request);
            AdminPayload::assignPrimaryUpload($request, $files);
            $source = AdminPayload::resolveSource($request);
            $preview = $request->all();
            $urls = AdminPayload::collectImageUrls($preview);
            // FileUploader posts one File per request as `file`. Do not treat
            // combine=1 + a single file as a batch — that wraps the row as
            // {data:[...], meta} and FileUploader cannot parse Attachment.hash.
            $isBatch = count($files) > 1 || count($urls) > 1;

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
            $upload = $files[0] ?? $request->file('image') ?? $request->file('file');

            if ($source === AdminPayload::SOURCE_UPLOAD && $files === [] && ! $this->hasStagedImagePath($data)) {
                return $this->fileRequiredError();
            }
            if ($source === AdminPayload::SOURCE_URL && $urls === []) {
                return $this->urlRequiredError();
            }

            $item = $this->service->createItem($data, $upload instanceof \Illuminate\Http\UploadedFile ? $upload : null);

            return $this->success(
                'custom-ad_float::messages.items.create_success',
                $this->toUploaderAttachment($item, $upload instanceof \Illuminate\Http\UploadedFile ? $upload : null)
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e, 'custom-ad_float::messages.items.create_failed');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage() !== '' ? $e->getMessage() : 'custom-ad_float::messages.items.create_failed', 422, [$e->getMessage()]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.create_failed', 500, [$e->getMessage()]);
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
     * @param  array<string, mixed>  $data
     */
    private function hasStagedImagePath(array $data): bool
    {
        $path = $data['image_path'] ?? null;

        return is_string($path) && trim($path) !== '';
    }

    /**
     * G7 FileUploader expects Attachment-shaped fields (hash, download_url, is_image)
     * when this endpoint is used as apiEndpoints.upload. Extra keys are ignored by the list UI.
     *
     * @return array<string, mixed>
     */
    private function toUploaderAttachment(AdFloatItem $item, ?\Illuminate\Http\UploadedFile $file): array
    {
        $row = $item->toAdminArray();
        $name = $file instanceof \Illuminate\Http\UploadedFile
            ? (string) $file->getClientOriginalName()
            : basename((string) $item->image_path);

        return array_merge($row, [
            'id' => (int) $item->id,
            'hash' => 'caf-'.$item->id,
            'original_filename' => $name !== '' ? $name : ('ad-'.$item->id),
            'mime_type' => $file instanceof \Illuminate\Http\UploadedFile
                ? (string) ($file->getMimeType() ?: 'image/jpeg')
                : 'image/jpeg',
            'size' => $file instanceof \Illuminate\Http\UploadedFile ? (int) $file->getSize() : 0,
            'size_formatted' => '',
            'download_url' => $item->imageUrl(),
            'url' => $item->imageUrl(),
            'thumbnail_url' => $item->imageUrl(),
            'order' => (int) $item->sort_order,
            'is_image' => true,
        ]);
    }

    /**
     * Put Laravel's errors bag into `message` so G7 toast `{{error.message}}` is never empty.
     */
    private function validationErrorResponse(\Illuminate\Validation\ValidationException $e, string $fallbackKey): JsonResponse
    {
        $messages = AdminPayload::flattenErrorMessages($e->errors());
        $first = $messages[0] ?? __($fallbackKey);

        return $this->error($first, 422, $messages !== [] ? $messages : [$first]);
    }

    private function fileRequiredError(): JsonResponse
    {
        $msg = __('custom-ad_float::messages.items.file_required');

        return $this->error('custom-ad_float::messages.items.file_required', 422, [$msg]);
    }

    private function urlRequiredError(): JsonResponse
    {
        $msg = __('custom-ad_float::messages.items.url_required');

        return $this->error('custom-ad_float::messages.items.url_required', 422, [$msg]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $item = AdFloatItem::query()->findOrFail($id);
            AdminPayload::nullifyEmpty($request, AdminPayload::itemNullableKeys());
            AdminPayload::dropNonFileUploadFields($request);
            $files = AdminPayload::collectUploadedFiles($request);
            AdminPayload::assignPrimaryUpload($request, $files);
            $source = AdminPayload::resolveSource($request, $item->resolvedSource());
            $data = $request->validate(AdminPayload::itemRules($source, false));
            $data['image_source'] = $source;

            if ($source === AdminPayload::SOURCE_UPLOAD && $files === [] && $item->resolvedSource() !== AdminPayload::SOURCE_UPLOAD && ! $this->hasStagedImagePath($data)) {
                return $this->fileRequiredError();
            }
            if ($source === AdminPayload::SOURCE_URL && empty($data['image_url']) && $item->resolvedSource() !== AdminPayload::SOURCE_URL) {
                return $this->urlRequiredError();
            }

            $upload = $files[0] ?? $request->file('image') ?? $request->file('file');
            $item = $this->service->updateItem($item, $data, $upload instanceof \Illuminate\Http\UploadedFile ? $upload : null);

            return $this->success(
                'custom-ad_float::messages.items.update_success',
                $this->toUploaderAttachment($item, $upload instanceof \Illuminate\Http\UploadedFile ? $upload : null)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('custom-ad_float::messages.items.not_found');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e, 'custom-ad_float::messages.items.update_failed');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage() !== '' ? $e->getMessage() : 'custom-ad_float::messages.items.update_failed', 422, [$e->getMessage()]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.items.update_failed', 500, [$e->getMessage()]);
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
