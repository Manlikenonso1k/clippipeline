<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ConnectAccounts extends Page
{
    /**
     * Allow string or BackedEnum (matches parent type)
     *
     * @var \BackedEnum|string|null
     */
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Platform Integrations';
    protected static string $view = 'filament.pages.connect-accounts';
}
