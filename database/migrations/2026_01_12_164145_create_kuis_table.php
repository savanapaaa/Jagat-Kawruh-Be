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
        Schema::create('kuis', function (Blueprint $table) {
            $table->string('id')->primary(); // kuis-1, kuis-2, etc
            $table->string('judul');
            $table->json('kelas'); // ["X", "XI", "XII"]
            $table->integer('batas_waktu'); // dalam menit
            $table->enum('status', ['Draft', 'Aktif', 'Selesai'])->default('Draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuis');
    }
};
