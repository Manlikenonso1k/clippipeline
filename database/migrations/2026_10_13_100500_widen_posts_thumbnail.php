<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform thumbnail URLs (Instagram thumbnail_url, Facebook picture, TikTok
     * cover_image_url) are signed CDN URLs well over 255 characters.
     */
    public function up(): void
    {
        if (Schema::hasColumn('posts', 'thumbnail')) {
            Schema::table('posts', function (Blueprint $t) {
                $t->text('thumbnail')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Narrowing could truncate stored URLs; leave the column as text.
    }
};
