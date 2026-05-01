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
        Schema::create('materi_submissions', function (Blueprint $table) {
            $table->string('id')->primary(); // msub-1, msub-2, etc
            $table->string('materi_id');
            $table->unsignedBigInteger('siswa_id');
            $table->text('catatan')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedTinyInteger('nilai')->nullable(); // 0-100
            $table->text('feedback')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->foreign('materi_id')->references('id')->on('materis')->onDelete('cascade');
            $table->foreign('siswa_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['materi_id', 'siswa_id']);
            $table->index(['materi_id', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materi_submissions');
    }
};
