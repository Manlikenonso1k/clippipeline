<?php

namespace App\Filament\Resources\StudioTrends;

use App\Filament\Resources\StudioTrends\Pages\CreateStudioTrend;
use App\Filament\Resources\StudioTrends\Pages\EditStudioTrend;
use App\Filament\Resources\StudioTrends\Pages\ListStudioTrends;
use App\Studio\Models\StudioTrend;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-only list of trending topics shown in the app's "Make a video" flow.
 * YouTube rows are refreshed automatically (studio:trends-refresh); TikTok and Instagram have no
 * trending API for apps, so add those here (e.g. from TikTok Creative Center, by hand).
 */
class StudioTrendResource extends Resource
{
    protected static ?string $model = StudioTrend::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $navigationLabel = 'Studio trends';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform')->options(['tiktok' => 'TikTok', 'youtube' => 'YouTube', 'instagram' => 'Instagram'])->required(),
            TextInput::make('region')->default('NG')->required()->length(2)->helperText('Two letters, e.g. NG, US, GB. Use GL for worldwide.'),
            TextInput::make('title')->required()->maxLength(120)->helperText('What the trend is about, e.g. "GTA 6 trailer reactions".'),
            TextInput::make('hook')->maxLength(24)->helperText('Short on-screen hook, e.g. "GTA 6". Leave empty to use the first words of the title.'),
            TagsInput::make('hashtags')->helperText('Without #.'),
            TextInput::make('url')->url()->maxLength(255),
            TextInput::make('score')->numeric()->default(0)->helperText('Higher shows first.'),
            DateTimePicker::make('expires_at')->default(now()->addDays(7)),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('score', 'desc')
            ->columns([
                TextColumn::make('platform')->badge()->sortable(),
                TextColumn::make('region'),
                TextColumn::make('title')->searchable()->limit(50),
                TextColumn::make('hook'),
                TextColumn::make('source')->badge(),
                TextColumn::make('score')->numeric()->sortable(),
                TextColumn::make('expires_at')->since()->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('platform')->options(['tiktok' => 'TikTok', 'youtube' => 'YouTube', 'instagram' => 'Instagram']),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudioTrends::route('/'),
            'create' => CreateStudioTrend::route('/create'),
            'edit' => EditStudioTrend::route('/{record}/edit'),
        ];
    }
}
