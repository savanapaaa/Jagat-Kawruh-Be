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
        Schema::create('kelompoks', function (Blueprint $table) {
            $table->string('id')->primary(); // kelompok-1, kelompok-2, etc
            $table->string('pbl_id');
            $table->string('nama_kelompok');
            $table->json('anggota'); // Array of siswa IDs
            $table->timestamps();
            
            $table->foreign('pbl_id')->references('id')->on('pbls')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelompoks');
    }
};
