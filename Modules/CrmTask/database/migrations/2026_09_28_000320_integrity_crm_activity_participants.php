<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Database-only invariants; see output/refactor/database-invariants.md for the required SQL-write guarantees.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_activity_participants` ADD CONSTRAINT `crm_activity_participants_party_xor` CHECK ((((`user_id` is not null) and (`customer_id` is null)) or ((`user_id` is null) and (`customer_id` is not null))))');
        DB::statement('ALTER TABLE `crm_activity_participants` ADD CONSTRAINT `crm_activity_participants_cost_scope_check` CHECK (((`customer_id` is null) or ((`hourly_cost` is null) and (`rate_as_of` is null))))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_activity_participants_person_insert` BEFORE INSERT ON `crm_activity_participants` FOR EACH ROW BEGIN IF NEW.customer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.customer_id AND c.hq_id=NEW.hq_id AND c.kind='PERSON') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A company is never selected as a participating person'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_activity_participants_person_update` BEFORE UPDATE ON `crm_activity_participants` FOR EACH ROW BEGIN IF NEW.customer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.customer_id AND c.hq_id=NEW.hq_id AND c.kind='PERSON') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A company is never selected as a participating person'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_activity_participants_person_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_activity_participants_person_update`');
        DB::statement('ALTER TABLE `crm_activity_participants` DROP CHECK `crm_activity_participants_cost_scope_check`');
        DB::statement('ALTER TABLE `crm_activity_participants` DROP CHECK `crm_activity_participants_party_xor`');
    }
};
