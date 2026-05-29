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
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // tiktok, youtube, instagram
            $table->string('external_id')->nullable(); // id on external platform
            $table->string('status')->default('queued'); // queued, processing, published, failed
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'platform']);
            $table->unique(['post_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_distributions');
    }
};
