<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use Modules\Customer\Application\Dto\CustomerAddressChangesDto;
use Modules\Customer\Application\Dto\CustomerAddressDraftDto;
use Modules\Customer\Application\Validators\CustomerAddressValidator;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Application\Repositories\CountryRepositoryInterface;
use Modules\Geography\Application\Repositories\ProvinceRepositoryInterface;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Geography\Infrastructure\Persistence\Models\CountryRecord;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The address-book rules, judged without a database: the merge semantics and the entry validator. */
final class CustomerAddressEntryTest extends TestCase
{
    public function test_a_change_leaves_every_field_it_does_not_name_alone(): void
    {
        $merged = (new CustomerAddressChangesDto(purpose: 'BILLING'))->applyTo($this->stored());

        self::assertSame('BILLING', $merged->purpose);
        self::assertSame('IR', $merged->countryCode);
        self::assertSame('1', $merged->provinceId);
        self::assertSame('1', $merged->cityId);
        self::assertSame('1234567890', $merged->postalCode);
        self::assertSame('12', $merged->plaque);
        self::assertSame('3', $merged->unit);
        self::assertSame('35.7000000', $merged->latitude);
        self::assertTrue($merged->isDefault);
    }

    public function test_an_explicit_null_clears_a_field_while_an_absent_key_does_not(): void
    {
        $cleared = (new CustomerAddressChangesDto(unitSpecified: true, coordinatesSpecified: true))->applyTo($this->stored());

        self::assertNull($cleared->unit);
        self::assertNull($cleared->latitude);
        self::assertNull($cleared->longitude);
        self::assertSame('12', $cleared->plaque);

        $untouched = (new CustomerAddressChangesDto)->applyTo($this->stored());
        self::assertSame('3', $untouched->unit);
        self::assertSame('35.7000000', $untouched->latitude);
    }

    public function test_a_change_that_names_nothing_is_recognised_as_empty(): void
    {
        self::assertTrue((new CustomerAddressChangesDto)->touchesNothing());
        self::assertFalse((new CustomerAddressChangesDto(purpose: 'BILLING'))->touchesNothing());
        // Clearing a field is a change, even though the value beside the flag is null.
        self::assertFalse((new CustomerAddressChangesDto(unitSpecified: true))->touchesNothing());
        self::assertFalse((new CustomerAddressChangesDto(coordinatesSpecified: true))->touchesNothing());
        self::assertFalse((new CustomerAddressChangesDto(isDefault: false))->touchesNothing());
    }

    public function test_an_iranian_entry_is_accepted_without_a_province_or_a_city(): void
    {
        $this->validator()->validateEntry(new CustomerAddressDraftDto('IR', 'MAIN', 'نشانی بدون شهر'));

        $this->expectNotToPerformAssertions();
    }

    public function test_an_iranian_entry_keeps_its_reference_city_inside_the_named_province(): void
    {
        $this->validator()->validateEntry(new CustomerAddressDraftDto('IR', 'MAIN', 'نشانی', provinceId: '1', cityId: '1'));

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('incoherentEntries')]
    public function test_an_incoherent_entry_names_the_field_that_is_wrong(CustomerAddressDraftDto $address, string $field, string $messageKey): void
    {
        try {
            $this->validator()->validateEntry($address);
            self::fail('The entry should have been refused.');
        } catch (ApiException $exception) {
            self::assertSame([$field => [$messageKey]], $exception->fieldErrors);
            self::assertSame(422, $exception->httpStatus);
        }
    }

