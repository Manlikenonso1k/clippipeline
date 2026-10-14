<?php

namespace App\Services\Account;

use App\Models\User;
use App\Services\CrossPostService;
use App\Services\PostUploadService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** CONTRACT §10 Preferences, stored as columns on users. Shared by API + Filament. */
class PreferencesService
{
    /**
     * @return array{notifications_enabled: bool, auto_sync: bool, default_platforms: array<int, string>, default_hashtags: array<int, string>}
     */
    public function get(User $user): array
    {
        return [
            'notifications_enabled' => $user->notifications_enabled ?? true,
            'auto_sync' => $user->auto_sync ?? true,
            'default_platforms' => array_values(array_intersect(CrossPostService::PLATFORMS, (array) ($user->default_platforms ?? []))),
            'default_hashtags' => array_values((array) ($user->default_hashtags ?? [])),
        ];
    }

    /**
     * Apply any subset of Preferences. Throws ValidationException (422) on bad input.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): array
    {
        $validated = Validator::make($data, [
            'notifications_enabled' => ['sometimes', 'boolean'],
            'auto_sync' => ['sometimes', 'boolean'],
            'default_platforms' => ['sometimes', 'array'],
            'default_platforms.*' => ['string', 'distinct', Rule::in(CrossPostService::PLATFORMS)],
            'default_hashtags' => ['sometimes', 'array', 'max:30'],
            'default_hashtags.*' => ['string', 'max:100'],
        ])->validate();

        foreach (['notifications_enabled', 'auto_sync'] as $flag) {
            if (array_key_exists($flag, $validated)) {
                $user->{$flag} = (bool) $validated[$flag];
            }
        }
        if (array_key_exists('default_platforms', $validated)) {
            $user->default_platforms = array_values($validated['default_platforms']);
        }
        if (array_key_exists('default_hashtags', $validated)) {
            $user->default_hashtags = PostUploadService::normalizeHashtags($validated['default_hashtags']);
        }
        $user->save();

        return $this->get($user);
    }

    /** Users who turned auto-sync off (excluded from scheduled syncs). @return array<int, int> */
    public static function autoSyncDisabledUserIds(): array
    {
        return User::query()->where('auto_sync', false)->pluck('id')->all();
    }
}
