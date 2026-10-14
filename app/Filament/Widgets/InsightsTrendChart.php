<?php

namespace App\Filament\Widgets;

/** Insights timeseries: period and platform both come from the page filters (§5.3). */
class InsightsTrendChart extends ViewsTrendChart
{
    protected ?string $heading = 'Daily views & engagement';

    protected function getFilters(): ?array
    {
        return null;
    }

    public function timeseries(): array
    {
        return $this->analytics()->timeseries($this->user(), $this->period(), $this->platform());
    }
}
