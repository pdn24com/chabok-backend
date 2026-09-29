<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Geography\Domain\Support\PersianSearchNormalizer;
use Modules\Geography\Infrastructure\Persistence\Models\CountryRecord;
use RuntimeException;

final class CountrySeeder extends Seeder
{
    public function __construct(private readonly PersianSearchNormalizer $normalizer) {}

    public function run(): void
    {
        $source = dirname(__DIR__, 4).'/database/data/countries.json';
        $raw = file_get_contents($source);
        if ($raw === false) {
            throw new RuntimeException("Unable to read Geography seed source: {$source}");
        }
        $countries = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($countries) || ! array_is_list($countries) || count($countries) !== 249) {
            throw new RuntimeException('Country seed source must contain exactly 249 ISO 3166-1 countries and territories.');
        }

        $rows = [];
        $seen = [];
        $now = now();
        foreach ($countries as $country) {
            if (! is_array($country)) {
                throw new RuntimeException('Invalid country seed record.');
            }
            foreach (['country_code' => '/^[A-Z]{2}$/D', 'alpha3_code' => '/^[A-Z]{3}$/D', 'numeric_code' => '/^[0-9]{3}$/D'] as $field => $pattern) {
                $value = $country[$field] ?? null;
                if (! is_string($value) || ! preg_match($pattern, $value) || isset($seen[$field][$value])) {
                    throw new RuntimeException("Invalid or duplicate country seed field: {$field}");
                }
                $seen[$field][$value] = true;
            }
            foreach (['name_fa', 'name_en'] as $field) {
                $value = $country[$field] ?? null;
                if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 160) {
                    throw new RuntimeException("Invalid country seed field: {$field}");
                }
            }
            $rows[] = [
                'country_code' => $country['country_code'],
                'alpha3_code' => $country['alpha3_code'],
                'numeric_code' => $country['numeric_code'],
                'name_fa' => $country['name_fa'],
                'name_en' => $country['name_en'],
                'normalized_name' => $this->normalizer->normalize($country['name_fa']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(fn () => CountryRecord::query()->upsert($rows, ['country_code'], [
            'alpha3_code', 'numeric_code', 'name_fa', 'name_en', 'normalized_name', 'updated_at',
        ]));
    }
}
