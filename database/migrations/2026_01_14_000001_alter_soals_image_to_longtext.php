<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Base64 image data URLs can exceed MySQL TEXT (64KB). Use LONGTEXT instead.
        DB::statement('ALTER TABLE `soals` MODIFY `image` LONGTEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `soals` MODIFY `image` TEXT NULL');
    }
};
