<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parity + compliance schema (docs/CONTRACT.md Part 2). Additive and guarded so
     * a partially applied deploy can re-run safely.
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

        $add('users', [
            // The `settings` cast (billing) predates any migration for it.
            'settings' => fn (Blueprint $t) => $t->json('settings')->nullable(),
            'last_ip' => fn (Blueprint $t) => $t->string('last_ip', 45)->nullable(),
            'notifications_enabled' => fn (Blueprint $t) => $t->boolean('notifications_enabled')->default(true),
            'auto_sync' => fn (Blueprint $t) => $t->boolean('auto_sync')->default(true),
            'default_platforms' => fn (Blueprint $t) => $t->json('default_platforms')->nullable(),
            'default_hashtags' => fn (Blueprint $t) => $t->json('default_hashtags')->nullable(),
            'deletion_requested_at' => fn (Blueprint $t) => $t->timestamp('deletion_requested_at')->nullable(),
        ]);

        $add('social_accounts', [
            'last_authorized_check_at' => fn (Blueprint $t) => $t->timestamp('last_authorized_check_at')->nullable(),
            'reauth_required_at' => fn (Blueprint $t) => $t->timestamp('reauth_required_at')->nullable(),
        ]);

        // Revoked accounts keep their row (needs_reauth) but lose their tokens.
        Schema::table('social_accounts', function (Blueprint $t) {
            $t->text('access_token')->nullable()->change();
        });

        $add('posts', [
            'video_duration_seconds' => fn (Blueprint $t) => $t->decimal('video_duration_seconds', 10, 3)->nullable(),
        ]);

        $add('post_distributions', [
            'options' => fn (Blueprint $t) => $t->json('options')->nullable(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
