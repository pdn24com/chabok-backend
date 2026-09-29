<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Geography\Domain\Support\PersianSearchNormalizer;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;
use RuntimeException;

final class IranGeographySeeder extends Seeder
{
    private const PROVINCES = [
        '1' => ['آذربایجان شرقی', 38.0962, 46.2738],
        '2' => ['آذربایجان غربی', 37.5522, 45.0761],
        '3' => ['اردبیل', 38.2498, 48.2933],
        '4' => ['اصفهان', 32.6613, 51.6804],
        '5' => ['البرز', 35.84, 50.9391],
        '6' => ['ایلام', 33.638, 46.4227],
        '7' => ['بوشهر', 28.9234, 50.8203],
        '8' => ['تهران', 35.6892, 51.389],
        '9' => ['خراسان جنوبی', 32.8663, 59.2211],
        '10' => ['خراسان رضوی', 36.297, 59.6062],
        '11' => ['خراسان شمالی', 37.4774, 57.3242],
        '12' => ['خوزستان', 31.3183, 48.6706],
        '13' => ['زنجان', 36.6736, 48.4787],
        '14' => ['سمنان', 35.579, 53.3948],
        '15' => ['سیستان و بلوچستان', 29.4963, 60.8629],
        '16' => ['فارس', 29.5918, 52.5836],
        '17' => ['قزوین', 36.2688, 50.0041],
        '18' => ['قم', 34.6399, 50.8759],
        '19' => ['کردستان', 35.3123, 46.9988],
        '20' => ['کرمان', 30.2839, 57.0834],
        '21' => ['کرمانشاه', 34.3142, 47.065],
        '22' => ['کهگیلویه و بویراحمد', 30.6684, 51.5879],
        '23' => ['لرستان', 33.4878, 48.3558],
        '24' => ['مازندران', 36.5653, 53.0588],
        '25' => ['مرکزی', 34.0971, 49.7013],
        '26' => ['هرمزگان', 27.1832, 56.2666],
        '27' => ['همدان', 34.7986, 48.5146],
        '28' => ['چهارمحال و بختیاری', 32.3264, 50.856],
        '29' => ['گلستان', 36.8427, 54.4436],
        '30' => ['گیلان', 37.2808, 49.5832],
        '31' => ['یزد', 31.8974, 54.3569],
    ];

    public function __construct(private readonly PersianSearchNormalizer $normalizer) {}

    public function run(): void
    {
        $source = dirname(__DIR__, 4).'/database/data/iran-cities.json';
        $raw = file_get_contents($source);
        if ($raw === false) {
            throw new RuntimeException("Unable to read Geography seed source: {$source}");
        }
        $responses = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($responses) || count($responses) !== 31) {
            throw new RuntimeException('Iran city seed source must contain exactly 31 response objects.');
        }
        $cities = [];
        $seenCodes = [];
        $seenProvinces = [];
        foreach ($responses as $response) {
            foreach ((array) ($response['objects'] ?? []) as $city) {
                $provinceCode = (string) ($city['state_no'] ?? '');
                $cityCode = (string) ($city['no'] ?? '');
                $name = trim((string) ($city['name'] ?? ''));
                if (! isset(self::PROVINCES[$provinceCode]) || $cityCode === '' || $name === '' || isset($seenCodes[$cityCode])) {
                    throw new RuntimeException("Invalid or duplicate Iran city seed record: {$cityCode}");
                }
                $seenCodes[$cityCode] = true;
                $seenProvinces[$provinceCode] = true;
                $cities[] = [$provinceCode, $cityCode, $name];
            }
        }
        if (count($cities) !== 2858 || count($seenProvinces) !== 31) {
            throw new RuntimeException('Iran city seed source must contain 2,858 cities across 31 provinces.');
        }
        DB::transaction(function () use ($cities): void {
            $now = now();
            $provinces = [];
            foreach (self::PROVINCES as $code => [$name, $latitude, $longitude]) {
                $provinces[] = [
                    'legacy_province_code' => (string) $code,
                    'name_fa' => $name,
                    'normalized_name' => $this->normalizer->normalize($name),
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            ProvinceRecord::query()->upsert($provinces, ['legacy_province_code'], ['name_fa', 'normalized_name', 'latitude', 'longitude', 'is_active', 'updated_at']);
            $provinceIds = ProvinceRecord::query()->pluck('id', 'legacy_province_code');
            foreach (array_chunk($cities, 500) as $chunk) {
                $rows = array_map(fn (array $city): array => [
                    'province_id' => $provinceIds[$city[0]],
                    'legacy_city_code' => $city[1],
                    'name_fa' => $city[2],
                    'normalized_name' => $this->normalizer->normalize($city[2]),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);
                CityRecord::query()->upsert($rows, ['legacy_city_code'], ['province_id', 'name_fa', 'normalized_name', 'is_active', 'updated_at']);
            }
        });
    }
}
