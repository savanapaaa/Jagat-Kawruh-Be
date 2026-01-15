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
        Schema::create('soals', function (Blueprint $table) {
            $table->string('id')->primary(); // soal-1, soal-2, etc
            $table->string('kuis_id');
            $table->text('pertanyaan');
            $table->text('image')->nullable(); // base64 or URL
            $table->json('pilihan'); // {"A": "...", "B": "...", ...}
            $table->string('jawaban', 1); // A, B, C, D, atau E
            $table->integer('urutan')->default(0);
            $table->timestamps();
            
            $table->foreign('kuis_id')->references('id')->on('kuis')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('soals');
    }
};
