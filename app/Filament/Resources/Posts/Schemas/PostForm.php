<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('social_account_id')
                    ->relationship('socialAccount', 'id'),
                TextInput::make('title')
                    ->required(),
                Textarea::make('video_url')
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('published_at'),
                TextInput::make('tiktok_id'),
                Textarea::make('caption')
                    ->columnSpanFull(),
                TextInput::make('download_path'),
                Textarea::make('original_url')
                    ->columnSpanFull(),
                DateTimePicker::make('downloaded_at'),
                Textarea::make('meta')
                    ->columnSpanFull(),
            ]);
    }
}
