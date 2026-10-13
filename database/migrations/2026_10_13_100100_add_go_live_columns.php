<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Go-live schema (docs/CONTRACT.md §6). Additive only; every column is guarded
     * so a partially applied deploy can re-run safely.
     */
    public function up(): void
    {
        $add = function (string $table, array $columns): void {
            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $name => $define) {
                    if (! Schema::hasColumn($table, $name)) {
                        $define($t);
                    }
                }
            });
        };

        $add('social_accounts', [
            'status' => fn (Blueprint $t) => $t->string('status', 32)->default('active'),
            'external_id' => fn (Blueprint $t) => $t->string('external_id')->nullable(),
            'display_name' => fn (Blueprint $t) => $t->string('display_name')->nullable(),
            'avatar_url' => fn (Blueprint $t) => $t->text('avatar_url')->nullable(),
            'last_synced_at' => fn (Blueprint $t) => $t->timestamp('last_synced_at')->nullable(),
            'last_error' => fn (Blueprint $t) => $t->text('last_error')->nullable(),
        ]);

        $add('posts', [
            'hashtags' => fn (Blueprint $t) => $t->json('hashtags')->nullable(),
            'scheduled_at' => fn (Blueprint $t) => $t->timestamp('scheduled_at')->nullable()->index(),
            'source' => fn (Blueprint $t) => $t->string('source', 32)->default('tiktok_ingest'),
            'video_path' => fn (Blueprint $t) => $t->string('video_path')->nullable(),
        ]);

        $add('post_distributions', [
            'attempts' => fn (Blueprint $t) => $t->unsignedInteger('attempts')->default(0),
            'privacy' => fn (Blueprint $t) => $t->string('privacy', 32)->nullable(),
            'dispatched_at' => fn (Blueprint $t) => $t->timestamp('dispatched_at')->nullable(),
        ]);

        $add('post_analytics', [
            'distribution_id' => fn (Blueprint $t) => $t->foreignId('distribution_id')->nullable()->constrained('post_distributions')->cascadeOnDelete(),
            'saves' => fn (Blueprint $t) => $t->unsignedBigInteger('saves')->default(0),
            'watch_time_seconds' => fn (Blueprint $t) => $t->unsignedBigInteger('watch_time_seconds')->default(0),
            'avg_view_duration_seconds' => fn (Blueprint $t) => $t->unsignedInteger('avg_view_duration_seconds')->default(0),
            'captured_on' => fn (Blueprint $t) => $t->date('captured_on')->nullable(),
        ]);

        Schema::table('post_analytics', function (Blueprint $t) {
            $t->unique(['distribution_id', 'captured_on'], 'post_analytics_distribution_day_unique');
        });

        if (! Schema::hasTable('account_follower_snapshots')) {
            Schema::create('account_follower_snapshots', function (Blueprint $t) {
                $t->id();
                $t->foreignId('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
                $t->string('platform', 32);
                $t->unsignedBigInteger('followers')->default(0);
                $t->date('captured_on');
                $t->timestamps();

                $t->unique(['social_account_id', 'captured_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_follower_snapshots');
    }
};
