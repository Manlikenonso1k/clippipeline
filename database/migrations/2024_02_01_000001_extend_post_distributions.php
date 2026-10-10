<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_distributions', function (Blueprint $table) {
            if (! Schema::hasColumn('post_distributions', 'external_id')) {
                $table->string('external_id')->nullable();
            }
            if (! Schema::hasColumn('post_distributions', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }
            if (! Schema::hasColumn('post_distributions', 'meta')) {
                $table->json('meta')->nullable();
            }
            if (! Schema::hasColumn('post_distributions', 'error')) {
                $table->text('error')->nullable();
            }
        });

        // status started life as an enum (pending|processing|published|failed) which
        // rejects the 'queued' state the manual cross-post flow writes. Widen it to a
        // plain string. MySQL needs an explicit MODIFY; SQLite (tests) is rebuilt by
        // the schema builder's native change().
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE post_distributions MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('post_distributions', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('post_distributions', function (Blueprint $table) {
            foreach (['published_at', 'meta', 'error'] as $col) {
                if (Schema::hasColumn('post_distributions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
