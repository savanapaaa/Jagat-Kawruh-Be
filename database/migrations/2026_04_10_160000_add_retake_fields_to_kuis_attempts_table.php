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
        Schema::table('kuis_attempts', function (Blueprint $table) {
            $table->boolean('retake_allowed')->default(false)->after('status');
            $table->unsignedBigInteger('retake_approved_by')->nullable()->after('retake_allowed');
            $table->timestamp('retake_approved_at')->nullable()->after('retake_approved_by');

            $table->foreign('retake_approved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kuis_attempts', function (Blueprint $table) {
            $table->dropForeign(['retake_approved_by']);
            $table->dropColumn(['retake_allowed', 'retake_approved_by', 'retake_approved_at']);
        });
    }
};
