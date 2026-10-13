<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform columns started as enums (tiktok|youtube|instagram). Facebook
     * Page publishing needs 'facebook', so widen them to plain strings.
     */
    public function up(): void
    {
        $columns = [
            'social_accounts' => ['provider', 'platform_name'],
            'post_distributions' => ['platform'],
            'post_analytics' => ['platform'],
        ];

        foreach ($columns as $table => $names) {
            foreach ($names as $name) {
                if (DB::getDriverName() === 'mysql') {
                    $default = $name === 'platform_name' ? " DEFAULT 'tiktok'" : '';
                    DB::statement("ALTER TABLE {$table} MODIFY {$name} VARCHAR(32) NOT NULL{$default}");
                } else {
                    Schema::table($table, function (Blueprint $t) use ($name) {
                        $column = $t->string($name, 32);
                        if ($name === 'platform_name') {
                            $column->default('tiktok');
                        }
                        $column->change();
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // Irreversible: narrowing back to an enum would reject 'facebook' rows.
    }
};
