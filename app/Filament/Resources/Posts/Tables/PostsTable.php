<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use App\Models\PostDistribution;
use App\Services\PostDeletionService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Posts list (GET /api/posts): search on title/caption, platform filter, delete via PostDeletionService. */
class PostsTable
{
    /** Shown on every delete confirm (PARITY P5). */
    public const DELETE_NOTICE = 'This deletes the post, its stored video and its analytics from ClipPipeline. It does not delete anything already published on YouTube, Facebook, Instagram or TikTok.';

    public const CANCEL_NOTICE = 'The post will not be published. It is removed from ClipPipeline together with its stored video.';

    public const PLATFORM_OPTIONS = [
        'youtube' => 'YouTube',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('distributions'))
            ->defaultSort('created_at', 'desc')
            // Refresh every 5s while any of the user's distributions is still publishing.
            ->poll(fn (): ?string => self::hasInFlight() ? '5s' : null)
            ->columns([
                TextColumn::make('title')
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::search($query, $search))
                    ->description(fn (Post $record): ?string => $record->caption ? str($record->caption)->limit(60)->toString() : null)
                    ->limit(40),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (Post $record): string => $record->overallStatus())
                    ->badge()
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('distributions_summary')
                    ->label('Platforms')
                    ->state(fn (Post $record): string => $record->distributions
                        ->map(fn ($d) => (self::PLATFORM_OPTIONS[$d->platform] ?? ucfirst($d->platform)).': '.$d->status)
                        ->implode(' · ') ?: '—')
                    ->wrap(),
                TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Same as GET /api/posts?platform= : posts with a distribution on that platform.
                SelectFilter::make('platform')
                    ->label('Platform')
                    ->options(self::PLATFORM_OPTIONS)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('distributions', fn (Builder $q) => $q->where('platform', $data['value']))
                        : $query),
            ])
            ->recordActions([
                ViewAction::make(),
                self::cancelScheduledAction(),
                self::deleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label('Delete selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected posts?')
                        ->modalDescription(self::DELETE_NOTICE)
                        ->action(function (Collection $records, PostDeletionService $deletion): void {
                            $records->each(fn (Post $post) => $deletion->delete($post));

                            Notification::make()->title($records->count().' post(s) deleted')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /** Title or caption, case-insensitive (GET /api/posts?q=). */
    public static function search(Builder $query, string $search): Builder
    {
        $term = '%'.mb_strtolower($search).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw('LOWER(title) LIKE ?', [$term])
            ->orWhereRaw('LOWER(caption) LIKE ?', [$term]));
    }

    public static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this post?')
            ->modalDescription(self::DELETE_NOTICE)
            ->modalSubmitActionLabel('Delete')
            ->action(function (Post $record, PostDeletionService $deletion, Action $action): void {
                $deletion->delete($record);

                Notification::make()->title('Post deleted')->success()->send();
                $action->success();
            });
    }

    /** COMPLIANCE P-05: scheduled posts can be cancelled before they go out. */
    public static function cancelScheduledAction(): Action
    {
        return Action::make('cancelScheduled')
            ->label('Cancel schedule')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('warning')
            ->visible(fn (Post $record): bool => $record->overallStatus() === 'scheduled')
            ->requiresConfirmation()
            ->modalHeading('Cancel this scheduled post?')
            ->modalDescription(self::CANCEL_NOTICE)
            ->modalSubmitActionLabel('Cancel post')
            ->action(function (Post $record, PostDeletionService $deletion, Action $action): void {
                $deletion->delete($record);

                Notification::make()->title('Scheduled post cancelled')->success()->send();
                $action->success();
            });
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'published' => 'success',
            'failed' => 'danger',
            'partial' => 'warning',
            'scheduled' => 'info',
            default => 'gray',
        };
    }

    /** True while any of the signed-in user's distributions is queued/uploading/processing. */
    protected static function hasInFlight(): bool
    {
        return PostDistribution::query()
            ->whereIn('status', PostDistribution::IN_FLIGHT)
            ->whereHas('post', fn (Builder $q) => $q->where('user_id', auth()->id()))
            ->exists();
    }
}
