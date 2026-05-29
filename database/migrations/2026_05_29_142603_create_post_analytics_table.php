<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('post_distribution_id')->nullable()->constrained('post_distributions')->onDelete('set null');
            $table->string('platform');
            $table->date('date');
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('comments')->default(0);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'platform', 'date']);
            $table->index(['platform', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_analytics');
    }
};
