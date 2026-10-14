<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use App\Models\PostDistribution;
use Filament\Resources\Pages\ViewRecord;

/** Post details: per-platform status lives in the Distributions relation manager. */
class ViewPost extends ViewRecord
{
    protected static string $resource = PostResource::class;

    public const PROCESSING_NOTICE = 'Publishing can take a few minutes to process on each platform. This page refreshes on its own.';

    public function getSubheading(): ?string
    {
        /** @var Post $post */
        $post = $this->getRecord();
        $status = $post->overallStatus();

        if ($status === 'scheduled') {
            return 'Scheduled for '.$post->scheduled_at?->toDayDateTimeString().' (UTC). You can cancel it until then.';
        }

        $inFlight = $post->distributions()->whereIn('status', PostDistribution::IN_FLIGHT)->exists();

        return $inFlight ? self::PROCESSING_NOTICE : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            PostsTable::cancelScheduledAction()
                ->successRedirectUrl(PostResource::getUrl('index')),
            PostsTable::deleteAction()
                ->successRedirectUrl(PostResource::getUrl('index')),
        ];
    }
}
