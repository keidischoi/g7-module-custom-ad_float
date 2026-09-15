<?php

namespace Modules\Custom\AdFloat\Support;

class ModuleVersion
{
    public static function string(): string
    {
        $path = dirname(__DIR__, 2).'/module.json';
        if (is_file($path)) {
            $meta = json_decode((string) file_get_contents($path), true);
            if (is_array($meta) && ! empty($meta['version'])) {
                return (string) $meta['version'];
            }
        }

        return '0.1.25';
    }

    public static function scriptSrc(): string
    {
        return '/api/modules/custom-ad_float/assets/ad-float.js?v='.rawurlencode(self::string());
    }
}
