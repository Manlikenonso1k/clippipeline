<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Models\User;
use App\Services\PostUploadService;
use App\Services\TikTok\CreatorInfoService;
use App\Services\TikTok\NoTikTokAccountException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs the same upload-once flow as POST /api/posts (PostUploadService), as a
 * wizard ending in an explicit confirm step (COMPLIANCE P-01).
 */
class CreatePost extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PostResource::class;

    /** Last GET /api/tiktok/creator-info result for this form (T-01). */
    public ?array $tiktokCreator = null;

    public ?string $tiktokCreatorError = null;

    public function mount(): void
    {
        parent::mount();

        if (array_key_exists('tiktok', PostForm::platformOptions())) {
            $this->loadTiktokCreator();
        }
    }

    /** T-01: fetched every time the TikTok form opens (page load or TikTok checked). */
    public function loadTiktokCreator(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $this->tiktokCreator = app(CreatorInfoService::class)->forUser($user);
            $this->tiktokCreatorError = null;
        } catch (NoTikTokAccountException) {
            $this->tiktokCreator = null;
            $this->tiktokCreatorError = 'TikTok is not connected or needs reconnecting';
        } catch (Throwable $e) {
            report($e);
            $this->tiktokCreator = null;
            $this->tiktokCreatorError = 'TikTok did not respond';
        }
    }

    public function getSteps(): array
    {
        return PostForm::createSteps(
            fn (): ?array => $this->tiktokCreator,
            fn (): ?string => $this->tiktokCreatorError,
            fn () => $this->loadTiktokCreator(),
        );
    }

    protected function handleRecordCreation(array $data): Model
    {
        $video = Arr::first(Arr::wrap($data['video'] ?? null));
        if (! $video instanceof UploadedFile) {
            throw ValidationException::withMessages(['data.video' => 'Upload a video.']);
        }

        try {
            return app(PostUploadService::class)->create(
                auth()->user(),
                $video,
                $data['title'],
                filled($data['caption'] ?? null) ? $data['caption'] : null,
                PostUploadService::normalizeHashtags($data['hashtags'] ?? null),
                array_values($data['platforms'] ?? []),
                filled($data['scheduled_at'] ?? null) ? Carbon::parse($data['scheduled_at'])->utc() : null,
                PostForm::platformOptionsFromData($data),
            );
        } catch (ValidationException $e) {
            // Map service field errors (e.g. "video", "platform_options.tiktok.privacy_level") onto the form's state paths.
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.self::fieldFor($key) => $messages])->all(),
            );
        }
    }

    /** API error key → wizard field name. */
    private static function fieldFor(string $key): string
    {
        return match ($key) {
            'platform_options.youtube.privacy_status' => 'yt_privacy',
            'platform_options.youtube.guidelines_confirmed' => 'yt_guidelines',
            'platform_options.tiktok.privacy_level' => 'tt_privacy_level',
            'platform_options.tiktok.brand_content_toggle' => 'tt_brand_content',
            'platform_options.tiktok.brand_organic_toggle' => 'tt_brand_organic',
            default => str_starts_with($key, 'platform_options.') ? 'platforms' : $key,
        };
    }

    protected function getCreatedNotification(): ?Notification
    {
        $scheduled = $this->getRecord()->scheduled_at !== null;

        return Notification::make()
            ->success()
            ->title($scheduled ? 'Post scheduled' : 'Publishing started')
            // COMPLIANCE P-06
            ->body($scheduled ? 'It will be published at the scheduled time. You can cancel it until then.' : PostForm::PROCESSING_NOTICE);
    }

    protected function getRedirectUrl(): string
    {
        return PostResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
