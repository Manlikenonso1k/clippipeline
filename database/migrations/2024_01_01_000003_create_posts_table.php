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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
            $table->string('title');
            $table->text('video_url');
            $table->timestamp('published_at')->nullable();
            $table->string('tiktok_id')->nullable();
            $table->text('caption')->nullable();
            $table->string('download_path')->nullable();
            $table->text('original_url')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('tiktok_id');
            $table->index('social_account_id');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
