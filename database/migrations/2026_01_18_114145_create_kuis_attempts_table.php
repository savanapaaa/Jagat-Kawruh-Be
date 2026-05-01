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
        Schema::create('kuis_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('kuis_id'); // FK to kuis.id (string: kuis-1)
            $table->unsignedBigInteger('siswa_id'); // FK to users.id
            $table->string('token', 64)->unique(); // Random token untuk akses attempt
            
            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable(); // started_at + duration
            $table->timestamp('submitted_at')->nullable();
            
            // Status: in_progress, submitted, expired
            $table->enum('status', ['in_progress', 'submitted', 'expired'])->default('in_progress');
            
            // Hasil (dihitung saat submit)
            $table->decimal('score', 5, 2)->nullable(); // Nilai 0-100 (contoh: 96.67)
            $table->integer('benar')->nullable();
            $table->integer('salah')->nullable();
            $table->integer('total_soal')->nullable();
            
            // Jawaban siswa (JSON: { "soal-1": "A", "soal-2": "B" })
            $table->json('answers')->nullable();
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('kuis_id')->references('id')->on('kuis')->onDelete('cascade');
            $table->foreign('siswa_id')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint: 1 attempt aktif per siswa per kuis
            // (bisa multiple attempt jika max_attempts > 1, tapi hanya 1 in_progress)
            $table->index(['kuis_id', 'siswa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuis_attempts');
    }
};
