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
        Schema::create('pbls', function (Blueprint $table) {
            $table->string('id')->primary(); // pbl-1, pbl-2, etc
            $table->string('judul');
            $table->text('masalah');
            $table->text('tujuan_pembelajaran');
            $table->text('panduan');
            $table->text('referensi')->nullable();
            $table->enum('kelas', ['X', 'XI', 'XII']);
            $table->string('jurusan_id');
            $table->enum('status', ['Draft', 'Aktif', 'Selesai'])->default('Draft');
            $table->date('deadline')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->foreign('jurusan_id')->references('id')->on('jurusans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pbls');
    }
};
