<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\RelationManagers\DistributionsRelationManager;
use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Jobs\PublishToTikTokJob;
use App\Jobs\PublishToYouTubeShortsJob;
use App\Models\Post;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
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
}
