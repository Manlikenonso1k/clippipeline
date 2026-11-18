<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Studio "Make a video" (express recipe on projects) and the trending-topics list (CONTRACT §17.5).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_projects', function (Blueprint $table) {
            $table->json('recipe')->nullable()->after('style_profile');
        });

        Schema::create('studio_trends', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 16);             // tiktok|youtube|instagram
            $table->string('region', 8)->default('NG');
            $table->string('title', 120);
            $table->string('hook', 40)->nullable();     // short on-screen hook, e.g. "GTA 6"
            $table->json('hashtags')->nullable();
            $table->string('source', 16)->default('curated'); // youtube (live API) | curated (admin)
            $table->string('url')->nullable();
            $table->unsignedBigInteger('score')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['platform', 'region', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_trends');
        Schema::table('studio_projects', function (Blueprint $table) {
            $table->dropColumn('recipe');
        });
    }
};
