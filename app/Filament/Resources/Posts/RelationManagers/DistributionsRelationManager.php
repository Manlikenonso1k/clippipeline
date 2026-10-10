<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Models\PostDistribution;
use App\Services\CrossPostService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DistributionsRelationManager extends RelationManager
{
    protected static string $relationship = 'distributions';

    protected static ?string $title = 'Distributions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('platform')
            ->columns([
                TextColumn::make('platform')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'failed' => 'danger',
                        'processing' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('external_id')
                    ->label('Link')
                    ->url(fn (PostDistribution $record): ?string => $record->externalUrl(), shouldOpenInNewTab: true)
                    ->formatStateUsing(fn (?string $state): string => $state ? 'View' : '—'),
                TextColumn::make('error')
                    ->label('Error')
                    ->wrap()
                    ->limit(80)
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (PostDistribution $record): bool => $record->isFailed())
                    ->requiresConfirmation()
                    ->action(function (PostDistribution $record): void {
                        $registry = app(CrossPostService::class)->registry();
                        $job = $registry[$record->platform]['job'] ?? null;

                        if (! $job) {
                            Notification::make()->title('No publisher available for '.$record->platform)->warning()->send();

                            return;
                        }

                        $record->update([
                            'status' => PostDistribution::STATUS_QUEUED,
                            'error' => null,
                            'error_message' => null,
                            'external_id' => null,
                        ]);

                        $job::dispatch($record->id);

                        Notification::make()->title('Retrying '.$record->platform)->success()->send();
                    }),
            ]);
    }
}
