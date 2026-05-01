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
        Schema::create('pbl_kontribusi', function (Blueprint $table) {
            $table->id();
            $table->string('pbl_id');
            $table->string('kelompok_id');
            $table->string('sintaks_id');
            $table->unsignedBigInteger('siswa_id');
            $table->text('catatan');
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('pbl_id')->references('id')->on('pbls')->onDelete('cascade');
            $table->foreign('kelompok_id')->references('id')->on('kelompoks')->onDelete('cascade');
            $table->foreign('sintaks_id')->references('id')->on('pbl_sintaks')->onDelete('cascade');
            $table->foreign('siswa_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['pbl_id', 'kelompok_id', 'sintaks_id', 'siswa_id'], 'uniq_pbl_kontribusi_scope');
            $table->index(['pbl_id', 'kelompok_id']);
            $table->index(['pbl_id', 'sintaks_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pbl_kontribusi');
    }
};
