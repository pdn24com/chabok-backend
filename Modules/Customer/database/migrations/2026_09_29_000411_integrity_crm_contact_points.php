<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Database-only invariants; see output/refactor/database-invariants.md for the required SQL-write guarantees.
// Ownership of a normalised mobile number is deliberately NOT a unique index here: a plain UNIQUE on
// normalized_value does not cover every channel, and one person may reuse the same number across MOBILE and
// several messengers. That rule stays an atomic, locked write; a read before insert does not survive a race.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_contact_points` ADD CONSTRAINT `crm_contact_points_address_reference_check` CHECK (((`type` <> _utf8mb4\'ADDRESS_REFERENCE\') or (`address_id` is not null)))');
        DB::statement('ALTER TABLE `crm_contact_points` ADD CONSTRAINT `crm_contact_points_priority_check` CHECK (((`priority` is null) or (`priority` >= 0)))');
        // MySQL cannot express a partial unique index, so "at most one active default per channel and scope"
        // is enforced through a stored generated column that is NULL for every other row.
        DB::statement('ALTER TABLE `crm_contact_points` ADD COLUMN `default_contact_customer_id` int unsigned GENERATED ALWAYS AS (if(((`is_default` = 1) and (`status` = _utf8mb4\'ACTIVE\')), `customer_id`, NULL)) STORED');
        DB::statement('ALTER TABLE `crm_contact_points` ADD UNIQUE INDEX `crm_contact_points_default_unique` (`hq_id`, `default_contact_customer_id`, `type`, `scope`)');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_contact_points_owner_insert` BEFORE INSERT ON `crm_contact_points` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.customer_id AND c.hq_id=NEW.hq_id AND c.kind='PERSON') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A contact point belongs to a PERSON record only'; END IF; IF NEW.relationship_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_relationships r WHERE r.id=NEW.relationship_id AND r.hq_id=NEW.hq_id AND r.person_customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Contact point relationship must belong to the same person'; END IF; IF NEW.address_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_customer_address a WHERE a.id=NEW.address_id AND a.hq_id=NEW.hq_id AND a.customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Contact point address must belong to the same person'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_contact_points_owner_update` BEFORE UPDATE ON `crm_contact_points` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.customer_id AND c.hq_id=NEW.hq_id AND c.kind='PERSON') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A contact point belongs to a PERSON record only'; END IF; IF NEW.relationship_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_relationships r WHERE r.id=NEW.relationship_id AND r.hq_id=NEW.hq_id AND r.person_customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Contact point relationship must belong to the same person'; END IF; IF NEW.address_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_customer_address a WHERE a.id=NEW.address_id AND a.hq_id=NEW.hq_id AND a.customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Contact point address must belong to the same person'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_contact_points_owner_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_contact_points_owner_insert`');
        DB::statement('ALTER TABLE `crm_contact_points` DROP INDEX `crm_contact_points_default_unique`');
        DB::statement('ALTER TABLE `crm_contact_points` DROP COLUMN `default_contact_customer_id`');
        DB::statement('ALTER TABLE `crm_contact_points` DROP CHECK `crm_contact_points_priority_check`');
        DB::statement('ALTER TABLE `crm_contact_points` DROP CHECK `crm_contact_points_address_reference_check`');
    }
};
