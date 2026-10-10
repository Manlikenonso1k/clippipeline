<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use App\Services\CrossPostService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable(),
                TextColumn::make('distributions_summary')
                    ->label('Distribution')
                    ->state(fn (Post $record): string => $record->distributions
                        ->map(fn ($d) => ucfirst($d->platform).': '.$d->status)
                        ->implode('  •  ') ?: '—')
                    ->wrap(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                self::crossPostAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::crossPostBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Shared modal form fields, built from the post owner's connected providers. */
    protected static function formSchema(int $userId, ?Post $record = null): array
    {
        $fields = [];
        foreach (app(CrossPostService::class)->optionsForUser($userId) as $opt) {
            $fields[] = Toggle::make('platform_'.$opt['provider'])
                ->label($opt['label'])
                ->default($opt['publishable'])
                ->disabled(! $opt['publishable'])
                ->helperText($opt['reason']);
        }

        $fields[] = TextInput::make('title')
            ->default($record?->title)
            ->maxLength(100);
        $fields[] = Textarea::make('description')
            ->label('Caption / description')
            ->default($record?->caption)
            ->rows(3);
        $fields[] = TextInput::make('hashtags')
            ->placeholder('#shorts #reels');
        $fields[] = Select::make('privacy')
            ->label('YouTube privacy')
            ->options(['public' => 'Public', 'unlisted' => 'Unlisted', 'private' => 'Private'])
            ->default('public');

        if ($record) {
            $fields[] = FileUpload::make('video')
                ->label('Upload video (optional)')
                ->disk('public')
                ->directory('videos')
                ->acceptedFileTypes(['video/mp4', 'video/quicktime'])
                ->helperText('Leave empty to use the post\'s existing video or the URL below.');
            $fields[] = TextInput::make('video_url')
                ->label('Public video URL (optional)')
                ->url();
        }

        return $fields;
    }

    protected static function optionsFromData(array $data): array
    {
        return [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? '',
            'caption' => $data['description'] ?? '',
            'hashtags' => $data['hashtags'] ?? '',
            'privacy' => $data['privacy'] ?? 'public',
        ];
    }

    protected static function providersFromData(array $data): array
    {
        $providers = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'platform_') && $value) {
                $providers[] = substr($key, strlen('platform_'));
            }
        }

        return $providers;
    }

    protected static function crossPostAction(): Action
    {
        return Action::make('crosspost')
            ->label('Cross-post')
            ->icon('heroicon-o-paper-airplane')
            ->schema(fn (Post $record): array => self::formSchema($record->user_id, $record))
            ->action(function (array $data, Post $record): void {
                // Resolve the chosen video source onto the post.
                if (! empty($data['video'])) {
                    $record->update(['download_path' => $data['video']]);
                } elseif (! empty($data['video_url'])) {
                    $record->update(['video_url' => $data['video_url']]);
                }

                $providers = self::providersFromData($data);
                if (empty($providers)) {
                    Notification::make()->title('Pick at least one platform')->warning()->send();

                    return;
                }

                $result = app(CrossPostService::class)->dispatch($record, $providers, self::optionsFromData($data));

                Notification::make()
                    ->title('Cross-post queued')
                    ->body(self::summaryLine($result))
                    ->success()
                    ->send();
            });
    }

    protected static function crossPostBulkAction(): BulkAction
    {
        return BulkAction::make('crosspost_bulk')
            ->label('Cross-post')
            ->icon('heroicon-o-paper-airplane')
            ->schema(fn (): array => self::formSchema(auth()->id()))
            ->action(function (array $data, Collection $records): void {
                $providers = self::providersFromData($data);
                if (empty($providers)) {
                    Notification::make()->title('Pick at least one platform')->warning()->send();

                    return;
                }

                $service = app(CrossPostService::class);
                foreach ($records as $record) {
                    $service->dispatch($record, $providers, self::optionsFromData($data));
                }

                Notification::make()
                    ->title('Cross-post queued for '.$records->count().' post(s)')
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    protected static function summaryLine(array $result): string
    {
        $parts = [];
        if (! empty($result['dispatched'])) {
            $parts[] = 'Queued: '.implode(', ', $result['dispatched']);
        }
        foreach ($result['skipped'] as $skip) {
            $parts[] = 'Skipped '.$skip['provider'].' ('.$skip['reason'].')';
        }

        return implode(' — ', $parts) ?: 'Nothing to do.';
    }
}
