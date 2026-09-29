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

        DB::statement('ALTER TABLE `crm_team_members` ADD CONSTRAINT `crm_team_members_validity_window_check` CHECK (((`valid_to` is null) or (`valid_to` >= `valid_from`)))');
        DB::statement('ALTER TABLE `crm_team_members` ADD CONSTRAINT `crm_team_members_ended_window_check` CHECK ((((`status` = _utf8mb4\'ENDED\') and (`valid_to` is not null)) or (`status` <> _utf8mb4\'ENDED\')))');
        // MySQL cannot express a partial unique index, so "one current membership per tenant, team and user"
        // is enforced through a stored generated column that is NULL for every ended membership.
        DB::statement('ALTER TABLE `crm_team_members` ADD COLUMN `active_membership_user_id` int unsigned GENERATED ALWAYS AS (if((`status` = _utf8mb4\'ACTIVE\'), `user_id`, NULL)) STORED');
        DB::statement('ALTER TABLE `crm_team_members` ADD UNIQUE INDEX `crm_team_members_active_membership_unique` (`hq_id`, `team_id`, `active_membership_user_id`)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_team_members` DROP INDEX `crm_team_members_active_membership_unique`');
        DB::statement('ALTER TABLE `crm_team_members` DROP COLUMN `active_membership_user_id`');
        DB::statement('ALTER TABLE `crm_team_members` DROP CHECK `crm_team_members_ended_window_check`');
        DB::statement('ALTER TABLE `crm_team_members` DROP CHECK `crm_team_members_validity_window_check`');
    }
};
