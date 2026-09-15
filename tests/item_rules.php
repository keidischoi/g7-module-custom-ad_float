<?php

declare(strict_types=1);

namespace {
    require dirname(__DIR__).'/src/Support/AdminPayload.php';

    use Modules\Custom\AdFloat\Support\AdminPayload;

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

    $createUpload = AdminPayload::itemRules(AdminPayload::SOURCE_UPLOAD, true);
    expect('upload create image is nullable not required', $createUpload['image'][0] ?? null, 'nullable');
    expect('upload create still validates image mime', in_array('image', $createUpload['image'], true), true);
    expect('images is nullable (not required array)', $createUpload['images'][0] ?? null, 'nullable');

    $createUrl = AdminPayload::itemRules(AdminPayload::SOURCE_URL, true);
    expect('url create image_url required', $createUrl['image_url'][0] ?? null, 'required');
    expect('url create image still nullable', $createUrl['image'][0] ?? null, 'nullable');

    $updateUpload = AdminPayload::itemRules(AdminPayload::SOURCE_UPLOAD, false);
    expect('upload update image nullable', $updateUpload['image'][0] ?? null, 'nullable');

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
