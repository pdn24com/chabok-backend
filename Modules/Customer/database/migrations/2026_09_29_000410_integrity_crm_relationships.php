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

        DB::statement('ALTER TABLE `crm_relationships` ADD CONSTRAINT `crm_relationships_validity_window_check` CHECK (((`valid_from` is null) or (`valid_to` is null) or (`valid_to` >= `valid_from`)))');
        // MySQL cannot express a partial unique index, so "one active primary contact per company" is
        // enforced through a stored generated column that is NULL for every other row. A generated column
        // must be deterministic, so the slot is held by an open-ended primary relation; closing a relation
        // by setting valid_to releases it. Honouring a future valid_to stays a write-path check.
        // Zero primary contacts stays allowed.
        DB::statement('ALTER TABLE `crm_relationships` ADD COLUMN `active_primary_company_id` int unsigned GENERATED ALWAYS AS (if(((`is_primary` = 1) and (`valid_to` is null)), `company_customer_id`, NULL)) STORED');
        DB::statement('ALTER TABLE `crm_relationships` ADD UNIQUE INDEX `crm_relationships_active_primary_unique` (`hq_id`, `active_primary_company_id`)');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_relationships_endpoint_kind_insert` BEFORE INSERT ON `crm_relationships` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.person_customer_id AND c.hq_id=NEW.hq_id AND c.kind='PERSON') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Relationship person end must be a PERSON record'; END IF; IF NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.company_customer_id AND c.hq_id=NEW.hq_id AND c.kind='COMPANY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Relationship company end must be a COMPANY record'; END IF; IF NEW.position_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_positions p JOIN crm_customer_departments d ON d.id=p.department_id AND d.hq_id=p.hq_id WHERE p.id=NEW.position_id AND p.hq_id=NEW.hq_id AND d.company_customer_id=NEW.company_customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Position must belong to the same company'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_relationships_endpoint_kind_update` BEFORE UPDATE ON `crm_relationships` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.person_customer_id AND c.hq_id=NEW.hq_id AND c.kind='PERSON') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Relationship person end must be a PERSON record'; END IF; IF NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.company_customer_id AND c.hq_id=NEW.hq_id AND c.kind='COMPANY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Relationship company end must be a COMPANY record'; END IF; IF NEW.position_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_positions p JOIN crm_customer_departments d ON d.id=p.department_id AND d.hq_id=p.hq_id WHERE p.id=NEW.position_id AND p.hq_id=NEW.hq_id AND d.company_customer_id=NEW.company_customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Position must belong to the same company'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_relationships_endpoint_kind_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_relationships_endpoint_kind_insert`');
        DB::statement('ALTER TABLE `crm_relationships` DROP INDEX `crm_relationships_active_primary_unique`');
        DB::statement('ALTER TABLE `crm_relationships` DROP COLUMN `active_primary_company_id`');
        DB::statement('ALTER TABLE `crm_relationships` DROP CHECK `crm_relationships_validity_window_check`');
    }
};
