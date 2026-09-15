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

            public function exists(string $key): bool
            {
                return array_key_exists($key, $this->bag);
            }

            public function input(string $key, mixed $default = null): mixed
            {
                return $this->bag[$key] ?? $default;
            }

            public function merge(array $data): void
            {
                $this->bag = array_merge($this->bag, $data);
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

    expect('empty null', AdminPayload::normalizePlacements(null), []);
    expect('empty array', AdminPayload::normalizePlacements([]), []);
    expect('json string empty', AdminPayload::normalizePlacements('[]'), []);

    $one = AdminPayload::normalizePlacements([
        ['id' => 'left-main', 'position' => 'left', 'item_ids' => [3, 1, 1], 'enabled' => true],
    ]);
    expect('keeps sanitized id', $one[0]['id'] ?? null, 'left-main');
    expect('position left', $one[0]['position'] ?? null, 'left');
    expect('unique sorted item_ids', $one[0]['item_ids'] ?? null, [1, 3]);
    expect('fills width default', $one[0]['width_px'] ?? null, 180);
    expect('fills autoplay default', $one[0]['autoplay'] ?? null, true);

    $deleted = AdminPayload::normalizePlacements([
        ['id' => 'gone', 'position' => 'right', '_deleted' => true],
        ['id' => 'keep', 'position' => 'left'],
    ]);
    expect('skips deleted', array_column($deleted, 'id'), ['keep']);

    $ids = AdminPayload::normalizePlacements([
        ['id' => 'new'],
        ['id' => '!!!'],
        ['id' => 'p1'],
    ]);
    expect('new and invalid become pN without collision', array_column($ids, 'id'), ['p1', 'p2', 'p3']);

    $dup = AdminPayload::normalizePlacements([
        ['id' => 'side'],
        ['id' => 'side'],
    ]);
    expect('duplicate ids uniqued', array_column($dup, 'id'), ['side', 'p2']);

    $many = [];
    for ($i = 0; $i < 12; $i++) {
        $many[] = ['id' => 'w'.$i, 'position' => 'right'];
    }
    expect('caps at 8', count(AdminPayload::normalizePlacements($many)), 8);

    $disabled = AdminPayload::normalizePlacements([
        ['id' => 'off', 'enabled' => false],
        ['id' => 'on'],
    ]);
    expect('disabled stays in list', $disabled[0]['enabled'] ?? null, false);
    expect('missing enabled is on', $disabled[1]['enabled'] ?? null, true);

    $jsonIds = AdminPayload::normalizePlacements([
        ['id' => 'json', 'item_ids' => '[9,8]'],
    ]);
    expect('item_ids json string', $jsonIds[0]['item_ids'] ?? null, [8, 9]);

    $form = AdminPayload::placementToForm(['id' => 'box', 'item_ids' => [4, 5]], 0, ['position' => 'bottom']);
    expect('form id', $form['id'] ?? null, 'box');
    expect('form inherits base position', $form['position'] ?? null, 'bottom');
    expect('form sel flags', [$form['sel_4'] ?? null, $form['sel_5'] ?? null], [true, true]);
    expect('form not deleted', $form['_deleted'] ?? null, false);

    expect('sanitize new', AdminPayload::sanitizePlacementId('new', 0, []), 'p1');
    expect('sanitize junk', AdminPayload::sanitizePlacementId('@@', 3, []), 'p4');
    expect('sanitize collision', AdminPayload::sanitizePlacementId('keep', 0, ['keep' => true]), 'p1');
    expect('sanitize strips', AdminPayload::sanitizePlacementId('L/eft 1', 0, []), 'Left1');

    $req = new Request(['placements' => '{"id":"x"}']);
    AdminPayload::preparePlacementsRequest($req);
    expect('prepare non-array json becomes []', $req->input('placements'), []);

    $req2 = new Request(['placements' => json_encode([
        ['id' => 'a', 'width_px' => '', 'item_ids' => '[2,2]'],
    ])]);
    AdminPayload::preparePlacementsRequest($req2);
    $prepared = $req2->input('placements');
    expect('prepare decodes json array', is_array($prepared) && ($prepared[0]['id'] ?? null) === 'a', true);
    expect('prepare blanks numeric to null', array_key_exists('width_px', $prepared[0]) && $prepared[0]['width_px'] === null, true);
    expect('prepare decodes item_ids', $prepared[0]['item_ids'] ?? null, [2, 2]);

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