    public static function incoherentEntries(): array
    {
        return [
            'unknown country' => [
                new CustomerAddressDraftDto('ZZ', 'MAIN', 'نشانی'),
                'country_code', 'customer.select_active_country',
            ],
            'foreign region inside Iran' => [
                new CustomerAddressDraftDto('IR', 'MAIN', 'نشانی', foreignRegion: 'امارت دبی'),
                'foreign_region', 'customer.foreign_region_requires_foreign_country',
            ],
            'foreign city inside Iran' => [
                new CustomerAddressDraftDto('IR', 'MAIN', 'نشانی', foreignCity: 'دبی'),
                'foreign_city', 'customer.foreign_city_requires_foreign_country',
            ],
            'reference province abroad' => [
                new CustomerAddressDraftDto('AE', 'MAIN', 'نشانی', provinceId: '1'),
                'province_id', 'customer.canonical_province_requires_iran',
            ],
            'reference city abroad' => [
                new CustomerAddressDraftDto('AE', 'MAIN', 'نشانی', cityId: '1'),
                'city_id', 'customer.canonical_city_requires_iran',
            ],
            'inactive province' => [
                new CustomerAddressDraftDto('IR', 'MAIN', 'نشانی', provinceId: '9'),
                'province_id', 'customer.select_active_province_from_reference_data',
            ],
            'unknown city' => [
                new CustomerAddressDraftDto('IR', 'MAIN', 'نشانی', provinceId: '1', cityId: '9'),
                'city_id', 'geography.select_active_city_from_reference_data',
            ],
            'city of another province' => [
                new CustomerAddressDraftDto('IR', 'MAIN', 'نشانی', provinceId: '2', cityId: '1'),
                'province_id', 'customer.select_active_province_for_city',
            ],
            'city without its province' => [
                new CustomerAddressDraftDto('IR', 'MAIN', 'نشانی', cityId: '1'),
                'province_id', 'customer.select_active_province_for_city',
            ],
            'postal code of nine digits' => [
                new CustomerAddressDraftDto('IR', 'MAIN', 'نشانی', postalCode: '123456789'),
                'postal_code', 'customer.iranian_postal_code_has_ten_digits',
            ],
        ];
    }

    public function test_a_postal_code_abroad_keeps_the_spelling_of_its_own_country(): void
    {
        $this->validator()->validateEntry(new CustomerAddressDraftDto('AE', 'MAIN', 'نشانی', postalCode: 'AE-1234'));

        $this->expectNotToPerformAssertions();
    }

    /** The entry as it sits in the database, so a change is merged into real stored values. */
    private function stored(): CustomerAddressDraftDto
    {
        $record = new CustomerAddressRecord;
        $record->setRawAttributes([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'country_code' => 'IR', 'purpose' => 'MAIN',
            'address_text' => 'تهران، نشانی نمایشی', 'province_id' => 1, 'city_id' => 1,
            'foreign_region' => null, 'foreign_city' => null, 'postal_code' => '1234567890',
            'plaque' => '12', 'unit' => '3', 'latitude' => '35.7000000', 'longitude' => '51.4000000',
            'is_default' => 1,
        ], sync: true);

        return CustomerAddressDraftDto::fromRecord($record);
    }

    private function validator(): CustomerAddressValidator
    {
        $countries = Mockery::mock(CountryRepositoryInterface::class);
        $countries->shouldReceive('findActiveByCode')
            ->andReturnUsing(fn (string $code): ?CountryRecord => in_array($code, ['IR', 'AE'], true) ? new CountryRecord : null);

        $provinces = Mockery::mock(ProvinceRepositoryInterface::class);
        $provinces->shouldReceive('activeExists')->andReturnUsing(fn (string $id): bool => in_array($id, ['1', '2'], true));

        $cities = Mockery::mock(CityRepositoryInterface::class);
        $cities->shouldReceive('findWithProvince')->andReturnUsing(fn (string $id): ?CityRecord => $id === '1' ? $this->tehran() : null);

        return new CustomerAddressValidator($countries, $cities, $provinces);
    }

    private function tehran(): CityRecord
    {
        $province = new ProvinceRecord;
        $province->setRawAttributes(['id' => 1, 'is_active' => 1], sync: true);
        $city = new CityRecord;
        $city->setRawAttributes(['id' => 1, 'province_id' => 1, 'is_active' => 1], sync: true);

        return $city->setRelation('province', $province);
    }
}
