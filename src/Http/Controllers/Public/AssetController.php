<?php

namespace Modules\Custom\AdFloat\Http\Controllers\Public;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class AssetController extends Controller
{
    public function adFloatJs(): Response
    {
        $path = dirname(__DIR__, 4).'/resources/assets/ad-float.js';
        if (! is_file($path)) {
            return response('/* missing ad-float.js */', 200, [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, must-revalidate',
        ]);
    }
}
