<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\InternalRedirect;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InternalRedirectTest extends TestCase
{
    #[DataProvider('safePaths')]
    public function test_accepts_paths_that_stay_on_the_current_host(string $path): void
    {
        self::assertTrue(InternalRedirect::isSafePath($path));
    }

    #[DataProvider('unsafePaths')]
    public function test_rejects_paths_that_can_change_the_host(string $path): void
    {
        self::assertFalse(InternalRedirect::isSafePath($path));
    }

    /** @return array<string, array{string}> */
    public static function safePaths(): array
    {
        return [
            'root' => ['/'],
            'path with query' => ['/products?comum_id=7'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function unsafePaths(): array
    {
        return [
            'protocol relative' => ['//evil.example/phish'],
            'backslash normalized by browsers' => ['/\\evil.example/phish'],
            'relative path' => ['products'],
        ];
    }
}
