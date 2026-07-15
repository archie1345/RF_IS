<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs') || Schema::hasColumn('activity_logs', 'id')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('alter table `activity_logs` add column `id` bigint unsigned not null auto_increment primary key first');
            return;
        }

        throw new RuntimeException('activity_logs.id is missing. Rebuild the database with migrate:fresh or restore the primary key manually for driver: '.$driver);
    }

    public function down(): void
    {
        // Do not drop the primary key on rollback.
    }
};
