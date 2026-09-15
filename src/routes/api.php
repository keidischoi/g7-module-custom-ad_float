<?php

use Illuminate\Support\Facades\Route;
use Modules\Custom\AdFloat\Http\Controllers\Admin\AdFloatItemController;
use Modules\Custom\AdFloat\Http\Controllers\Admin\AdFloatSettingController;
use Modules\Custom\AdFloat\Http\Controllers\Admin\AdFloatStatController;
use Modules\Custom\AdFloat\Http\Controllers\Public\AssetController;
use Modules\Custom\AdFloat\Http\Controllers\Public\PayloadController;
use Modules\Custom\AdFloat\Http\Controllers\Public\TrackController;

/*
| ModuleRouteServiceProvider prefix: api/modules/custom-ad_float
*/

Route::get('payload', [PayloadController::class, 'show'])
    ->middleware(['throttle:600,1'])
    ->name('payload.show');

Route::get('assets/ad-float.js', [AssetController::class, 'adFloatJs'])
    ->middleware(['throttle:600,1'])
    ->name('assets.ad_float');
Route::get('assets/ad-float', [AssetController::class, 'adFloatJs'])
    ->middleware(['throttle:600,1'])
    ->name('assets.ad_float.alias');

Route::post('track', [TrackController::class, 'store'])
    ->middleware(['throttle:120,1'])
    ->name('track.store');

Route::prefix('admin/stats')
    ->middleware(['auth:sanctum', 'throttle:600,1'])
    ->name('admin.stats.')
    ->group(function () {
        Route::get('/', [AdFloatStatController::class, 'show'])
            ->middleware('permission:admin,custom-ad_float.ads.read')
            ->name('show');
    });

Route::prefix('admin/settings')
    ->middleware(['auth:sanctum', 'throttle:600,1'])
    ->name('admin.settings.')
    ->group(function () {
        Route::get('/', [AdFloatSettingController::class, 'show'])
            ->middleware('permission:admin,custom-ad_float.ads.read')
            ->name('show');
        Route::put('/', [AdFloatSettingController::class, 'update'])
            ->middleware('permission:admin,custom-ad_float.ads.update')
            ->name('update');
        Route::post('/', [AdFloatSettingController::class, 'update'])
            ->middleware('permission:admin,custom-ad_float.ads.update')
            ->name('update.post');
    });

Route::prefix('admin/items')
    ->middleware(['auth:sanctum', 'throttle:600,1'])
    ->name('admin.items.')
    ->group(function () {
        Route::get('/', [AdFloatItemController::class, 'index'])
            ->middleware('permission:admin,custom-ad_float.ads.read')
            ->name('index');
        Route::post('/', [AdFloatItemController::class, 'store'])
            ->middleware('permission:admin,custom-ad_float.ads.create')
            ->name('store');
        Route::post('/combine', [AdFloatItemController::class, 'combine'])
            ->middleware('permission:admin,custom-ad_float.ads.update')
            ->name('combine');
        Route::post('/uncombine', [AdFloatItemController::class, 'uncombine'])
            ->middleware('permission:admin,custom-ad_float.ads.update')
            ->name('uncombine');
        Route::put('/{id}', [AdFloatItemController::class, 'update'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_float.ads.update')
            ->name('update');
        Route::post('/{id}', [AdFloatItemController::class, 'update'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_float.ads.update')
            ->name('update.post');
        Route::patch('/{id}/toggle', [AdFloatItemController::class, 'toggle'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_float.ads.update')
            ->name('toggle');
        Route::delete('/{id}', [AdFloatItemController::class, 'destroy'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_float.ads.delete')
            ->name('destroy');
    });
