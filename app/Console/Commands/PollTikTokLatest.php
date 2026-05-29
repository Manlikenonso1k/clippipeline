<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\TikTokScraperService;
use App\Jobs\DownloadTikTokVideoJob;

class PollTikTokLatest extends Command
{
    protected $signature = 'tiktok:poll-latest';
    protected $description = 'Poll configured users for their latest TikTok video and enqueue processing';

    public function handle(TikTokScraperService $scraper)
    {
        $this->info('Polling users for latest TikTok videos...');

        $users = DB::table('users')->whereNotNull('settings')->get();

        foreach ($users as $user) {
            $settings = json_decode($user->settings, true) ?? [];
            if (empty($settings['tiktok_username'])) {
                continue;
            }

            $username = $settings['tiktok_username'];
            $latest = $scraper->getLatestVideoId($username);
            if (! $latest) {
                $this->line("No latest found for {$username}");
                continue;
            }

            $exists = DB::table('posts')->where('tiktok_id', $latest)->exists();
            if ($exists) {
                $this->line("Already processed {$latest} for {$username}");
                continue;
            }

            // Abuse / rate checks: prevent free-plan users from exceeding free limits
            if (! $this->canCreatePost($user)) {
                $this->line("Skipping {$latest} for user {$user->id} — rate/IP limits or flagged");
                continue;
            }

            $postId = DB::table('posts')->insertGetId([
                'user_id' => $user->id,
                'tiktok_id' => $latest,
                'caption' => null,
                'original_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->line("Queued post {$latest} (id: {$postId}) for download");
            DownloadTikTokVideoJob::dispatch($postId);
        }

        $this->info('Polling complete.');
    }

    /**
     * Decide whether the user may create another post under their plan and IP.
     */
    private function canCreatePost($user): bool
    {
        $settings = json_decode($user->settings, true) ?: [];
        $billing = $settings['billing'] ?? [];
        $plan = $billing['plan'] ?? 'free';

        // If already flagged, disallow automatic post creation.
        try {
            $flagged = DB::table('users')->where('id', $user->id)->value('flagged');
            if ($flagged) {
                return false;
            }
        } catch (\Exception $e) {
            // DB column may not exist yet; allow to proceed in that case
        }

        if ($plan !== 'free') {
            return true; // paid plans have no automatic free quota enforcement here
        }

        $freeLimit = 10; // free plan allowed posts
        $userPosts = DB::table('posts')->where('user_id', $user->id)->count();
        if ($userPosts >= $freeLimit) {
            $this->flagUser($user, 'free_quota_exceeded');
            return false;
        }

        // IP-abuse checks: if many accounts from same IP are creating posts, flag.
        try {
            $ip = DB::table('users')->where('id', $user->id)->value('last_ip');
        } catch (\Exception $e) {
            // column likely missing, skip IP checks
            return true;
        }
        if (! $ip) {
            return true;
        }

        $relatedUserIds = DB::table('users')->where('last_ip', $ip)->pluck('id')->toArray();
        $distinctAccountsFromIp = count($relatedUserIds);

        // count total posts created by accounts from this IP
        $postsFromIp = DB::table('posts')->whereIn('user_id', $relatedUserIds)->count();

        // thresholds — tune these as needed
        if ($distinctAccountsFromIp >= 3 || $postsFromIp >= ($freeLimit * 2)) {
            $this->flagUser($user, 'ip_abuse_suspected');
            return false;
        }

        return true;
    }

    private function flagUser($user, $reason = 'abuse_detected')
    {
        try {
            // set flagged flag and append suggestion to settings
            DB::table('users')->where('id', $user->id)->update([
                'flagged' => true,
                'flag_reason' => $reason,
                'flagged_at' => now(),
                'updated_at' => now(),
            ]);

            $settings = json_decode($user->settings, true) ?: [];
            $settings['billing']['flagged'] = true;
            $settings['billing']['flag_reason'] = $reason;
            $settings['billing']['suggest_upgrade'] = true;
            DB::table('users')->where('id', $user->id)->update([
                'settings' => json_encode($settings),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // ignore
        }
    }
}
