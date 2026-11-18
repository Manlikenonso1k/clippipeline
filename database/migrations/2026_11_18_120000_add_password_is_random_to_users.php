<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Accounts made with Google/Facebook/TikTok get a random password nobody knows. Flag them so they can
// set a real password without "current password" (CONTRACT §18).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('password_is_random')->default(false)->after('password');
        });
        DB::table('users')->where(fn ($q) => $q->whereNotNull('google_id')->orWhereNotNull('facebook_id')->orWhereNotNull('tiktok_id'))
            ->update(['password_is_random' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_is_random');
        });
    }
};
