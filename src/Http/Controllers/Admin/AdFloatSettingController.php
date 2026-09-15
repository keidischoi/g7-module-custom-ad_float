<?php

namespace Modules\Custom\AdFloat\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdFloat\Services\AdFloatService;

class AdFloatSettingController extends AdminBaseController
{
    public function __construct(private AdFloatService $service)
    {
        parent::__construct();
    }

    public function show(): JsonResponse
    {
        try {
            $row = \Modules\Custom\AdFloat\Models\AdFloatSetting::current();

            return $this->success('custom-ad_float::messages.settings.fetch_success', $row->toAdminArray());
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.settings.fetch_failed', 500, $e->getMessage());
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'enabled' => ['nullable'],
                'home_only' => ['nullable'],
                'position' => ['required', 'in:left,right,top,bottom'],
                'direction' => ['required', 'in:horizontal,vertical'],
                'interval_ms' => ['required', 'integer', 'min:1000', 'max:60000'],
                'width_px' => ['required', 'integer', 'min:80', 'max:1200'],
                'height_px' => ['required', 'integer', 'min:80', 'max:1200'],
                'radius_px' => ['required', 'integer', 'min:0', 'max:100'],
                'offset_px' => ['required', 'integer', 'min:0', 'max:500'],
                'z_index' => ['required', 'integer', 'min:100', 'max:2147483647'],
                'max_items' => ['required', 'integer', 'min:1', 'max:100'],
                'close_cookie_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
                'autoplay' => ['nullable'],
                'show_arrows' => ['nullable'],
                'show_dots' => ['nullable'],
                'show_close' => ['nullable'],
                'pause_on_hover' => ['nullable'],
                'open_new_tab' => ['nullable'],
                'mobile_mode' => ['required', 'in:hide,show'],
                'start_at' => ['nullable', 'date'],
                'end_at' => ['nullable', 'date'],
            ]);

            $row = $this->service->updateSettings($data);

            return $this->success('custom-ad_float::messages.settings.update_success', $row->toAdminArray());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->error('custom-ad_float::messages.settings.update_failed', 500, $e->getMessage());
        }
    }
}
