<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class PopularPostsWidget extends BaseWidget
{
    protected static ?string $heading = 'Most Popular Posts';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->query(
                Post::where('user_id', $user->id)
                    ->with('distributions', 'analytics')
                    ->orderByDesc(
                        DB::table('post_analytics')
                            ->selectRaw('SUM(views + likes + comments)')
                            ->whereColumn('post_id', 'posts.id')
                    )
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Post ID')
                    ->sortable(),

                TextColumn::make('caption')
                    ->label('Caption')
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('analytics')
                    ->label('Total Views')
                    ->formatStateUsing(fn ($state, Post $record) => 
                        number_format(
                            $record->analytics()->sum('views') ?? 0
                        )
                    ),

                TextColumn::make('engagement')
                    ->label('Total Engagement')
                    ->formatStateUsing(fn ($state, Post $record) => 
                        number_format(
                            $record->getCombinedEngagement()
                        )
                    ),

                BadgeColumn::make('distributions')
                    ->label('Platforms')
                    ->formatStateUsing(fn ($state, Post $record) => 
                        implode(', ', $record->distributions()->pluck('platform')->toArray())
                    )
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable(),
            ])
            ->striped()
            ->paginated([10]);
    }
}
