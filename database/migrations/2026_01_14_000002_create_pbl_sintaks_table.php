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
        Schema::create('pbl_sintaks', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('pbl_id');
            $table->string('judul');
            $table->text('instruksi')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->foreign('pbl_id')->references('id')->on('pbls')->onDelete('cascade');
            $table->index(['pbl_id', 'urutan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pbl_sintaks');
    }
};
