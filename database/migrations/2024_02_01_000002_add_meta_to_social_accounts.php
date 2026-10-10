<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_accounts', 'meta')) {
                $table->json('meta')->nullable();
            }
            if (! Schema::hasColumn('social_accounts', 'needs_attention')) {
                $table->boolean('needs_attention')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            foreach (['meta', 'needs_attention'] as $col) {
                if (Schema::hasColumn('social_accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
