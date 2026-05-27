<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class ExtensionSelectorFixtures
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function byDomain(): array
    {
        static $fixtures = null;

        if ($fixtures !== null) {
            return $fixtures;
        }

        $path = database_path('fixtures/extension_selectors.json');

        if (! File::exists($path)) {
            return $fixtures = [];
        }

        return $fixtures = collect(json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR))
            ->mapWithKeys(fn (array $row): array => [strtolower($row['domain']) => $row])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function forDomain(string $domain): array
    {
        return self::byDomain()[strtolower($domain)] ?? [];
    }
}
