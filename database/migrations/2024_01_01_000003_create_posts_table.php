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
            $table->string('tiktok_id')->nullable();
            $table->text('caption')->nullable();
            $table->string('download_path')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('tiktok_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
