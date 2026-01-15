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
        Schema::create('hasil_kuis', function (Blueprint $table) {
            $table->id();
            $table->string('kuis_id');
            $table->foreignId('siswa_id')->constrained('users')->onDelete('cascade');
            $table->json('jawaban'); // {"soal-1": "A", "soal-2": "B", ...}
            $table->integer('nilai')->default(0);
            $table->integer('benar')->default(0);
            $table->integer('salah')->default(0);
            $table->timestamp('waktu_mulai')->nullable();
            $table->timestamp('waktu_selesai')->nullable();
            $table->timestamps();
            
            $table->foreign('kuis_id')->references('id')->on('kuis')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_kuis');
    }
};
