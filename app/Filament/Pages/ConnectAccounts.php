<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ConnectAccounts extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Platform Integrations';
    protected static string $view = 'filament.pages.connect-accounts';
}
