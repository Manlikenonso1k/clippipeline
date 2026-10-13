<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/** Analytics dashboard; the period filter feeds every widget (same periods as /api/analytics/*). */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Period')
                ->options([
                    '7d' => 'Last 7 days',
                    '30d' => 'Last 30 days',
                    '90d' => 'Last 90 days',
                ])
                ->default('7d')
                ->selectablePlaceholder(false),
        ]);
    }
}
