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
        Schema::create('materi_kelas', function (Blueprint $table) {
            $table->id();
            $table->string('materi_id'); // FK to materis.id (string format: materi-1)
            $table->unsignedBigInteger('kelas_id'); // FK to kelas.id
            $table->timestamps();

            // Foreign keys
            $table->foreign('materi_id')->references('id')->on('materis')->onDelete('cascade');
            $table->foreign('kelas_id')->references('id')->on('kelas')->onDelete('cascade');

            // Unique constraint to prevent duplicate pivot entries
            $table->unique(['materi_id', 'kelas_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materi_kelas');
    }
};
