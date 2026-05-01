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
        Schema::create('pbl_progress', function (Blueprint $table) {
            $table->id();
            $table->string('pbl_id');
            $table->string('sintaks_id');
            $table->string('kelompok_id');
            $table->text('catatan')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('pbl_id')->references('id')->on('pbls')->onDelete('cascade');
            $table->foreign('sintaks_id')->references('id')->on('pbl_sintaks')->onDelete('cascade');
            $table->foreign('kelompok_id')->references('id')->on('kelompoks')->onDelete('cascade');
            
            // Unique constraint: satu progress per sintaks per kelompok
            $table->unique(['sintaks_id', 'kelompok_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pbl_progress');
    }
};
