<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $removed = $this->removeFixtures($this->fixtureNumbers());

        if ((bool) $this->option('remove')) {
            $this->info("Removed {$removed} local Consignment visual fixtures.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($hqId, $nodeId, $userId): void {
            $now = now();
            foreach (range(0, self::FIXTURE_COUNT - 1) as $index) {
                $status = self::STATUSES[$index % count(self::STATUSES)];
                $consignmentId = $this->fixtureUuid("consignment-{$index}");
                $number = sprintf('%s%06d', self::NUMBER_PREFIX, self::FIRST_NUMBER + $index);
                $isDetailFixture = $index === 0;
                $createdAt = $isDetailFixture
                    ? $now->copy()->subHours(4)
                    : $now->copy()->subHours(4)->subMinutes($index + 1);
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
                    'sender_latitude' => $isDetailFixture ? 35.7575 : null,
                    'sender_longitude' => $isDetailFixture ? 51.4090 : null,
                    'receiver_contact_name' => self::RECEIVER_NAMES[$index % count(self::RECEIVER_NAMES)],
                    'receiver_mobile' => self::RECEIVER_MOBILES[$index % count(self::RECEIVER_MOBILES)],
                    'receiver_phone' => null,
                    'receiver_address_text' => self::RECEIVER_ADDRESSES[$index % count(self::RECEIVER_ADDRESSES)],
                    'receiver_country' => 'ایران',
                    'receiver_state' => 'تهران',
                    'receiver_city' => 'تهران',
                    'receiver_postal_code' => null,
                    'receiver_latitude' => $isDetailFixture ? 35.7442 : null,
                    'receiver_longitude' => $isDetailFixture ? 51.5008 : null,
                    'service_type_id' => '00000000-0000-4000-8000-000000000101',
                    'shipping_method_id' => '00000000-0000-4000-8000-000000000201',
                    'pickup_commitment_at' => $isDetailFixture
                        ? $now->copy()->subHour()
                        : $now->copy()->addDay(),
                    'delivery_commitment_at' => $isDetailFixture
                        ? $now->copy()->addHours(16)
                        : $now->copy()->addDays(2),
                    'weight_kg' => $isDetailFixture ? 3.2 : 1 + ($index / 10),
                    'width_cm' => 20,
                    'length_cm' => 30,
                    'height_cm' => 15,
                    'declared_value_amount' => 2000000 + ($index * 100000),
                    'insurance_enabled' => $isDetailFixture,
                    'insurance_value_amount' => $isDetailFixture ? 2000000 : null,
                    'cod_enabled' => $isDetailFixture,
                    'cod_amount' => $isDetailFixture ? 2850000 : null,
                    'payer' => $isDetailFixture ? 'RECEIVER' : 'SENDER',
                    'payment_method' => $isDetailFixture ? 'COD' : 'CASH',
                    'current_status' => $status,
                    'version' => 1,
                    'created_at' => $createdAt,
                    'updated_at' => $now,
                ]);
                $parcelCount = $isDetailFixture ? 2 : 1;
                foreach (range(1, $parcelCount) as $parcelIndex) {
                    DB::table('parcels')->insert([
                        'parcel_id' => $this->fixtureUuid("consignment-{$index}-parcel-{$parcelIndex}"),
                        'hq_id' => $hqId,
                        'consignment_id' => $consignmentId,
                        'parcel_number' => sprintf('%s-%02d', $number, $parcelIndex),
                        'current_status' => $isDetailFixture && $parcelIndex === 1 ? 'PU' : $status,
                        'weight_kg' => $isDetailFixture ? ($parcelIndex === 1 ? 1.8 : 1.4) : 1 + ($index / 10),
                        'width_cm' => $isDetailFixture && $parcelIndex === 1 ? 18 : 20,
                        'length_cm' => $isDetailFixture && $parcelIndex === 1 ? 25 : 30,
                        'height_cm' => $isDetailFixture && $parcelIndex === 1 ? 10 : 15,
                        'created_at' => $createdAt,
                        'updated_at' => $now,
                    ]);
                }

                if ($isDetailFixture) {
                    $this->createDetailHistory(
                        $hqId,
                        $nodeId,
                        $userId,
                        $consignmentId,
                        $number,
                        $createdAt,
                        $now,
                    );
                }
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

    /** @param list<string> $fixtureNumbers */
    private function removeFixtures(array $fixtureNumbers): int
    {
        $ids = DB::table('consignments')
            ->where(function ($query) use ($fixtureNumbers): void {
                $query->where('consignment_number', 'like', self::LEGACY_PREFIX.'%')
                    ->orWhereIn('consignment_number', $fixtureNumbers);
            })
            ->pluck('consignment_id');
        if ($ids->isEmpty()) {
            return 0;
        }

        $this->dropFixtureCleanupTriggers();
        try {
            return DB::transaction(function () use ($ids): int {
                DB::table('audit_events')
                    ->where('target_type', 'CONSIGNMENT')
                    ->whereIn('target_id', $ids)
                    ->delete();
                $pricingIds = DB::table('consignment_pricing_versions')
                    ->whereIn('consignment_id', $ids)
                    ->pluck('pricing_version_id');
                DB::table('consignment_pricing_charge_lines')
                    ->whereIn('pricing_version_id', $pricingIds)
                    ->delete();
                DB::table('consignment_pricing_versions')->whereIn('consignment_id', $ids)->delete();
                DB::table('consignment_status_events')->whereIn('consignment_id', $ids)->delete();
                DB::table('parcels')->whereIn('consignment_id', $ids)->delete();

                return DB::table('consignments')->whereIn('consignment_id', $ids)->delete();
            });
        } finally {
            $this->restoreFixtureCleanupTriggers();
        }
    }

    private function dropFixtureCleanupTriggers(): void
    {
        foreach ([
            'consignment_pricing_versions_immutable_delete',
            'consignment_pricing_charge_lines_immutable_delete',
            'consignment_status_events_immutable_delete',
            'audit_events_prevent_delete',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function restoreFixtureCleanupTriggers(): void
    {
        foreach ([
            'consignment_pricing_versions',
            'consignment_pricing_charge_lines',
            'consignment_status_events',
        ] as $table) {
            DB::unprepared(
                "CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment history'",
            );
        }
        DB::unprepared(
            "CREATE TRIGGER audit_events_prevent_delete BEFORE DELETE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append-only'",
        );
    }

    private function createDetailHistory(
        string $hqId,
        string $nodeId,
        string $userId,
        string $consignmentId,
        string $number,
        \Illuminate\Support\Carbon $createdAt,
        \Illuminate\Support\Carbon $now,
    ): void {
        $pricingVersionId = $this->fixtureUuid('consignment-detail-pricing-v1');
        DB::table('consignment_pricing_versions')->insert([
            'pricing_version_id' => $pricingVersionId,
            'hq_id' => $hqId,
            'consignment_id' => $consignmentId,
            'version_number' => 1,
            'provider_code' => 'LEGACY_CORE',
            'quote_id' => $this->fixtureUuid('consignment-detail-quote'),
            'quote_version' => 1,
            'option_id' => $this->fixtureUuid('consignment-detail-option'),
            'external_method_code' => 'LOCAL-SANITIZED',
            'method_name' => 'سرویس پذیرفته‌شده محلی',
            'external_price_list_code' => null,
            'zone' => null,
            'currency' => 'IRR',
            'total_amount' => 530000,
            'min_ins' => 10000,
            'delivery_windows' => json_encode([], JSON_THROW_ON_ERROR),
            'input_fingerprint' => hash('sha256', 'local-detail-fixture'),
            'provider_calculated_at' => $createdAt->copy()->subMinutes(5),
            'accepted_at' => $createdAt,
            'accepted_by' => $userId,
        ]);
        foreach ([
            ['FREIGHT', 'کرایه حمل', 475000],
            ['INSURANCE', 'هزینه بیمه', 20000],
            ['OTHER', 'سایر هزینه‌های سرویس', 35000],
        ] as $lineIndex => [$code, $title, $amount]) {
            DB::table('consignment_pricing_charge_lines')->insert([
                'pricing_charge_line_id' => $this->fixtureUuid("consignment-detail-price-line-{$lineIndex}"),
                'hq_id' => $hqId,
                'pricing_version_id' => $pricingVersionId,
                'line_number' => $lineIndex + 1,
                'charge_code' => $code,
                'title' => $title,
                'amount' => $amount,
            ]);
        }

        $parcelOne = $this->fixtureUuid('consignment-0-parcel-1');
        $parcelTwo = $this->fixtureUuid('consignment-0-parcel-2');
        $events = [
            [null, 'D00', null, 'ثبت اولیه مرسوله در محیط محلی', $createdAt, null],
            ['D00', 'CFM', null, 'مرسوله پس از کنترل اطلاعات تأیید شد', $createdAt->copy()->addMinutes(20), null],
            ['CFM', 'PU', $parcelOne, 'بسته نخست در گره جمع‌آوری ثبت شد', $now->copy()->subHours(2), null],
            ['PU', 'IR', $parcelTwo, 'بسته دوم برای عملیات داخلی دریافت شد', $now->copy()->subMinutes(30), 'LOCAL_SCAN'],
        ];
        foreach ($events as $eventIndex => [$previous, $next, $parcelId, $note, $eventAt, $reason]) {
            DB::table('consignment_status_events')->insert([
                'status_event_id' => $this->fixtureUuid("consignment-detail-status-{$eventIndex}"),
                'hq_id' => $hqId,
                'consignment_id' => $consignmentId,
                'parcel_id' => $parcelId,
                'previous_status' => $previous,
                'new_status' => $next,
                'initiator_id' => $userId,
                'node_id' => $nodeId,
                'driver_id' => null,
                'latitude' => null,
                'longitude' => null,
                'manifest_id' => null,
                'reason_code' => $reason,
                'note' => $note,
                'created_at' => $eventAt,
            ]);
        }

        foreach ([
            ['CONSIGNMENT_CREATED', "مرسوله {$number} در داده نمایشی محلی ایجاد شد", $createdAt],
            ['CONSIGNMENT_STATUS_RECORDED', 'رویدادهای عملیاتی محلی بدون داده حساس ثبت شدند', $now->copy()->subMinutes(30)],
        ] as $auditIndex => [$action, $note, $auditAt]) {
            DB::table('audit_events')->insert([
                'audit_id' => $this->fixtureUuid("consignment-detail-audit-{$auditIndex}"),
                'hq_id' => $hqId,
                'initiator_id' => $userId,
                'action_key' => $action,
                'target_type' => 'CONSIGNMENT',
                'target_id' => $consignmentId,
                'before_snapshot' => null,
                'after_snapshot' => null,
                'safe_note' => $note,
                'ip_address_hash' => null,
                'user_agent_hash' => null,
                'source_client' => 'LOCAL_FIXTURE',
                'correlation_id' => $this->fixtureUuid("consignment-detail-correlation-{$auditIndex}"),
                'created_at' => $auditAt,
            ]);
        }
    }

    private function fixtureUuid(string $key): string
    {
        $hex = substr(hash('sha256', "chabok-local-consignment-fixture:{$key}"), 0, 32);

        return sprintf(
            '%s-%s-4%s-8%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            substr($hex, 17, 3),
            substr($hex, 20, 12),
        );
    }
}
