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
    $editUploader = null;
    $scan = function ($node) use (&$scan, &$uploader, &$editUploader): void {
        if (! is_array($node)) {
            return;
        }
        if (($node['id'] ?? '') === 'caf_create_images_uploader') {
            $uploader = $node;
        }
        if (($node['id'] ?? '') === 'caf_edit_images_uploader') {
            $editUploader = $node;
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
    expect('uploadTriggerEvent create', $uploader['props']['uploadTriggerEvent'] ?? null, 'upload:ad_float_create');
    expect(
        'apiEndpoints.upload is items store',
        $uploader['props']['apiEndpoints']['upload'] ?? null,
        '/api/modules/custom-ad_float/admin/items'
    );
    expect('uploadParams source is upload', $uploader['props']['uploadParams']['source'] ?? null, 'upload');

    $actionsJson = json_encode($uploader['actions'] ?? [], JSON_UNESCAPED_UNICODE);
    expect('uses event onFilesChange', str_contains((string) $actionsJson, '"event":"onFilesChange"'), true);
    expect('does not use type onFilesChange', str_contains((string) $actionsJson, '"type":"onFilesChange"'), false);
    expect('onFilesChange stores image_count from length', str_contains((string) $actionsJson, 'create.image_count'), true);
    expect('onFilesChange stores original_filename not File', str_contains((string) $actionsJson, 'original_filename'), true);
    expect('does not stash PendingFile.file in setState', str_contains((string) $actionsJson, '.file'), false);
    expect('does not stash uploadFile File from PendingFile', str_contains((string) $actionsJson, '"uploadFile":"{{'), false);
    expect('onUploadComplete present', str_contains((string) $actionsJson, '"event":"onUploadComplete"'), true);
    expect('onUploadError toast present', str_contains((string) $actionsJson, '"event":"onUploadError"'), true);
    expect('onUploadError toast uses $args[0]', str_contains((string) $actionsJson, '"message":"{{$args[0]}}"'), true);
    expect('no .map(', str_contains((string) $actionsJson, '.map('), false);
    expect('no function keyword', str_contains((string) $actionsJson, 'function'), false);
    expect('no arrow =>', str_contains((string) $actionsJson, '=>'), false);
    expect('no optional chaining', str_contains((string) $actionsJson, '?.'), false);
    expect('no nullish coalescing', str_contains((string) $actionsJson, '??'), false);
    expect('plain file input box gone', str_contains((string) $raw, 'caf_create_images_input'), false);
    expect('plain file input box wrapper gone', str_contains((string) $raw, 'caf_create_images_box'), false);

    expect('edit FileUploader present', is_array($editUploader), true);
    expect('edit uploadTriggerEvent', $editUploader['props']['uploadTriggerEvent'] ?? null, 'upload:ad_float_update');
    expect(
        'edit apiEndpoints.upload uses item id',
        $editUploader['props']['apiEndpoints']['upload'] ?? null,
        '/api/modules/custom-ad_float/admin/items/{{_local.editingId}}'
    );

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
    expect('create button emitEvent', str_contains((string) $btnJson, '"handler":"emitEvent"'), true);
    expect('create button trigger event', str_contains((string) $btnJson, 'upload:ad_float_create'), true);
    expect('create does not post _local.uploadFile', str_contains((string) $btnJson, '_local.uploadFile'), false);
    expect('create does not post create.image File', str_contains((string) $btnJson, '"image":"{{_local.create.image}}"'), false);
    expect('create is not a multipart apiCall', str_contains((string) $btnJson, 'multipart'), false);
    expect('create enable uses image_count', str_contains((string) $btnJson, 'image_count'), true);

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
    expect('url create onError uses error.message', str_contains((string) $urlJson, '{{error.message || error.data.message}}'), true);
    expect('url create onError does not use .join', str_contains((string) $urlJson, 'error.errors.join'), false);

    expect('layout has no error.errors.join toast', str_contains((string) $raw, 'error.errors.join'), false);

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
