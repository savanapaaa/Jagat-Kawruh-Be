<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelompoks', function (Blueprint $table) {
            $table->json('nilai_individu')->nullable()->after('jobdesk');
        });
    }

    public function down(): void
    {
        Schema::table('kelompoks', function (Blueprint $table) {
            $table->dropColumn('nilai_individu');
        });
    }
};
