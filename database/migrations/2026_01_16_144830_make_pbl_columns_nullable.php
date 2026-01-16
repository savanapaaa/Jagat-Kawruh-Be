<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pbls', function (Blueprint $table) {
            $table->text('masalah')->nullable()->change();
            $table->text('tujuan_pembelajaran')->nullable()->change();
            $table->text('panduan')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pbls', function (Blueprint $table) {
            $table->text('masalah')->nullable(false)->change();
            $table->text('tujuan_pembelajaran')->nullable(false)->change();
            $table->text('panduan')->nullable(false)->change();
        });
    }
};
