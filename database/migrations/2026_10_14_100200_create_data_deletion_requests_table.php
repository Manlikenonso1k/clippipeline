<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deletion requests + audit log without PII (COMPLIANCE C-06, A-02): the subject
     * is stored only as a SHA-256 hash.
     */
    public function up(): void
    {
        if (Schema::hasTable('data_deletion_requests')) {
            return;
        }

        Schema::create('data_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('source', 32); // meta | tiktok | account
            $table->string('subject_hash', 64)->nullable();
            $table->string('status', 32)->default('pending'); // pending | completed
            $table->json('summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_deletion_requests');
    }
};
