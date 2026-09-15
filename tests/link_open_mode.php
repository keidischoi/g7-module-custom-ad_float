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

    expect('blank mode + open_new_tab true → new_tab', AdminPayload::normalizeLinkOpenMode(null, true), 'new_tab');
    expect('blank mode + open_new_tab false → same', AdminPayload::normalizeLinkOpenMode(null, false), 'same');
    expect('blank mode + open_new_tab 0 → same', AdminPayload::normalizeLinkOpenMode('', '0'), 'same');
    expect('default when both missing', AdminPayload::normalizeLinkOpenMode(null, null), 'new_tab');
    expect('explicit modal wins over open_new_tab true', AdminPayload::normalizeLinkOpenMode('modal', true), 'modal');
    expect('explicit same', AdminPayload::normalizeLinkOpenMode('same', true), 'same');
    expect('alias popup → modal', AdminPayload::normalizeLinkOpenMode('popup', null), 'modal');
    expect('alias _blank → new_tab', AdminPayload::normalizeLinkOpenMode('_blank', false), 'new_tab');

    $coerced = AdminPayload::coerceSettingFlags(['open_new_tab' => false]);
    expect('coerce derives same + open_new_tab false', $coerced['link_open_mode'] ?? null, 'same');
    expect('coerce open_new_tab follows mode', $coerced['open_new_tab'] ?? null, false);

    $modal = AdminPayload::coerceSettingFlags(['link_open_mode' => 'modal', 'open_new_tab' => true]);
    expect('coerce modal keeps modal', $modal['link_open_mode'] ?? null, 'modal');
    expect('coerce modal derives open_new_tab false', $modal['open_new_tab'] ?? null, false);

    $pinned = AdminPayload::overlayVisual(
        ['link_open_mode' => 'modal', 'open_new_tab' => false, 'position' => 'right', 'show_close' => true],
        ['open_new_tab' => true, 'link_open_mode' => 'same', 'width_px' => 90]
    );
    expect('overlay pins base modal', $pinned['link_open_mode'] ?? null, 'modal');
    expect('overlay derived open_new_tab stays false', $pinned['open_new_tab'] ?? null, false);
    expect('overlay still applies other visuals', $pinned['width_px'] ?? null, 90);

    $windows = AdminPayload::windowsFromMatchingSchedules([
        [
            'enabled' => true,
            'position' => 'left',
            'item_ids' => [1],
            'weekdays' => [],
            'link_open_mode' => 'same',
            'open_new_tab' => false,
        ],
    ], [
        'enabled' => true,
        'position' => 'right',
        'link_open_mode' => 'modal',
        'open_new_tab' => false,
        'show_close' => true,
    ]);
    expect('windows pin base link_open_mode', $windows[0]['settings']['link_open_mode'] ?? null, 'modal');

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
