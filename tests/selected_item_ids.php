<?php

declare(strict_types=1);

namespace Illuminate\Http {
    if (! class_exists(Request::class)) {
        class Request
        {
            /** @param array<string, mixed> $bag */
            public function __construct(private array $bag = [])
            {
            }

            /** @return array<string, mixed> */
            public function all(): array
            {
                return $this->bag;
            }

            public function input(string $key, mixed $default = null): mixed
            {
                return $this->bag[$key] ?? $default;
            }
        }
    }
}

namespace {
    require dirname(__DIR__).'/src/Support/AdminPayload.php';

    use Illuminate\Http\Request;
    use Modules\Custom\AdFloat\Support\AdminPayload;

    $failed = 0;
    $passed = 0;

    function expect(string $label, array $actual, array $expected): void
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

    expect('array item_ids', AdminPayload::selectedItemIds([1, 2, 3]), [1, 2, 3]);
    expect('json string item_ids', AdminPayload::selectedItemIds('[4,5]'), [4, 5]);
    expect('csv ids', AdminPayload::selectedItemIds('6, 7, 8'), [6, 7, 8]);
    expect('single numeric', AdminPayload::selectedItemIds(9), [9]);
    expect('sels object', AdminPayload::selectedItemIds(null, ['1' => true, '2' => false, '3' => 'true']), [1, 3]);
    expect('sels json string', AdminPayload::selectedItemIds(null, '{"10":true,"11":"1"}'), [10, 11]);
    expect('sel_ keys in sels', AdminPayload::selectedItemIds(null, ['sel_12' => true, 'sel_13' => 0]), [12]);
    expect('union ids + sels', AdminPayload::selectedItemIds([1], ['2' => true]), [1, 2]);
    expect('empty', AdminPayload::selectedItemIds(null, null), []);
    expect('empty array', AdminPayload::selectedItemIds([], []), []);
    expect('template leftover', AdminPayload::selectedItemIds('{{_local.itemIds ?? []}}'), []);
    expect('id 1 in list not dropped', AdminPayload::selectedItemIds([1, 2]), [1, 2]);
    expect('item_ids flag map', AdminPayload::selectedItemIds(['1' => true, '2' => false, '4' => true]), [1, 4]);
    expect('item_ids json object', AdminPayload::selectedItemIds('{"1":true,"8":true}'), [1, 8]);

    expect(
        'bag item_ids',
        AdminPayload::selectedItemIdsFromBag(['item_ids' => [20, 21]]),
        [20, 21]
    );
    expect(
        'bag itemIds alias',
        AdminPayload::selectedItemIdsFromBag(['itemIds' => [22]]),
        [22]
    );
    expect(
        'bag csv ids',
        AdminPayload::selectedItemIdsFromBag(['ids' => '23,24']),
        [23, 24]
    );
    expect(
        'bag json item_ids',
        AdminPayload::selectedItemIdsFromBag(['item_ids' => '[25,26]']),
        [25, 26]
    );
    expect(
        'bag top-level sel_',
        AdminPayload::selectedItemIdsFromBag(['sel_27' => true, 'sel_28' => '1', 'title' => 'x']),
        [27, 28]
    );
    expect(
        'bag sels json',
        AdminPayload::selectedItemIdsFromBag(['sels' => '{"29":true}']),
        [29]
    );
    expect(
        'request aliases',
        AdminPayload::selectedItemIdsFromRequest(new Request(['item_ids' => [30, 31], 'ids' => '32'])),
        [30, 31, 32]
    );

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
