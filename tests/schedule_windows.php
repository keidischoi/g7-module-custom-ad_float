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

    $tueNoon = new DateTimeImmutable('2026-09-15 12:00:00'); // Tuesday = 2

    $left = [
        'enabled' => true,
        'position' => 'left',
        'item_ids' => [1, 2],
        'weekdays' => [],
        'start_at' => '2026-09-01T00:00:00',
        'end_at' => '2026-09-30T23:59:59',
        'width_px' => 120,
    ];
    $right = [
        'enabled' => true,
        'position' => 'right',
        'item_ids' => [3],
        'weekdays' => [],
        'start_at' => '2026-09-01T00:00:00',
        'end_at' => '2026-09-30T23:59:59',
        'width_px' => 200,
    ];
    $leftLater = [
        'enabled' => true,
        'position' => 'left',
        'item_ids' => [2, 9],
        'weekdays' => [],
        'start_at' => '2026-09-01T00:00:00',
        'end_at' => '2026-09-30T23:59:59',
        'width_px' => 999,
    ];
    $disabled = [
        'enabled' => false,
        'position' => 'top',
        'item_ids' => [8],
        'weekdays' => [],
        'start_at' => '2026-09-01T00:00:00',
        'end_at' => '2026-09-30T23:59:59',
    ];
    $weekdayMismatch = [
        'enabled' => true,
        'position' => 'bottom',
        'item_ids' => [7],
        'weekdays' => [0], // Sunday only
        'start_at' => '2026-09-01T00:00:00',
        'end_at' => '2026-09-30T23:59:59',
    ];
    $future = [
        'enabled' => true,
        'position' => 'top',
        'item_ids' => [6],
        'weekdays' => [],
        'start_at' => '2026-12-01T00:00:00',
        'end_at' => '2026-12-31T23:59:59',
    ];

    $matches = AdminPayload::matchingSchedules(
        [$left, $disabled, $right, $weekdayMismatch, $future, $leftLater],
        $tueNoon
    );
    expect('matches left+right+leftLater only', array_column($matches, 'position'), ['left', 'right', 'left']);
    expect('first matching is left', (AdminPayload::firstMatchingSchedule([$left, $right], $tueNoon)['position'] ?? null), 'left');
    expect('no match when all future', AdminPayload::matchingSchedules([$future], $tueNoon), []);

    $base = array_merge(AdminPayload::visualDefaults(), [
        'enabled' => true,
        'home_only' => true,
        'position' => 'right',
        'close_cookie_key' => 'g7_custom_ad_float_closed',
    ]);

    $windows = AdminPayload::windowsFromMatchingSchedules($matches, $base);
    expect('one window per position', array_column($windows, 'id'), ['left', 'right']);
    expect('left visuals from first left row', $windows[0]['settings']['width_px'] ?? null, 120);
    expect('left items merged unique', $windows[0]['item_ids'], [1, 2, 9]);
    expect('right keeps own items', $windows[1]['item_ids'], [3]);
    expect('right width from right row', $windows[1]['settings']['width_px'] ?? null, 200);

    $emptyUnion = AdminPayload::windowsFromMatchingSchedules([
        ['enabled' => true, 'position' => 'left', 'item_ids' => [1], 'weekdays' => []],
        ['enabled' => true, 'position' => 'left', 'item_ids' => [], 'weekdays' => []],
    ], $base);
    expect('empty item_ids means all ads at that position', $emptyUnion[0]['item_ids'], []);

    $inherit = AdminPayload::windowsFromMatchingSchedules([
        ['enabled' => true, 'item_ids' => [4], 'weekdays' => []],
    ], $base);
    expect('missing position inherits base', $inherit[0]['id'] ?? null, 'right');

    $baseCloseOff = array_merge($base, ['show_close' => false]);
    $scheduleCloseOn = [
        'enabled' => true,
        'position' => 'left',
        'item_ids' => [1],
        'weekdays' => [],
        'show_close' => true,
    ];
    $pinnedOff = AdminPayload::windowsFromMatchingSchedules([$scheduleCloseOn], $baseCloseOff);
    expect('schedule show_close true does not override base OFF', $pinnedOff[0]['settings']['show_close'] ?? null, false);

    $baseCloseOn = array_merge($base, ['show_close' => true]);
    $scheduleCloseOff = [
        'enabled' => true,
        'position' => 'right',
        'item_ids' => [2],
        'weekdays' => [],
        'show_close' => false,
    ];
    $pinnedOn = AdminPayload::windowsFromMatchingSchedules([$scheduleCloseOff], $baseCloseOn);
    expect('schedule show_close false does not override base ON', $pinnedOn[0]['settings']['show_close'] ?? null, true);

    $direct = AdminPayload::overlayVisual(['show_close' => false, 'position' => 'right'], ['show_close' => true, 'width_px' => 90]);
    expect('overlayVisual pins base show_close', $direct['show_close'] ?? null, false);
    expect('overlayVisual still applies other schedule visuals', $direct['width_px'] ?? null, 90);

    $added = [
        'id' => 's1_1',
        'enabled' => true,
        'start_date' => '',
        'start_time' => '',
        'end_date' => '',
        'end_time' => '',
        'd0' => true,
        'd1' => true,
        'd2' => true,
        'd3' => true,
        'd4' => true,
        'd5' => true,
        'd6' => true,
        '_deleted' => false,
        'position' => 'right',
    ];
    $kept = AdminPayload::normalizeSchedules([$added]);
    expect('예약 추가 row survives normalize', count($kept), 1);
    expect('preserves admin row id', $kept[0]['id'] ?? null, 's1_1');
    expect('preserves position', $kept[0]['position'] ?? null, 'right');
    expect('all-days weekdays stored empty', $kept[0]['weekdays'] ?? null, []);

    $softDeleted = AdminPayload::normalizeSchedules([
        array_merge($added, ['_deleted' => true]),
        array_merge($added, ['id' => 's2_2', '_deleted' => false]),
    ]);
    expect('soft-deleted row dropped, other kept', array_column($softDeleted, 'id'), ['s2_2']);

    $identityOnly = AdminPayload::normalizeSchedules([
        ['id' => 'n1', 'enabled' => true],
        [],
    ]);
    expect('id/enabled-only row kept, empty junk dropped', array_column($identityOnly, 'id'), ['n1']);

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
