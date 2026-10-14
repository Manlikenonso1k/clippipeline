<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a post locally (CONTRACT §12 DELETE /posts/{id}, COMPLIANCE P-05): its
 * distributions, analytics and stored files. Queued/scheduled publish jobs become
 * no-ops because PublishJob::handle() returns when its distribution is gone.
 * Nothing already published on a platform is deleted there.
 */
class PostDeletionService
{
    public function delete(Post $post): void
    {
        $files = array_values(array_filter([
            $post->video_path,
            $post->download_path,
            is_string($post->thumbnail) && ! preg_match('#^https?://#i', $post->thumbnail) ? $post->thumbnail : null,
        ], fn ($p) => is_string($p) && $p !== '' && ! preg_match('#^https?://#i', $p)));

        DB::transaction(function () use ($post) {
            $distributionIds = PostDistribution::query()->where('post_id', $post->id)->pluck('id');

            PostAnalytic::query()
                ->where(fn ($q) => $q->where('post_id', $post->id)->orWhereIn('distribution_id', $distributionIds))
                ->delete();

            // Query-builder delete: no "published/failed" push for rows being removed.
            PostDistribution::query()->whereIn('id', $distributionIds)->delete();

            $post->delete();
        });

        foreach ($files as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}
