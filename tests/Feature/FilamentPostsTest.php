<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\RelationManagers\DistributionsRelationManager;
use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Jobs\PublishToTikTokJob;
use App\Jobs\PublishToYouTubeShortsJob;
use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\TikTok\CreatorInfoService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentPostsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://clip.test']);
        Storage::fake('public');
        Queue::fake();
        Http::fake(['clip.test/*' => Http::response('', 200)]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->user = User::factory()->create(['is_admin' => true]);
        SocialAccount::factory()->for($this->user)->platform('youtube')->create();
        SocialAccount::factory()->for($this->user)->platform('tiktok')->needsReauth()->create();

        $this->actingAs($this->user);
    }

    private function makePost(User $user, string $title): Post
    {
        return Post::create(['user_id' => $user->id, 'title' => $title, 'video_url' => 'https://cdn.test/v.mp4']);
    }

    private function video(): UploadedFile
    {
        return UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4');
    }

    public function test_list_is_scoped_to_the_owner(): void
    {
        $mine = $this->makePost($this->user, 'Mine');
        $theirs = $this->makePost(User::factory()->create(), 'Theirs');

        Livewire::test(ListPosts::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->assertSame([$mine->id], PostResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_list_and_create_pages_render_with_only_active_platforms(): void
    {
        $this->makePost($this->user, 'Mine');

        $this->get(PostResource::getUrl('index'))->assertOk()->assertSeeText('Mine');
        $this->get(PostResource::getUrl('create'))->assertOk()
            ->assertSeeText('YouTube Shorts');

        // needs_reauth TikTok is not offered.
        $this->assertSame(['youtube' => 'YouTube Shorts'], PostForm::platformOptions());
    }

    public function test_view_page_404s_for_another_users_post(): void
    {
        $mine = $this->makePost($this->user, 'Mine');
        $theirs = $this->makePost(User::factory()->create(), 'Theirs');

        $this->get(PostResource::getUrl('view', ['record' => $mine]))->assertOk()->assertSeeText('Mine');
        $this->get(PostResource::getUrl('view', ['record' => $theirs]))->assertNotFound();
    }

    public function test_create_runs_the_api_upload_flow(): void
    {
        Livewire::test(CreatePost::class)
            ->set('data.video', [$this->video()])
            ->fillForm([
                'title' => 'Web clip',
                'caption' => 'From the dashboard',
                'hashtags' => ['#fyp', 'shorts'],
                'platforms' => ['youtube'],
                'yt_guidelines' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('title', 'Web clip')->firstOrFail();
        $this->assertSame($this->user->id, $post->user_id);
        $this->assertSame(Post::SOURCE_UPLOAD, $post->source);
        $this->assertSame(['fyp', 'shorts'], $post->hashtags);
        $this->assertStringStartsWith('https://clip.test/', $post->video_url);
        Storage::disk('public')->assertExists($post->video_path);

        $this->assertSame(['youtube' => 'queued'], $post->distributions()->pluck('status', 'platform')->all());
        Queue::assertPushed(PublishToYouTubeShortsJob::class, 1);
        Queue::assertNotPushed(PublishToTikTokJob::class);
    }

    public function test_create_scheduled_post_does_not_dispatch(): void
    {
        Livewire::test(CreatePost::class)
            ->set('data.video', [$this->video()])
            ->fillForm([
                'title' => 'Later',
                'platforms' => ['youtube'],
                'yt_guidelines' => true,
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNotNull(Post::where('title', 'Later')->firstOrFail()->scheduled_at);
        Queue::assertNothingPushed();
    }

    public function test_create_rejects_platforms_without_an_active_account(): void
    {
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('platforms')
            ->set('data.video', [$this->video()])
            ->fillForm([
                'title' => 'Nope',
                'platforms' => ['tiktok'],
            ])
            ->call('create')
            ->assertHasFormErrors(['platforms.0']);

        $this->assertSame(0, Post::count());
        Queue::assertNothingPushed();
    }

    public function test_distributions_show_status_and_failed_ones_can_be_retried(): void
    {
        $post = $this->makePost($this->user, 'Mine');
        $failed = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'failed', 'error_message' => 'quotaExceeded']);
        $done = PostDistribution::create(['post_id' => $post->id, 'platform' => 'tiktok', 'status' => 'published', 'external_id' => 'v1', 'privacy' => 'SELF_ONLY']);

        Livewire::test(DistributionsRelationManager::class, ['ownerRecord' => $post, 'pageClass' => ViewPost::class])
            ->assertCanSeeTableRecords([$failed, $done])
            ->assertSeeText('quotaExceeded')
            ->assertSeeText('SELF_ONLY')
            ->assertActionVisible(TestAction::make('retry')->table($failed))
            ->assertActionHidden(TestAction::make('retry')->table($done))
            ->callAction(TestAction::make('retry')->table($failed));

        $failed->refresh();
        $this->assertSame('queued', $failed->status);
        $this->assertNull($failed->error_message);
        Queue::assertPushed(PublishToYouTubeShortsJob::class, 1);
    }

    // --- parity (WEB) ---

    private function creatorInfo(array $overrides = []): array
    {
        return array_merge([
            'creator_nickname' => 'Nonso',
            'creator_username' => 'nonso',
            'creator_avatar_url' => 'https://cdn.test/avatar.jpg',
            'privacy_level_options' => ['SELF_ONLY'],
            'comment_disabled' => false,
            'duet_disabled' => false,
            'stitch_disabled' => true,
            'max_video_post_duration_sec' => 600,
            'can_post' => true,
            'reason' => null,
            'audited' => false,
        ], $overrides);
    }

    /** A user with active YouTube + TikTok; CreatorInfoService returns $creator. */
    private function tiktokUser(array $creator, int $expectedCalls = 2): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        SocialAccount::factory()->for($user)->platform('youtube')->create();
        SocialAccount::factory()->for($user)->platform('tiktok')->create();
        $this->actingAs($user);

        $mock = $this->mock(CreatorInfoService::class);
        // Once on open, once more when TikTok is ticked (T-01).
        $mock->shouldReceive('forUser')->times($expectedCalls)->andReturn($creator);
        // PublishRules re-checks against the last creator_info served (T-09).
        $mock->shouldReceive('lastForUser')->andReturn($creator);

        return $user;
    }

    public function test_youtube_section_shows_tos_guidelines_and_private_notice(): void
    {
        config(['clippipeline.youtube_audited' => false]);

        $this->get(PostResource::getUrl('create'))->assertOk()
            ->assertSeeText('YouTube Terms of Service');

        Livewire::test(CreatePost::class)
            ->fillForm(['platforms' => ['youtube']])
            ->assertSeeText(PostForm::YOUTUBE_PRIVATE_NOTICE)
            ->assertSeeText('Community Guidelines')
            ->assertFormSet(['yt_privacy' => 'private'])
            ->assertFormFieldDisabled('yt_privacy')
            ->assertSeeText(PostForm::PROCESSING_NOTICE)
            ->assertSeeText('Check before publishing');
    }

    public function test_youtube_requires_guidelines_confirmation(): void
    {
        Livewire::test(CreatePost::class)
            ->set('data.video', [$this->video()])
            ->fillForm(['title' => 'No confirm', 'platforms' => ['youtube']])
            ->call('create')
            ->assertHasFormErrors(['yt_guidelines']);

        $this->assertSame(0, Post::count());
    }

    public function test_confirm_step_lists_destinations_and_settings(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Summary clip',
                'caption' => 'Caption here',
                'hashtags' => ['fyp'],
                'platforms' => ['youtube'],
                'yt_guidelines' => true,
            ])
            ->assertSeeText('Summary clip')
            ->assertSeeText('Caption here')
            ->assertSeeText('#fyp')
            ->assertSeeText('YouTube Shorts')
            ->assertSeeText('Privacy: Private')
            ->assertSeeText('Community Guidelines confirmed')
            ->assertSeeText('Publish now');
    }

    public function test_platform_options_follow_the_api_shape(): void
    {
        $options = PostForm::platformOptionsFromData([
            'platforms' => ['youtube', 'tiktok'],
            'yt_privacy' => 'private',
            'yt_guidelines' => true,
            'tt_privacy_level' => 'FOLLOWER_OF_CREATOR',
            'tt_allow_comment' => true,
            'tt_allow_duet' => false,
            'tt_disclose' => true,
            'tt_brand_organic' => true,
            'tt_brand_content' => false,
            'tt_is_aigc' => true,
        ]);

        $this->assertSame(['privacy_status' => 'private', 'guidelines_confirmed' => true], $options['youtube']);
        $this->assertSame([
            'privacy_level' => 'FOLLOWER_OF_CREATOR',
            'disable_comment' => false,
            'disable_duet' => true,
            'disable_stitch' => true,
            'brand_organic_toggle' => true,
            'brand_content_toggle' => false,
            'is_aigc' => true,
            'music_consent' => true,
        ], $options['tiktok']);

        // Brand toggles only count while "Disclose video content" is on.
        $off = PostForm::platformOptionsFromData(['platforms' => ['tiktok'], 'tt_brand_content' => true]);
        $this->assertFalse($off['tiktok']['brand_content_toggle']);
        $this->assertArrayNotHasKey('youtube', $off);
    }

    public function test_tiktok_form_loads_creator_info_each_time_and_has_no_privacy_default(): void
    {
        $creator = $this->creatorInfo();
        $this->tiktokUser($creator);

        Livewire::test(CreatePost::class)
            ->assertSet('tiktokCreator', $creator)
            ->fillForm(['platforms' => ['tiktok']]) // T-01: fetched again when TikTok is picked
            ->assertSeeText('Posting to @nonso (Nonso)')
            ->assertSeeText(PostForm::TIKTOK_PRIVATE_NOTICE)
            ->assertSeeText('Only me')
            ->assertSeeText('600 seconds')
            ->assertFormSet([
                'tt_privacy_level' => null,
                'tt_allow_comment' => false,
                'tt_allow_duet' => false,
                'tt_allow_stitch' => false,
                'tt_disclose' => false,
            ])
            ->assertFormFieldEnabled('tt_allow_comment')
            ->assertFormFieldDisabled('tt_allow_stitch')
            ->assertSeeText('Turned off in your TikTok privacy settings.')
            ->assertSeeText('Music Usage Confirmation')
            ->assertDontSeeText('Branded Content Policy')
            ->fillForm(['tt_disclose' => true, 'tt_brand_content' => true])
            ->assertSeeText(PostForm::BRAND_CONTENT_LABEL)
            ->assertSeeText('Branded Content Policy');
    }

    public function test_tiktok_requires_privacy_choice_and_disclosure_rules(): void
    {
        $this->tiktokUser($this->creatorInfo(['privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'], 'audited' => true]));

        $page = Livewire::test(CreatePost::class)
            ->set('data.video', [$this->video()])
            ->fillForm(['title' => 'TT', 'platforms' => ['tiktok']]);
        $page->call('create')->assertHasFormErrors(['tt_privacy_level']);

        // Disclosure on: at least one of Your brand / Branded content.
        $page->fillForm(['tt_privacy_level' => 'PUBLIC_TO_EVERYONE', 'tt_disclose' => true])
            ->call('create')
            ->assertHasFormErrors(['tt_brand_organic']);

        // Branded content can't be SELF_ONLY.
        $page->fillForm(['tt_privacy_level' => 'SELF_ONLY', 'tt_brand_content' => true])
            ->call('create')
            ->assertHasFormErrors(['tt_brand_content']);

        // An option TikTok did not offer is rejected.
        $page->fillForm(['tt_privacy_level' => 'MUTUAL_FOLLOW_FRIENDS', 'tt_disclose' => false])
            ->call('create')
            ->assertHasFormErrors(['tt_privacy_level']);

        $this->assertSame(0, Post::count());
    }

    public function test_tiktok_blocked_when_creator_cannot_post(): void
    {
        $this->tiktokUser($this->creatorInfo(['can_post' => false, 'reason' => 'Daily post limit reached.']));

        Livewire::test(CreatePost::class)
            ->set('data.video', [$this->video()])
            ->fillForm(['title' => 'TT', 'platforms' => ['tiktok'], 'tt_privacy_level' => 'SELF_ONLY'])
            ->assertSeeText('Daily post limit reached.')
            ->call('create')
            ->assertHasFormErrors(['tt_privacy_level']);

        $this->assertSame(0, Post::count());
    }

    public function test_tiktok_create_queues_a_tiktok_distribution(): void
    {
        $this->tiktokUser($this->creatorInfo());

        Livewire::test(CreatePost::class)
            ->set('data.video', [$this->video()])
            ->fillForm(['title' => 'TT ok', 'platforms' => ['tiktok'], 'tt_privacy_level' => 'SELF_ONLY'])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('title', 'TT ok')->firstOrFail();
        $distribution = $post->distributions()->firstOrFail();
        $this->assertSame('tiktok', $distribution->platform);
        $this->assertSame('queued', $distribution->status);
        Queue::assertPushed(PublishToTikTokJob::class, 1);
    }

    public function test_list_search_matches_caption_and_platform_filter(): void
    {
        $beach = Post::create(['user_id' => $this->user->id, 'title' => 'Clip one', 'caption' => 'At the Beach today', 'video_url' => 'https://cdn.test/1.mp4']);
        $city = Post::create(['user_id' => $this->user->id, 'title' => 'Clip two', 'caption' => 'City lights', 'video_url' => 'https://cdn.test/2.mp4']);
        PostDistribution::create(['post_id' => $beach->id, 'platform' => 'tiktok', 'status' => 'published']);
        PostDistribution::create(['post_id' => $city->id, 'platform' => 'youtube', 'status' => 'published']);

        Livewire::test(ListPosts::class)
            ->searchTable('beach')
            ->assertCanSeeTableRecords([$beach])
            ->assertCanNotSeeTableRecords([$city])
            ->searchTable(null)
            ->filterTable('platform', 'youtube')
            ->assertCanSeeTableRecords([$city])
            ->assertCanNotSeeTableRecords([$beach]);
    }

    public function test_delete_uses_post_deletion_service_and_explains_platforms_are_untouched(): void
    {
        $this->assertStringContainsString('does not delete anything already published', PostsTable::DELETE_NOTICE);

        $post = $this->makePost($this->user, 'Gone');
        PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published']);

        Livewire::test(ListPosts::class)
            ->callAction(TestAction::make('delete')->table($post));

        $this->assertModelMissing($post);
        $this->assertSame(0, PostDistribution::where('post_id', $post->id)->count());

        $other = $this->makePost($this->user, 'Also gone');
        Livewire::test(ViewPost::class, ['record' => $other->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect(PostResource::getUrl('index'));
        $this->assertModelMissing($other);
    }

    public function test_scheduled_posts_can_be_cancelled(): void
    {
        $scheduled = Post::create(['user_id' => $this->user->id, 'title' => 'Later', 'video_url' => 'https://cdn.test/v.mp4', 'scheduled_at' => now()->addDay()]);
        PostDistribution::create(['post_id' => $scheduled->id, 'platform' => 'youtube', 'status' => 'queued']);
        $published = $this->makePost($this->user, 'Done');
        PostDistribution::create(['post_id' => $published->id, 'platform' => 'youtube', 'status' => 'published']);

        Livewire::test(ListPosts::class)
            ->assertActionHidden(TestAction::make('cancelScheduled')->table($published))
            ->assertActionVisible(TestAction::make('cancelScheduled')->table($scheduled))
            ->callAction(TestAction::make('cancelScheduled')->table($scheduled));

        $this->assertModelMissing($scheduled);
        $this->assertModelExists($published);
    }

    public function test_view_page_metrics_match_the_post_analytics_api(): void
    {
        $post = $this->makePost($this->user, 'Stats');
        $d = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published', 'external_id' => 'abc', 'published_at' => now()->subDays(3)]);
        PostAnalytic::factory()->forDistribution($d)->create(['views' => 4321, 'likes' => 87, 'comments' => 9, 'shares' => 0, 'saves' => 0, 'watch_time_seconds' => 0]);

        $api = $this->actingAs($this->user, 'sanctum')->getJson("/api/posts/{$post->id}/analytics")->assertOk()->json();

        $manager = Livewire::test(DistributionsRelationManager::class, ['ownerRecord' => $post, 'pageClass' => ViewPost::class]);
        $this->assertEquals($api['platforms'][0]['metrics'], $manager->instance()->metricsFor($d)['metrics']);
        $manager->assertSeeText('4,321')->assertSeeText('87');
    }
}
