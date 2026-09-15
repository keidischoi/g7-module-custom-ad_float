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
    expect('upload create uses file rule not image getimagesize', in_array('file', $createUpload['image'], true), true);
    expect('upload create does not use image rule', in_array('image', $createUpload['image'], true), false);
    expect('upload create still validates mime', in_array('mimes:jpg,jpeg,png,gif,webp', $createUpload['image'], true), true);
    expect('file field is an upload rule', in_array('file', $createUpload['file'] ?? [], true), true);
    expect('images0 field is an upload rule', array_key_exists('images0', $createUpload), true);
    expect('images is nullable (not required array)', $createUpload['images'][0] ?? null, 'nullable');

    $createUrl = AdminPayload::itemRules(AdminPayload::SOURCE_URL, true);
    expect('url create image_url required', $createUrl['image_url'][0] ?? null, 'required');
    expect('url create image still nullable', $createUrl['image'][0] ?? null, 'nullable');

    $updateUpload = AdminPayload::itemRules(AdminPayload::SOURCE_UPLOAD, false);
    expect('upload update image nullable', $updateUpload['image'][0] ?? null, 'nullable');

    $schedRules = AdminPayload::scheduleNestedRules();
    expect('schedule id is validated', array_key_exists('schedules.*.id', $schedRules), true);
    expect('schedule _deleted is validated', array_key_exists('schedules.*._deleted', $schedRules), true);
    expect('remove_schedule_id is validated', array_key_exists('remove_schedule_id', $schedRules), true);

    expect('images0 is an upload field name', AdminPayload::isUploadFieldName('images0'), true);
    expect('images9 is an upload field name', AdminPayload::isUploadFieldName('images9'), true);
    expect('uploadFile is an upload field name', AdminPayload::isUploadFieldName('uploadFile'), true);
    expect('uploadFile0 is an upload field name', AdminPayload::isUploadFieldName('uploadFile0'), true);
    expect('file is an upload field name', AdminPayload::isUploadFieldName('file'), true);
    expect('FileUploader is an upload field name', AdminPayload::isUploadFieldName('FileUploader'), true);
    expect('title is not an upload field name', AdminPayload::isUploadFieldName('title'), false);

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
