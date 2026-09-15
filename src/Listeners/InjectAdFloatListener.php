<?php

namespace Modules\Custom\AdFloat\Listeners;

use App\Contracts\Extension\HookListenerInterface;

/**
 * Layout / SEO hooks for custom-ad_float.
 *
 * Primary injection is via resources/extensions/ad_float__user_base.json
 * (mount + ad-float.js). This listener is a belt-and-suspenders path that
 * ensures a mount node exists on known layouts and keeps SEO body-end markup
 * available when layout extensions are not applied.
 */
class InjectAdFloatListener implements HookListenerInterface
{
    private const MOUNT_ID = 'g7_custom_ad_float_mount';

    public static function getSubscribedHooks(): array
    {
        return [
            'core.layout.filter_merged' => [
                'method' => 'filterMergedLayout',
                'priority' => 95,
                'type' => 'filter',
                'sync' => true,
            ],
            'core.seo.filter_view_data' => [
                'method' => 'onViewData',
                'priority' => 120,
            ],
        ];
    }

    public function handle(...$args): void
    {
    }

    public function filterMergedLayout(mixed $merged = null, mixed $parentLayout = null, mixed $childLayout = null): mixed
    {
        if (! is_array($merged)) {
            return $merged;
        }

        return $this->ensureMount($merged);
    }

    public function onViewData(array $viewData, array $context = []): array
    {
        // Prefer layout extension + JS. Keep a tiny marker so older themes that
        // only honor extraBodyEnd still get a mount for ad-float.js.
        $marker = '<div id="'.self::MOUNT_ID.'" class="g7-custom-ad-float-mount" data-caf-hook="seo" hidden></div>';
        $viewData['extraBodyEnd'] = ($viewData['extraBodyEnd'] ?? '').$marker;

        return $viewData;
    }

    private function ensureMount(array $layout): array
    {
        $slots = $layout['slots'] ?? null;
        if (! is_array($slots)) {
            return $layout;
        }

        foreach ($slots as $slotName => $nodes) {
            if (! is_array($nodes)) {
                continue;
            }
            if ($this->findId($nodes, self::MOUNT_ID)) {
                return $layout;
            }
        }

        $mount = [
            'id' => self::MOUNT_ID,
            'type' => 'basic',
            'name' => 'Div',
            'props' => [
                'className' => 'g7-custom-ad-float-mount',
                'data-caf-hook' => 'layout',
            ],
            'children' => [],
        ];

        if (isset($slots['overlay']) && is_array($slots['overlay'])) {
            $layout['slots']['overlay'][] = $mount;
        } elseif (isset($slots['content']) && is_array($slots['content'])) {
            $layout['slots']['content'][] = $mount;
        } else {
            $first = array_key_first($slots);
            if ($first !== null && is_array($layout['slots'][$first])) {
                $layout['slots'][$first][] = $mount;
            }
        }

        return $layout;
    }

    private function findId(array $nodes, string $id): bool
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (($node['id'] ?? null) === $id) {
                return true;
            }
            if (! empty($node['children']) && is_array($node['children']) && $this->findId($node['children'], $id)) {
                return true;
            }
        }

        return false;
    }
}
