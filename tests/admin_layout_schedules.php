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

    $schedSection = null;
    foreach ($layout['slots']['content'][0]['children'] ?? [] as $child) {
        $blob = json_encode($child, JSON_UNESCAPED_UNICODE);
        if (is_string($blob) && str_contains($blob, 'btn_add_schedule') && str_contains($blob, 'custom-ad_float.admin.sections.schedules')) {
            $schedSection = $child;
            break;
        }
    }
    expect('schedules section found', is_array($schedSection), true);
    $schedJson = json_encode($schedSection, JSON_UNESCAPED_UNICODE);

    expect('no Date.now in 예약 section', str_contains((string) $schedJson, 'Date.now'), false);
    expect('no nested form.schedules.{{sidx}} keys', str_contains((string) $schedJson, 'form.schedules.{{sidx}}'), false);
    expect('no optional form?.schedules', str_contains((string) $schedJson, 'form?.schedules'), false);
    expect('iteration uses || []', str_contains((string) $schedJson, '{{_local.form.schedules || []}}'), true);
    expect('card if is !sch._deleted', str_contains((string) $schedJson, '{{!sch._deleted}}'), true);

    $iterHasNullish = false;
    $addHasNullish = false;
    $cardHasNullish = false;
    $scan = function ($node) use (&$scan, &$iterHasNullish, &$addHasNullish, &$cardHasNullish): void {
        if (! is_array($node)) {
            return;
        }
        if (($node['id'] ?? '') === 'caf_sch_{{sidx}}') {
            $src = (string) ($node['iteration']['source'] ?? '');
            $iterHasNullish = str_contains($src, '??') || str_contains($src, '?.');
            $cardBlob = json_encode($node);
            $cardHasNullish = is_string($cardBlob) && str_contains($cardBlob, '??');
        }
        if (($node['id'] ?? '') === 'btn_add_schedule') {
            $blob = json_encode($node);
            $addHasNullish = is_string($blob) && (str_contains($blob, '??') || str_contains($blob, 'Date.now'));
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                $scan($v);
            }
        }
    };
    $scan($schedSection);
    expect('iteration source has no ?? / ?.', $iterHasNullish, false);
    expect('add button has no ?? / Date.now', $addHasNullish, false);
    expect('schedule cards have no ??', $cardHasNullish, false);
    expect('add concat uses schSeq', str_contains((string) $schedJson, "_local.schSeq || 0"), true);
    expect('add concat uses length', str_contains((string) $schedJson, '(_local.form.schedules || []).length + 1'), true);
    expect('add concat sets _deleted false', str_contains((string) $schedJson, '_deleted: false'), true);
    expect('add concat sets d0-d6', str_contains((string) $schedJson, 'd0: true, d1: true, d2: true, d3: true, d4: true, d5: true, d6: true'), true);
    expect('remove maps _deleted true', str_contains((string) $schedJson, '{_deleted: true}'), true);
    expect('field updates map by sch.id', str_contains((string) $schedJson, 'row.id === sch.id'), true);
    expect('init schSeq', ($layout['init_actions'][0]['params']['schSeq'] ?? null) === 0, true);

    $buttonsMissingType = 0;
    $walk = function ($node) use (&$walk, &$buttonsMissingType): void {
        if (! is_array($node)) {
            return;
        }
        if (($node['name'] ?? '') === 'Button') {
            $type = $node['props']['type'] ?? null;
            if ($type !== 'button') {
                $buttonsMissingType++;
            }
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                $walk($v);
            }
        }
    };
    $walk($schedSection);
    expect('every 예약 Button has type=button', $buttonsMissingType, 0);

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
