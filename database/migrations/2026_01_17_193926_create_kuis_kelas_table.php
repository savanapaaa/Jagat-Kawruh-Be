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
        Schema::create('kuis_kelas', function (Blueprint $table) {
            $table->id();
            $table->string('kuis_id'); // FK to kuis.id (string format: kuis-1)
            $table->unsignedBigInteger('kelas_id'); // FK to kelas.id
            $table->timestamps();

            // Foreign keys
            $table->foreign('kuis_id')->references('id')->on('kuis')->onDelete('cascade');
            $table->foreign('kelas_id')->references('id')->on('kelas')->onDelete('cascade');

            // Unique constraint to prevent duplicate pivot entries
            $table->unique(['kuis_id', 'kelas_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuis_kelas');
    }
};
