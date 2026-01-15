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
        Schema::create('helpdesks', function (Blueprint $table) {
            $table->string('id')->primary(); // ticket-1, ticket-2, etc
            $table->foreignId('siswa_id')->constrained('users')->onDelete('cascade');
            $table->enum('kategori', ['Akun', 'Kuis', 'Materi', 'PBL', 'Lainnya']);
            $table->string('judul');
            $table->text('pesan');
            $table->enum('status', ['open', 'progress', 'solved'])->default('open');
            $table->text('balasan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helpdesks');
    }
};
