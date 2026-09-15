<?php

declare(strict_types=1);

namespace {
    require dirname(__DIR__).'/src/Support/ImageUrl.php';

    use Modules\Custom\AdFloat\Support\ImageUrl;

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

    $media = fn (string $rel) => '/api/modules/custom-ad_float/media/'.$rel;

    expect(
        'store() relative path uses module media URL',
        ImageUrl::publicUrl('custom-ad-float/abc.jpg'),
        $media('custom-ad-float/abc.jpg')
    );
    expect(
        'storage web path is rewritten to media (no APP_URL host)',
        ImageUrl::publicUrl('/storage/custom-ad-float/abc.jpg'),
        $media('custom-ad-float/abc.jpg')
    );
    expect(
        'storage/ prefix without slash',
        ImageUrl::publicUrl('storage/custom-ad-float/abc.jpg'),
        $media('custom-ad-float/abc.jpg')
    );
    expect(
        'full laravel disk path',
        ImageUrl::publicUrl('storage/app/public/custom-ad-float/abc.jpg'),
        $media('custom-ad-float/abc.jpg')
    );
    expect(
        'NAS/unix filesystem path',
        ImageUrl::publicUrl('/volume1/web/3ds/storage/app/public/custom-ad-float/abc.jpg'),
        $media('custom-ad-float/abc.jpg')
    );
    expect(
        'windows UNC-style path',
        ImageUrl::publicUrl('\\\\NAS\\web\\3ds\\storage\\app\\public\\custom-ad-float\\abc.jpg'),
        $media('custom-ad-float/abc.jpg')
    );
    expect(
        'https URL mode unchanged',
        ImageUrl::publicUrl('https://cdn.example.com/ad.png'),
        'https://cdn.example.com/ad.png'
    );
    expect(
        'http URL mode unchanged',
        ImageUrl::publicUrl('http://example.com/a.jpg'),
        'http://example.com/a.jpg'
    );
    expect(
        'protocol-relative URL mode unchanged',
        ImageUrl::publicUrl('//cdn.example.com/ad.png'),
        '//cdn.example.com/ad.png'
    );
    expect(
        'site-relative non-storage URL unchanged',
        ImageUrl::publicUrl('/images/banner.png'),
        '/images/banner.png'
    );
    expect('empty path', ImageUrl::publicUrl(''), '');
    expect(
        'does not double-prefix storage',
        ImageUrl::publicUrl('storage/custom-ad-float/x.webp'),
        $media('custom-ad-float/x.webp')
    );
    expect(
        'storageUrl is relative /storage path',
        ImageUrl::storageUrl('custom-ad-float/abc.jpg'),
        '/storage/custom-ad-float/abc.jpg'
    );
    expect(
        'storageUrl empty for remote URL',
        ImageUrl::storageUrl('https://cdn.example.com/ad.png'),
        ''
    );
    expect(
        'rejects path traversal',
        ImageUrl::isSafePublicRelativePath('custom-ad-float/../secret.jpg'),
        false
    );
    expect(
        'rejects other folders',
        ImageUrl::isSafePublicRelativePath('other/file.jpg'),
        false
    );
    expect(
        'accepts upload relative',
        ImageUrl::isSafePublicRelativePath('custom-ad-float/abc.jpg'),
        true
    );
    expect(
        'diskRelativePath null for https',
        ImageUrl::diskRelativePath('https://x.test/a.png'),
        null
    );
    expect(
        'filename with space is encoded',
        ImageUrl::publicUrl('custom-ad-float/my ad.jpg'),
        $media('custom-ad-float/my%20ad.jpg')
    );
    $uploadUrl = ImageUrl::publicUrl('custom-ad-float/x.jpg');
    expect('upload url is root-relative', str_starts_with($uploadUrl, '/'), true);
    expect('upload url has no scheme/host', str_contains($uploadUrl, '://'), false);
    expect(
        'https URL that happens to contain storage/app/public is unchanged',
        ImageUrl::publicUrl('https://cdn.example.com/storage/app/public/custom-ad-float/x.jpg'),
        'https://cdn.example.com/storage/app/public/custom-ad-float/x.jpg'
    );

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
