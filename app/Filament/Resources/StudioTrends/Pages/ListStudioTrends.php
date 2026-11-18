<?php

namespace App\Filament\Resources\StudioTrends\Pages;

use App\Filament\Resources\StudioTrends\StudioTrendResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudioTrends extends ListRecords
{
    protected static string $resource = StudioTrendResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
