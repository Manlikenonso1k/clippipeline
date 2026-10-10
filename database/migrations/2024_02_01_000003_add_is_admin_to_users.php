<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fresh = ! Schema::hasColumn('users', 'is_admin');

        Schema::table('users', function (Blueprint $table) use ($fresh) {
            if ($fresh) {
                $table->boolean('is_admin')->default(false);
            }
        });

        // Preserve current access: everyone who already had an account (the app let
        // all authenticated users into /admin before) stays an admin. New sign-ups
        // default to false and must be promoted with `php artisan app:make-admin`.
        if ($fresh) {
            DB::table('users')->update(['is_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_admin')) {
                $table->dropColumn('is_admin');
            }
        });
    }
};
