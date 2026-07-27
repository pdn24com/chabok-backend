<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ManageLocalConsignmentFixtures extends Command
{
    private const PREFIX = 'CHB-LOCAL-VIS-';

    /** @var list<string> */
    private const STATUSES = [
        'D00', 'CFM', 'PD', 'PU', 'IR', 'ROU', 'OF', 'OS',
        'OD', 'OK', 'NPU', 'NOK', 'RH', 'RCH', 'RO', 'AA',
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

        $removed = DB::transaction(function (): int {
            $ids = DB::table('consignments')
                ->where('consignment_number', 'like', self::PREFIX.'%')
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
            foreach (self::STATUSES as $index => $status) {
                $consignmentId = (string) Str::uuid();
                $number = self::PREFIX.$status;
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
                    'sender_contact_name' => 'فروشگاه محلی چابک',
                    'sender_mobile' => '09120000001',
                    'sender_phone' => null,
                    'sender_address_text' => 'تهران، شعبه محلی چابک',
                    'sender_country' => 'ایران',
                    'sender_state' => 'تهران',
                    'sender_city' => 'تهران',
                    'sender_postal_code' => null,
                    'sender_latitude' => null,
                    'sender_longitude' => null,
                    'receiver_contact_name' => 'گیرنده نمونه '.($index + 1),
                    'receiver_mobile' => sprintf('0912000%04d', $index + 2),
                    'receiver_phone' => null,
                    'receiver_address_text' => 'تهران، خیابان نمونه، پلاک '.($index + 10),
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

        $this->info('Created 16 LOCAL-HQ / LOCAL-BRANCH Consignment visual fixtures.');
        $this->line('Remove them with: php artisan chabok:local-consignment-fixtures --remove');

        return self::SUCCESS;
    }
}
