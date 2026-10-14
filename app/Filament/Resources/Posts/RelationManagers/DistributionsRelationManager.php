<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Models\Post;
use App\Models\PostDistribution;
use App\Services\AnalyticsService;
use App\Services\CrossPostService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Per-platform publish status (docs/CONTRACT.md §4 PostDistribution). */
class DistributionsRelationManager extends RelationManager
{
    protected static string $relationship = 'distributions';

    protected static ?string $title = 'Distributions';

    /** @var array<int, array<string, mixed>>|null per-distribution latest metrics from AnalyticsService::post (§5.4) */
    private ?array $metrics = null;

    /** Latest snapshot per distribution, exactly as GET /api/posts/{id}/analytics. */
    public function metricsFor(PostDistribution $distribution): array
    {
        if ($this->metrics === null) {
            /** @var Post $post */
            $post = $this->getOwnerRecord();
            $this->metrics = collect(app(AnalyticsService::class)->post($post)['platforms'])
                ->keyBy('distribution_id')
                ->all();
        }

        return $this->metrics[$distribution->id] ?? ['metrics' => app(AnalyticsService::class)->zeroMetrics(), 'captured_on' => null];
    }

    /** Shown on the View page; the Retry action must stay available there. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('platform')
            // Refresh every 5s while any platform is still queued/uploading/processing.
            ->poll(fn (): ?string => $this->getOwnerRecord()->distributions()
                ->whereIn('status', [...PostDistribution::IN_FLIGHT, 'pending'])
                ->exists() ? '5s' : null)
            ->columns([
                TextColumn::make('platform')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'pending' ? PostDistribution::STATUS_QUEUED : $state)
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'failed' => 'danger',
                        'uploading', 'processing' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('url')
                    ->label('Link')
                    ->state(fn (PostDistribution $record): ?string => $record->isPublished() ? $record->externalUrl() : null)
                    ->url(fn (PostDistribution $record): ?string => $record->isPublished() ? $record->externalUrl() : null, shouldOpenInNewTab: true)
                    ->formatStateUsing(fn (): string => 'Open')
                    ->placeholder('—'),
                TextColumn::make('privacy')
                    ->placeholder('—'),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->state(fn (PostDistribution $record): ?string => $record->error_message
                        ?? ($record->isFailed() ? $record->error : null))
                    ->wrap()
                    ->limit(120)
                    ->placeholder('—'),
                TextColumn::make('views')
                    ->state(fn (PostDistribution $record): int => (int) $this->metricsFor($record)['metrics']['views'])
                    ->numeric(),
                TextColumn::make('likes')
                    ->state(fn (PostDistribution $record): int => (int) $this->metricsFor($record)['metrics']['likes'])
                    ->numeric(),
                TextColumn::make('comments')
                    ->state(fn (PostDistribution $record): int => (int) $this->metricsFor($record)['metrics']['comments'])
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('captured_on')
                    ->label('Stats as of')
                    ->state(fn (PostDistribution $record): ?string => $this->metricsFor($record)['captured_on'] ?? null)
                    ->placeholder('Not synced yet')
                    ->toggleable(),
                TextColumn::make('attempts')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (PostDistribution $record): bool => $record->isFailed())
                    ->requiresConfirmation()
                    ->action(function (PostDistribution $record): void {
                        try {
                            app(CrossPostService::class)->retry($record);
                        } catch (ConflictHttpException $e) {
                            Notification::make()->title($e->getMessage())->warning()->send();

                            return;
                        }

                        Notification::make()->title('Retrying '.$record->platform)->success()->send();
                    }),
            ]);
    }
}
