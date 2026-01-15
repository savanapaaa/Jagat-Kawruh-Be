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
        Schema::create('pbl_submissions', function (Blueprint $table) {
            $table->string('id')->primary(); // submit-1, submit-2, etc
            $table->string('pbl_id');
            $table->string('kelompok_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('file_size');
            $table->text('catatan')->nullable();
            $table->integer('nilai')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
            
            $table->foreign('pbl_id')->references('id')->on('pbls')->onDelete('cascade');
            $table->foreign('kelompok_id')->references('id')->on('kelompoks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pbl_submissions');
    }
};
