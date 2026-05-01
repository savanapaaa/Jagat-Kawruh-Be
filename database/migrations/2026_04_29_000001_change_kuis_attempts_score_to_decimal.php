<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('kuis_attempts') || !Schema::hasColumn('kuis_attempts', 'score')) {
            return;
        }

        $driver = DB::getDriverName();

        // Prefer raw SQL to avoid requiring doctrine/dbal for column changes.
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `kuis_attempts` MODIFY `score` DECIMAL(5,2) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE kuis_attempts ALTER COLUMN score TYPE NUMERIC(5,2)');
            DB::statement('ALTER TABLE kuis_attempts ALTER COLUMN score DROP DEFAULT');
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support altering column types easily without table rebuild.
            // No-op to keep migrations runnable in lightweight/dev environments.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('kuis_attempts') || !Schema::hasColumn('kuis_attempts', 'score')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `kuis_attempts` MODIFY `score` INT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE kuis_attempts ALTER COLUMN score TYPE INTEGER');
            DB::statement('ALTER TABLE kuis_attempts ALTER COLUMN score DROP DEFAULT');
        } elseif ($driver === 'sqlite') {
            // No-op.
        }
    }
};
