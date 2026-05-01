<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pivot table untuk many-to-many PBL ↔ Kelas
     */
    public function up(): void
    {
        Schema::create('pbl_kelas', function (Blueprint $table) {
            $table->string('pbl_id');
            $table->unsignedBigInteger('kelas_id');
            $table->timestamps();

            $table->primary(['pbl_id', 'kelas_id']);
            
            $table->foreign('pbl_id')
                ->references('id')
                ->on('pbls')
                ->onDelete('cascade');
                
            $table->foreign('kelas_id')
                ->references('id')
                ->on('kelas')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pbl_kelas');
    }
};
