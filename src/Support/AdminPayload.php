<?php

namespace Modules\Custom\AdFloat\Support;

use Illuminate\Http\Request;

/**
 * Normalize admin JSON/form payloads before Laravel validation.
 *
 * G7 layout apiCall may send empty strings or the literals "null"/"undefined"
 * for optional fields (especially on engines that stringify null). Those values
 * fail rules such as url, integer, and date, which the admin UI surfaces as
 * 「광고 등록에 실패했습니다.」
 */
class AdminPayload
{
    /**
     * @param  array<int, string>  $keys
     */
    public static function nullifyEmpty(Request $request, array $keys): void
    {
        $merge = [];
        foreach ($keys as $key) {
            if (! $request->exists($key)) {
                continue;
            }
            $value = $request->input($key);
            if (self::isBlank($value)) {
                $merge[$key] = null;
            } elseif (is_string($value)) {
                $merge[$key] = trim($value);
            }
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    public static function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (! is_string($value)) {
            return false;
        }
        $trimmed = trim($value);

        return $trimmed === ''
            || strcasecmp($trimmed, 'null') === 0
            || strcasecmp($trimmed, 'undefined') === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemRules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:120'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            // string (not url): relative paths and scheme-less links are valid click targets
            'target_url' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'display_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'enabled' => ['nullable'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function itemNullableKeys(): array
    {
        return ['title', 'alt_text', 'target_url', 'image_url', 'display_seconds', 'sort_order'];
    }
}
