<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Services\CrossPostService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Create: same fields and rules as POST /api/posts (docs/CONTRACT.md §4).
 * View: read-only summary; per-platform status lives in the Distributions relation manager.
 */
class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        $maxMb = (int) config('clippipeline.max_upload_mb', 200);

        return $schema
            ->components([
                FileUpload::make('video')
                    ->label('Video')
                    ->required()
                    // Kept as a temporary upload: PostUploadService stores it on the public disk.
                    ->storeFiles(false)
                    ->disk('public')
                    // Livewire's temporary upload rules (config/livewire.php) allow mp4/mov only.
                    ->acceptedFileTypes(['video/mp4', 'video/quicktime'])
                    ->maxSize($maxMb * 1024)
                    ->helperText("MP4 or MOV, up to {$maxMb} MB.")
                    ->columnSpanFull()
                    ->visibleOn('create'),
                TextInput::make('title')
                    ->required()
                    ->maxLength(100),
                DateTimePicker::make('scheduled_at')
                    ->label('Schedule (optional)')
                    ->seconds(false)
                    ->after('now')
                    ->helperText('Leave empty to publish now.'),
                Textarea::make('caption')
                    ->maxLength(2200)
                    ->rows(3)
                    ->columnSpanFull(),
                TagsInput::make('hashtags')
                    ->placeholder('Add a hashtag')
                    ->splitKeys([' ', ','])
                    ->columnSpanFull(),
                CheckboxList::make('platforms')
                    ->label('Publish to')
                    ->required()
                    ->options(fn (): array => self::platformOptions())
                    ->in(fn (): array => array_keys(self::platformOptions()))
                    ->helperText(fn (): ?string => self::platformOptions() === []
                        ? 'No active accounts. Connect one on the Connected Accounts page first.'
                        : null)
                    ->columns(2)
                    ->columnSpanFull()
                    ->visibleOn('create'),
                TextInput::make('video_url')
                    ->label('Video URL')
                    ->columnSpanFull()
                    ->visibleOn('view'),
            ]);
    }

    /**
     * Only the user's active accounts (the set POST /api/posts accepts).
     *
     * @return array<string, string>
     */
    public static function platformOptions(): array
    {
        $options = [];
        foreach (app(CrossPostService::class)->optionsForUser((int) auth()->id()) as $opt) {
            if ($opt['publishable']) {
                $options[$opt['provider']] = $opt['label'];
            }
        }

        return $options;
    }
}
