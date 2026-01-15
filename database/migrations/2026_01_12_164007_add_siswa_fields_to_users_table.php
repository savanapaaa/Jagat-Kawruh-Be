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
        Schema::table('users', function (Blueprint $table) {
            // Ubah nisn menjadi nis dan buat unique optional
            $table->string('nis')->nullable()->unique()->after('role');
            $table->enum('kelas', ['X', 'XI', 'XII'])->nullable()->after('nis');
            $table->string('jurusan_id')->nullable()->after('kelas');
            
            // Foreign key ke jurusans
            $table->foreign('jurusan_id')->references('id')->on('jurusans')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['jurusan_id']);
            $table->dropColumn(['nis', 'kelas', 'jurusan_id']);
        });
    }
};
