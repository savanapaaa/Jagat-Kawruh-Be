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
        Schema::create('notifikasis', function (Blueprint $table) {
            $table->string('id')->primary(); // notif-1, notif-2, etc
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('judul');
            $table->text('pesan');
            $table->enum('tipe', ['kuis', 'materi', 'pbl', 'pengumuman'])->default('pengumuman');
            $table->boolean('read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifikasis');
    }
};
