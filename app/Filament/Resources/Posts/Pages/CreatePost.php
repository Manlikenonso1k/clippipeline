<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Services\PostUploadService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Runs the same upload-once flow as POST /api/posts (PostUploadService). */
class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

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
            );
        } catch (ValidationException $e) {
            // Map service field errors (e.g. "video") onto the form's state path.
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.$key => $messages])->all(),
            );
        }
    }

    protected function getRedirectUrl(): string
    {
        return PostResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
