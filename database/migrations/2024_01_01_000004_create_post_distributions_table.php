<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->enum('platform', ['youtube', 'instagram', 'tiktok']);
            $table->string('external_id')->nullable();
            $table->enum('status', ['pending', 'processing', 'published', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index('post_id');
            $table->index('platform');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_distributions');
    }
};
