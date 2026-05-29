<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('tiktok_id')->unique();
            $table->text('caption')->nullable();
            $table->string('download_path')->nullable(); // local path or s3 url
            $table->string('original_url')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('tiktok_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
