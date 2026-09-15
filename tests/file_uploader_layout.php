<?php

declare(strict_types=1);

namespace {
    $failed = 0;
    $passed = 0;

    function expect(string $label, mixed $actual, mixed $expected): void
    {
        global $failed, $passed;
        if ($actual === $expected) {
            $passed++;
            echo "ok  {$label}\n";

            return;
        }
        $failed++;
        echo "FAIL {$label}\n  expected ".json_encode($expected)."\n  actual   ".json_encode($actual)."\n";
    }

    $path = dirname(__DIR__).'/resources/layouts/admin/admin_ad_float.json';
    $raw = file_get_contents($path);
    expect('layout json readable', is_string($raw) && $raw !== '', true);
    $layout = json_decode((string) $raw, true);
    expect('layout json valid', is_array($layout), true);

    $uploader = null;
    $scan = function ($node) use (&$scan, &$uploader): void {
        if (! is_array($node)) {
            return;
        }
        if (($node['id'] ?? '') === 'caf_create_images_uploader') {
            $uploader = $node;
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                $scan($v);
            }
        }
    };
    $scan($layout);
    expect('FileUploader node present', is_array($uploader), true);
    expect('FileUploader is composite', $uploader['type'] ?? null, 'composite');
    expect('FileUploader name', $uploader['name'] ?? null, 'FileUploader');
    expect('autoUpload false', $uploader['props']['autoUpload'] ?? null, false);
    expect('accept is extensions not image/*', $uploader['props']['accept'] ?? null, '.jpg,.jpeg,.png,.gif,.webp');
    expect('maxFiles 10', $uploader['props']['maxFiles'] ?? null, 10);

    $actionsJson = json_encode($uploader['actions'] ?? [], JSON_UNESCAPED_UNICODE);
    expect('uses event onFilesChange', str_contains((string) $actionsJson, '"event":"onFilesChange"'), true);
    expect('does not use type onFilesChange', str_contains((string) $actionsJson, '"type":"onFilesChange"'), false);
    expect('stores $args[0][0].file', str_contains((string) $actionsJson, '$args[0][0].file'), true);
    expect('first file is raw File not ternary', str_contains((string) $actionsJson, '"uploadFile":"{{$args[0][0].file}}"'), true);
    expect('stores image_count from $args[0].length', str_contains((string) $actionsJson, '$args[0].length'), true);
    expect('does not coerce File with || null', str_contains((string) $actionsJson, '.file || null'), false);
    expect('does not coerce File with ||', str_contains((string) $actionsJson, '.file ||'), false);
    expect('guards missing indexes with length', str_contains((string) $actionsJson, '$args[0].length > 1 ? $args[0][1].file'), true);
    expect('stores top-level uploadFile', str_contains((string) $actionsJson, '"uploadFile"'), true);
    expect('no .map(', str_contains((string) $actionsJson, '.map('), false);
    expect('no function keyword', str_contains((string) $actionsJson, 'function'), false);
    expect('no arrow =>', str_contains((string) $actionsJson, '=>'), false);
    expect('no optional chaining', str_contains((string) $actionsJson, '?.'), false);
    expect('no nullish coalescing', str_contains((string) $actionsJson, '??'), false);
    expect('no $event[0]', str_contains((string) $actionsJson, '$event[0]'), false);
    expect('plain file input box gone', str_contains((string) $raw, 'caf_create_images_input'), false);
    expect('plain file input box wrapper gone', str_contains((string) $raw, 'caf_create_images_box'), false);

    $sourceToggle = json_encode($layout, JSON_UNESCAPED_UNICODE);
    expect('createSource still used', str_contains((string) $sourceToggle, 'createSource'), true);
    expect('upload panel if uses createSource', str_contains((string) $sourceToggle, "(_local.createSource || _local.create.source || 'url') === 'upload'"), true);
    expect('no create?.source', str_contains((string) $sourceToggle, 'create?.source'), false);

    $createBtn = null;
    $findBtn = function ($node) use (&$findBtn, &$createBtn): void {
        if (! is_array($node)) {
            return;
        }
        if (($node['id'] ?? '') === 'btn_create_item_upload') {
            $createBtn = $node;
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                $findBtn($v);
            }
        }
    };
    $findBtn($layout);
    expect('create upload button found', is_array($createBtn), true);
    $btnJson = json_encode($createBtn, JSON_UNESCAPED_UNICODE);
    expect('create posts multipart', str_contains((string) $btnJson, 'multipart'), true);
    expect('create posts source upload', str_contains((string) $btnJson, '"source":"upload"'), true);
    expect('create posts file field', str_contains((string) $btnJson, '"file":"{{_local.uploadFile}}"'), true);
    expect('create posts image File', str_contains((string) $btnJson, '"image":"{{_local.create.image}}"'), true);
    expect('create posts images0 File', str_contains((string) $btnJson, '"images0":"{{_local.create.images0}}"'), true);
    expect('create posts images9 File', str_contains((string) $btnJson, '"images9":"{{_local.create.images9}}"'), true);
    expect('create does not post images as a File', str_contains((string) $btnJson, '"images":"{{_local.create.images}}"'), false);
    expect('create File fields have no ||', str_contains((string) $btnJson, '"file":"{{_local.uploadFile ||'), false);
    expect('create image field has no ||', str_contains((string) $btnJson, '"image":"{{_local.create.image ||'), false);

    $urlBtn = null;
    $findUrl = function ($node) use (&$findUrl, &$urlBtn): void {
        if (! is_array($node)) {
            return;
        }
        if (($node['id'] ?? '') === 'btn_create_item_url') {
            $urlBtn = $node;
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                $findUrl($v);
            }
        }
    };
    $findUrl($layout);
    expect('create url button found', is_array($urlBtn), true);
    $urlJson = json_encode($urlBtn, JSON_UNESCAPED_UNICODE);
    expect('url create posts source url', str_contains((string) $urlJson, '"source":"url"'), true);
    expect('url create posts image_url', str_contains((string) $urlJson, 'image_url'), true);
    expect('url create is not multipart', str_contains((string) $urlJson, 'multipart'), false);

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
