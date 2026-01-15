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
        Schema::create('materis', function (Blueprint $table) {
            $table->string('id')->primary(); // materi-1, materi-2, etc
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->json('kelas'); // ["X", "XI", "XII"]
            $table->string('jurusan_id');
            $table->foreign('jurusan_id')->references('id')->on('jurusans')->onDelete('cascade');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable(); // in bytes
            $table->enum('status', ['Draft', 'Published', 'Archived'])->default('Draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materis');
    }
};
