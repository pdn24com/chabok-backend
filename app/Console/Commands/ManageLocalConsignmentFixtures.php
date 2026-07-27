<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ManageLocalConsignmentFixtures extends Command
{
    private const LEGACY_PREFIX = 'CHB-LOCAL-VIS-';

    private const NUMBER_PREFIX = 'CHB-2406-';

    private const FIRST_NUMBER = 882016;

    private const FIXTURE_COUNT = 134;

    /** @var list<string> */
    private const STATUSES = [
        'IR', 'NOK', 'CFM', 'PU', 'OK', 'AA',
        'D00', 'PD', 'ROU', 'OF', 'OS', 'OD', 'NPU', 'RH', 'RCH', 'RO',
    ];

    /** @var list<string> */
    private const RECEIVER_NAMES = [
        'ح. کریمی',
        'ف. نوری',
        'م. حسینی',
        'ا. صادقی',
        'ز. اکبری',
        'ن. تهرانی',
        'س. محمدی',
        'ر. احمدی',
        'پ. مرادی',
        'ع. رضایی',
        'ک. جعفری',
        'د. رحیمی',
    ];

    /** @var list<string> */
    private const RECEIVER_ADDRESSES = [
        'نارمک، خ. گلبرگ غربی، پ. ۲۴',
        'پاسداران، بوستان ۲، پ. ۷',
        'تجریش، خ. ولیعصر، پ. ۸۸',
        'ولنجک، خ. سیزدهم، پ. ۲',
        'رشت، گلسار، بلوار دیلمان، پ. ۱۱',
        'شهرک غرب، ایران‌زمین، پ. ۴۵',
        'یوسف‌آباد، خ. شصت‌وچهارم، پ. ۱۲',
        'سعادت‌آباد، بلوار دریا، پ. ۳۰',
        'تهرانپارس، خ. جشنواره، پ. ۱۸',
        'ونک، خ. ملاصدرا، پ. ۶۱',
        'الهیه، خ. فرشته، پ. ۹',
        'پونک، بلوار همیلا، پ. ۲۷',
    ];

    /** @var list<string> */
    private const RECEIVER_MOBILES = [
        '09121104567',
        '09387762210',
        '09194420098',
        '09126671140',
        '09113095521',
        '09032184476',
        '09122804563',
        '09351247760',
        '09901124482',
        '09125903716',
        '09215548320',
        '09368412950',
    ];

    protected $signature = 'chabok:local-consignment-fixtures
        {--remove : Remove only the deterministic local visual fixtures}';

    protected $description = 'Create or remove local-only Consignment List visual acceptance fixtures';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is restricted to local and testing environments.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('consignments') || ! Schema::hasTable('parcels')) {
            $this->error('Run the database migrations before managing visual fixtures.');

            return self::FAILURE;
        }

        $hqId = (string) DB::table('hq_tenants')->where('hq_code', 'LOCAL-HQ')->value('hq_id');
        $nodeId = (string) DB::table('nodes')
            ->where('hq_id', $hqId)->where('node_code', 'LOCAL-BRANCH')->value('node_id');
        $userId = (string) DB::table('users')
            ->where('hq_id', $hqId)->where('normalized_username', 'admin')->value('user_id');
        if ($hqId === '' || $nodeId === '' || $userId === '') {
            $this->error('Create the local admin with chabok:local-user before managing visual fixtures.');

            return self::FAILURE;
        }

        DB::table('nodes')->where('node_id', $nodeId)->update(['node_title' => 'تهران مرکزی']);
        $fixtureNumbers = $this->fixtureNumbers();
        $removed = DB::transaction(function () use ($fixtureNumbers): int {
            $ids = DB::table('consignments')
                ->where(function ($query) use ($fixtureNumbers): void {
                    $query->where('consignment_number', 'like', self::LEGACY_PREFIX.'%')
                        ->orWhereIn('consignment_number', $fixtureNumbers);
                })
                ->pluck('consignment_id');
            DB::table('parcels')->whereIn('consignment_id', $ids)->delete();

            return DB::table('consignments')->whereIn('consignment_id', $ids)->delete();
        });

        if ((bool) $this->option('remove')) {
            $this->info("Removed {$removed} local Consignment visual fixtures.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($hqId, $nodeId, $userId): void {
            $now = now();
            foreach (range(0, self::FIXTURE_COUNT - 1) as $index) {
                $status = self::STATUSES[$index % count(self::STATUSES)];
                $consignmentId = (string) Str::uuid();
                $number = sprintf('%s%06d', self::NUMBER_PREFIX, self::FIRST_NUMBER + $index);
                DB::table('consignments')->insert([
                    'consignment_id' => $consignmentId,
                    'hq_id' => $hqId,
                    'consignment_number' => $number,
                    'initiator_id' => $userId,
                    'pickup_node_id' => $nodeId,
                    'delivery_node_id' => $index % 3 === 0 ? null : $nodeId,
                    'pickup_man_id' => null,
                    'delivery_man_id' => null,
                    'sender_id' => null,
                    'receiver_id' => null,
                    'sender_contact_name' => 'فروشگاه مرکزی چابک',
                    'sender_mobile' => '02188770000',
                    'sender_phone' => null,
                    'sender_address_text' => 'تهران، میدان ونک، گره تهران مرکزی',
                    'sender_country' => 'ایران',
                    'sender_state' => 'تهران',
                    'sender_city' => 'تهران',
                    'sender_postal_code' => null,
                    'sender_latitude' => null,
                    'sender_longitude' => null,
                    'receiver_contact_name' => self::RECEIVER_NAMES[$index % count(self::RECEIVER_NAMES)],
                    'receiver_mobile' => self::RECEIVER_MOBILES[$index % count(self::RECEIVER_MOBILES)],
                    'receiver_phone' => null,
                    'receiver_address_text' => self::RECEIVER_ADDRESSES[$index % count(self::RECEIVER_ADDRESSES)],
                    'receiver_country' => 'ایران',
                    'receiver_state' => 'تهران',
                    'receiver_city' => 'تهران',
                    'receiver_postal_code' => null,
                    'receiver_latitude' => null,
                    'receiver_longitude' => null,
                    'service_type_id' => '00000000-0000-4000-8000-000000000101',
                    'shipping_method_id' => '00000000-0000-4000-8000-000000000201',
                    'pickup_commitment_at' => $now->copy()->addDay(),
                    'delivery_commitment_at' => $now->copy()->addDays(2),
                    'weight_kg' => 1 + ($index / 10),
                    'width_cm' => 20,
                    'length_cm' => 30,
                    'height_cm' => 15,
                    'declared_value_amount' => 2000000 + ($index * 100000),
                    'insurance_enabled' => false,
                    'insurance_value_amount' => null,
                    'cod_enabled' => false,
                    'cod_amount' => null,
                    'payer' => 'SENDER',
                    'payment_method' => 'CASH',
                    'current_status' => $status,
                    'version' => 1,
                    'created_at' => $now->copy()->subMinutes($index),
                    'updated_at' => $now,
                ]);
                DB::table('parcels')->insert([
                    'parcel_id' => (string) Str::uuid(),
                    'hq_id' => $hqId,
                    'consignment_id' => $consignmentId,
                    'parcel_number' => $number.'-01',
                    'current_status' => $status,
                    'weight_kg' => 1 + ($index / 10),
                    'width_cm' => 20,
                    'length_cm' => 30,
                    'height_cm' => 15,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        $this->info('Created 134 LOCAL-HQ / LOCAL-BRANCH Consignment visual fixtures.');
        $this->line('Remove them with: php artisan chabok:local-consignment-fixtures --remove');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function fixtureNumbers(): array
    {
        return array_map(
            static fn (int $index): string => sprintf(
                '%s%06d',
                self::NUMBER_PREFIX,
                self::FIRST_NUMBER + $index,
            ),
            range(0, self::FIXTURE_COUNT - 1),
        );
    }
}
