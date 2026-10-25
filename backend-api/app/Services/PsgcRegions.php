<?php

namespace App\Services;

/**
 * PSGC regions, keyed by the two-digit region prefix every PSGC city and
 * municipality code starts with. Read from the same bundled PSA snapshot
 * (psgc.gitlab.io) as the province and city seed files, so a city's region
 * comes from its official code rather than from its parent province — NCR
 * cities and independent cities sit under pseudo-provinces without one.
 */
class PsgcRegions
{
    private static ?array $regions = null;

    /** @return array<string, array{code: string, name: string, long_name: string}> */
    public static function all(): array
    {
        if (self::$regions === null) {
            $rows = json_decode(file_get_contents(database_path('data/psgc-regions.json')), true);
            self::$regions = [];
            foreach ($rows as $row) {
                $code = substr($row['code'], 0, 2);
                self::$regions[$code] = ['code' => $code, 'name' => $row['name'], 'long_name' => $row['regionName']];
            }
        }

        return self::$regions;
    }

    public static function codeForCity(?string $cityCode): ?string
    {
        return $cityCode !== null && preg_match('/^\d{9}$/', $cityCode) ? substr($cityCode, 0, 2) : null;
    }

    public static function name(?string $regionCode): ?string
    {
        return $regionCode === null ? null : (self::all()[$regionCode]['name'] ?? null);
    }
}
