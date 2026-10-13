<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Models\PostDistribution;
use App\Services\CrossPostService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
