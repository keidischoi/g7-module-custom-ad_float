<?php

namespace Modules\Custom\AdFloat\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Custom\AdFloat\Support\ModuleVersion;

/**
 * Inject ad-float.js on user chrome the same way working G7 modules do:
 * layout scripts + HtmlContent fallback + SEO extraBodyEnd script tag.
 *
 * Do not depend on main_content_area existing.
 */
class InjectAdFloatListener implements HookListenerInterface
{
    public const SCRIPT_ID = 'custom-ad_float-js';

    public const BOOT_ID = 'g7_custom_ad_float_boot';

    public const MOUNT_ID = 'g7_custom_ad_float_mount';

    public static function getSubscribedHooks(): array
    {
        return [
            'core.layout.filter_child_data' => [
                'method' => 'filterChildData',
                'priority' => 95,
                'type' => 'filter',
                'sync' => true,
            ],
            'core.layout.filter_merged' => [
                'method' => 'filterMergedLayout',
                'priority' => 95,
                'type' => 'filter',
                'sync' => true,
            ],
            'core.layout_extension.after_apply' => [
                'method' => 'afterApply',
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

    public function filterChildData(mixed $child = null, mixed $parent = null): mixed
    {
        return $this->injectIfUserLayout($child);
    }

    public function filterMergedLayout(mixed $merged = null, mixed $parentLayout = null, mixed $childLayout = null): mixed
    {
        return $this->injectIfUserLayout($merged);
    }

    public function afterApply(mixed $layout = null, mixed $extension = null): mixed
    {
        return $this->injectIfUserLayout($layout);
    }

    public function onViewData(array $viewData, array $context = []): array
    {
        $src = ModuleVersion::scriptSrc();
        $mount = '<div id="'.self::MOUNT_ID.'" class="g7-custom-ad-float-mount" hidden></div>';
        $script = '<script src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" async></script>';
        $chunk = $mount.$script;
        $existing = (string) ($viewData['extraBodyEnd'] ?? '');
        if (! str_contains($existing, 'ad-float.js')) {
            $viewData['extraBodyEnd'] = $existing.$chunk;
        }

        return $viewData;
    }

    private function injectIfUserLayout(mixed $layout): mixed
    {
        if (! is_array($layout) || ! $this->isUserChrome($layout)) {
            return $layout;
        }

        return $this->ensureScript($layout);
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    private function isUserChrome(array $layout): bool
    {
        $name = strtolower((string) ($layout['layout_name'] ?? $layout['name'] ?? ''));
        $extends = strtolower((string) ($layout['extends'] ?? ''));
        if (str_contains($name, 'admin') || str_contains($extends, 'admin')) {
            return false;
        }
        if ($name === '_user_base' || $extends === '_user_base') {
            return true;
        }
        if (str_contains($name, 'user') || str_contains($extends, 'user')) {
            return true;
        }

        return $this->findId($layout['slots'] ?? [], 'desktop_header')
            || $this->findId($layout['slots'] ?? [], 'user_layout_root');
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    private function ensureScript(array $layout): array
    {
        $src = ModuleVersion::scriptSrc();
        $scripts = $layout['scripts'] ?? [];
        if (! is_array($scripts)) {
            $scripts = [];
        }
        $has = false;
        foreach ($scripts as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $id = (string) ($entry['id'] ?? '');
            $entrySrc = (string) ($entry['src'] ?? '');
            if ($id === self::SCRIPT_ID || str_contains($entrySrc, 'ad-float.js')) {
                $has = true;
                break;
            }
        }
        if (! $has) {
            $scripts[] = [
                'id' => self::SCRIPT_ID,
                'src' => $src,
                'async' => true,
                'defer' => true,
                'optional' => true,
                'failOnError' => false,
                'errorHandling' => 'suppress',
            ];
        }
        $layout['scripts'] = $scripts;

        return $this->ensureBootNode($layout, $src);
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    private function ensureBootNode(array $layout, string $src): array
    {
        $slots = $layout['slots'] ?? null;
        if (! is_array($slots)) {
            return $layout;
        }
        if ($this->findId($slots, self::BOOT_ID) || $this->findId($slots, self::MOUNT_ID)) {
            return $layout;
        }

        $html = '<div id="'.self::MOUNT_ID.'" class="g7-custom-ad-float-mount" hidden></div>'
            .'<script src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" async></script>';

        $node = [
            'id' => self::BOOT_ID,
            'type' => 'basic',
            'name' => 'HtmlContent',
            'props' => [
                'html' => $html,
                'failOnError' => false,
            ],
            'failOnError' => false,
        ];

        foreach (['user_layout_root', 'overlay', 'content'] as $slotName) {
            if (isset($slots[$slotName]) && is_array($slots[$slotName])) {
                $layout['slots'][$slotName][] = $node;

                return $layout;
            }
        }

        $first = array_key_first($slots);
        if ($first !== null && is_array($layout['slots'][$first])) {
            $layout['slots'][$first][] = $node;
        }

        return $layout;
    }

    private function findId(mixed $nodes, string $id): bool
    {
        if (! is_array($nodes)) {
            return false;
        }
        if (($nodes['id'] ?? null) === $id) {
            return true;
        }
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
            if (isset($node['slots']) && is_array($node['slots']) && $this->findId($node['slots'], $id)) {
                return true;
            }
        }

        return false;
    }
}
