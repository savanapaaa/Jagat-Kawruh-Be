<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop FK first, then alter column to nullable, then re-add FK with set null.
        Schema::table('materis', function (Blueprint $table) {
            $table->dropForeign(['jurusan_id']);
        });

        DB::statement('ALTER TABLE materis MODIFY jurusan_id VARCHAR(255) NULL');

        Schema::table('materis', function (Blueprint $table) {
            $table->foreign('jurusan_id')->references('id')->on('jurusans')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materis', function (Blueprint $table) {
            $table->dropForeign(['jurusan_id']);
        });

        DB::statement('ALTER TABLE materis MODIFY jurusan_id VARCHAR(255) NOT NULL');

        Schema::table('materis', function (Blueprint $table) {
            $table->foreign('jurusan_id')->references('id')->on('jurusans')->onDelete('cascade');
        });
    }
};
