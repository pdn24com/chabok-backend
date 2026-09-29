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

        DB::statement('ALTER TABLE `crm_activities` ADD CONSTRAINT `crm_activities_call_shape` CHECK ((((`type` = _utf8mb4\'CALL\') and (`direction` is not null) and (`contact_value` is not null) and (`call_outcome` is not null)) or ((`type` <> _utf8mb4\'CALL\') and (`call_outcome` is null))))');
        DB::statement('ALTER TABLE `crm_activities` ADD CONSTRAINT `crm_activities_direction_scope_check` CHECK (((`direction` is null) or (`type` in (_utf8mb4\'CALL\',_utf8mb4\'MESSAGE\'))))');
        DB::statement('ALTER TABLE `crm_activities` ADD CONSTRAINT `crm_activities_duration_scope_check` CHECK (((`duration_minutes` is null) or (`type` in (_utf8mb4\'CALL\',_utf8mb4\'MEETING\'))))');
        DB::statement('ALTER TABLE `crm_activities` ADD CONSTRAINT `crm_activities_meeting_shape` CHECK ((((`type` = _utf8mb4\'MEETING\') and (`meeting_mode` is not null) and ((`location` is not null) or (`meeting_url` is not null))) or ((`type` <> _utf8mb4\'MEETING\') and (`meeting_mode` is null))))');
        DB::statement('ALTER TABLE `crm_activities` ADD CONSTRAINT `crm_activities_message_shape` CHECK (((`type` <> _utf8mb4\'MESSAGE\') or ((`direction` is not null) and (`channel` is not null) and (`contact_value` is not null))))');
        DB::statement('ALTER TABLE `crm_activities` ADD CONSTRAINT `crm_activities_note_shape` CHECK (((`type` <> _utf8mb4\'NOTE\') or (`body` is not null)))');
        DB::statement('ALTER TABLE `crm_activities` ADD CONSTRAINT `crm_activities_referral_shape` CHECK (((`type` <> _utf8mb4\'REFERRAL\') or (`assignment_event_id` is not null)))');
        DB::statement('ALTER TABLE `crm_activities` ADD CONSTRAINT `crm_activities_revision_pair_check` CHECK ((((`updated_at` is null) and (`updated_by` is null)) or ((`updated_at` is not null) and (`updated_by` is not null))))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_activities_contact_person_insert` BEFORE INSERT ON `crm_activities` FOR EACH ROW BEGIN IF NEW.contact_customer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.contact_customer_id AND c.hq_id=NEW.hq_id AND c.kind='PERSON') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='contact_customer_id must reference a PERSON record'; END IF; IF NEW.assignment_event_id IS NOT NULL AND NEW.task_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_task_assignment_events e WHERE e.id=NEW.assignment_event_id AND e.hq_id=NEW.hq_id AND e.task_id=NEW.task_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Referral evidence must belong to the same task'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_activities_contact_person_update` BEFORE UPDATE ON `crm_activities` FOR EACH ROW BEGIN IF NEW.contact_customer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.contact_customer_id AND c.hq_id=NEW.hq_id AND c.kind='PERSON') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='contact_customer_id must reference a PERSON record'; END IF; IF NEW.assignment_event_id IS NOT NULL AND NEW.task_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_task_assignment_events e WHERE e.id=NEW.assignment_event_id AND e.hq_id=NEW.hq_id AND e.task_id=NEW.task_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Referral evidence must belong to the same task'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_activities_contact_person_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_activities_contact_person_update`');
        DB::statement('ALTER TABLE `crm_activities` DROP CHECK `crm_activities_revision_pair_check`');
        DB::statement('ALTER TABLE `crm_activities` DROP CHECK `crm_activities_referral_shape`');
        DB::statement('ALTER TABLE `crm_activities` DROP CHECK `crm_activities_note_shape`');
        DB::statement('ALTER TABLE `crm_activities` DROP CHECK `crm_activities_message_shape`');
        DB::statement('ALTER TABLE `crm_activities` DROP CHECK `crm_activities_meeting_shape`');
        DB::statement('ALTER TABLE `crm_activities` DROP CHECK `crm_activities_duration_scope_check`');
        DB::statement('ALTER TABLE `crm_activities` DROP CHECK `crm_activities_direction_scope_check`');
        DB::statement('ALTER TABLE `crm_activities` DROP CHECK `crm_activities_call_shape`');
    }
};
