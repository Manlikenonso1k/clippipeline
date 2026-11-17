<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Studio (docs/STUDIO_SPEC.md §4, CONTRACT §17).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('aspect', 8)->default('9:16');
            $table->unsignedSmallInteger('fps')->default(30);
            $table->json('edl');
            $table->json('edl_previous')->nullable(); // one-step "Undo auto-cut"
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('reference_asset_id')->nullable();
            $table->json('style_profile')->nullable();
            $table->unsignedBigInteger('last_render_id')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('studio_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('studio_projects')->cascadeOnDelete();
            $table->string('role', 16);   // source|reference|music|image|logo
            $table->string('origin', 8);  // upload|link
            $table->text('original_url')->nullable();
            $table->string('original_name')->nullable();
            $table->string('disk', 32)->default('local');
            $table->string('path')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->decimal('duration_seconds', 10, 3)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('fps', 8, 3)->nullable();
            $table->boolean('has_audio')->nullable();
            $table->json('probe')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->string('preview_proxy_path')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('waveform_path')->nullable();
            $table->string('thumbs_path')->nullable();
            $table->string('transcript_path')->nullable();
            $table->string('transcript_language', 16)->nullable();
            $table->json('style_profile')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'role']);
        });

        Schema::create('studio_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('studio_projects')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('studio_assets')->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 16)->default('queued');
            $table->smallInteger('priority')->default(0);
            $table->json('input')->nullable();
            $table->json('result')->nullable();
            $table->float('progress')->default(0);
            $table->string('message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->string('worker_id', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'created_at']);
        });

        Schema::create('studio_renders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('studio_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('job_id')->nullable();
            $table->string('quality', 8); // preview|final
            $table->unsignedInteger('edl_revision');
            $table->string('path');
            $table->string('poster_path')->nullable();
            $table->decimal('duration_seconds', 10, 3)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('check')->nullable();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('studio_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64);
            $table->unsignedInteger('version');
            $table->string('name', 120);
            $table->json('categories');
            $table->string('kind', 16); // segment|overlay|captions|transition
            $table->json('aspects');
            $table->float('default_duration')->default(3);
            $table->float('min_duration')->default(0.5);
            $table->float('max_duration')->default(30);
            $table->json('props_schema');
            $table->json('default_props');
            $table->string('preview_path')->nullable();
            $table->string('poster_path')->nullable();
            $table->json('previews')->nullable(); // {"9:16": {"video": path, "poster": path}, ...}
            $table->json('credit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamps();
            $table->unique(['slug', 'version']);
        });

        Schema::create('studio_ai_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_ai_calls');
        Schema::dropIfExists('studio_templates');
        Schema::dropIfExists('studio_renders');
        Schema::dropIfExists('studio_jobs');
        Schema::dropIfExists('studio_assets');
        Schema::dropIfExists('studio_projects');
    }
};
