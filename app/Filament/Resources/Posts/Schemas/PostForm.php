<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Account\PreferencesService;
use App\Services\CrossPostService;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Create: same fields and rules as POST /api/posts (docs/CONTRACT.md §4, §12),
 * as a wizard ending in an explicit confirm step (COMPLIANCE P-01).
 * View: read-only summary; per-platform status lives in the Distributions relation manager.
 */
class PostForm
{
    public const YOUTUBE_TOS_URL = 'https://www.youtube.com/t/terms';

    public const YOUTUBE_GUIDELINES_URL = 'https://www.youtube.com/howyoutubeworks/policies/community-guidelines/';

    public const TIKTOK_MUSIC_URL = 'https://www.tiktok.com/legal/page/global/music-usage-confirmation/en';

    public const TIKTOK_BRANDED_URL = 'https://www.tiktok.com/legal/page/global/bc-policy/en';

    public const YOUTUBE_PRIVATE_NOTICE = "YouTube uploads stay private until ClipPipeline passes YouTube's audit.";

    public const TIKTOK_PRIVATE_NOTICE = "TikTok posts stay private until ClipPipeline passes TikTok's review.";

    public const PROCESSING_NOTICE = 'After you publish, each platform can take a few minutes to process the video.';

    public const YOUTUBE_PRIVACY = ['public' => 'Public', 'unlisted' => 'Unlisted', 'private' => 'Private'];

    public const TIKTOK_PRIVACY_LABELS = [
        'PUBLIC_TO_EVERYONE' => 'Everyone',
        'MUTUAL_FOLLOW_FRIENDS' => 'Friends',
        'FOLLOWER_OF_CREATOR' => 'Followers',
        'SELF_ONLY' => 'Only me',
    ];

    public const BRAND_ORGANIC_LABEL = "Your photo/video will be labeled as 'Promotional content'";

    public const BRAND_CONTENT_LABEL = "Your photo/video will be labeled as 'Paid partnership'";

    /** View page (the create page replaces this with the wizard steps below). */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->maxLength(100),
                DateTimePicker::make('scheduled_at')
                    ->label('Scheduled for')
                    ->seconds(false),
                Textarea::make('caption')
                    ->rows(3)
                    ->columnSpanFull(),
                TagsInput::make('hashtags')
                    ->columnSpanFull(),
                TextInput::make('video_url')
                    ->label('Video URL')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  Closure(): ?array  $creator  TikTok creator info (CreatorInfoService) for the open form
     * @param  Closure(): ?string  $creatorError  why creator info could not be loaded
     * @param  Closure(): void  $refreshCreator  re-fetch creator info (T-01: every time TikTok is picked)
     * @return array<int, Step>
     */
    public static function createSteps(Closure $creator, Closure $creatorError, Closure $refreshCreator): array
    {
        return [
            Step::make('Video')
                ->description('File and details')
                ->schema(self::detailsFields()),
            Step::make('Platforms')
                ->description('Where and how to post')
                ->schema([
                    self::platformsField($refreshCreator),
                    self::youtubeSection(),
                    self::tiktokSection($creator, $creatorError),
                ]),
            Step::make('Review')
                ->description('Confirm and publish')
                ->schema(self::reviewFields($creator)),
        ];
    }

    /** @return array<int, mixed> */
    protected static function detailsFields(): array
    {
        $maxMb = (int) config('clippipeline.max_upload_mb', 200);

        return [
            FileUpload::make('video')
                ->label('Video')
                ->required()
                // Kept as a temporary upload: PostUploadService stores it on the public disk.
                ->storeFiles(false)
                ->disk('public')
                // Livewire's temporary upload rules (config/livewire.php) allow mp4/mov only.
                ->acceptedFileTypes(['video/mp4', 'video/quicktime'])
                ->maxSize($maxMb * 1024)
                ->helperText("MP4 or MOV, up to {$maxMb} MB.")
                ->live()
                ->columnSpanFull(),
            // COMPLIANCE P-02 / duration: browser-side checks (server-side PublishRules still enforces).
            View::make('filament.forms.video-checks')
                ->viewData(fn (Get $get): array => self::videoCheckData($get('video')))
                ->columnSpanFull(),
            TextInput::make('title')
                ->required()
                ->maxLength(100),
            DateTimePicker::make('scheduled_at')
                ->label('Schedule (optional)')
                ->seconds(false)
                ->after('now')
                ->helperText('Leave empty to publish now. Scheduled posts can be cancelled until they go out.'),
            Textarea::make('caption')
                ->maxLength(2200)
                ->rows(3)
                ->columnSpanFull(),
            TagsInput::make('hashtags')
                ->placeholder('Add a hashtag')
                ->splitKeys([' ', ','])
                ->default(fn (): array => self::preference('default_hashtags'))
                ->columnSpanFull(),
        ];
    }

    protected static function platformsField(Closure $refreshCreator): CheckboxList
    {
        return CheckboxList::make('platforms')
            ->label('Publish to')
            ->required()
            ->live()
            ->allowHtml()
            ->options(fn (): array => self::platformOptionsWithLogos())
            ->in(fn (): array => array_keys(self::platformOptions()))
            ->default(fn (): array => array_values(array_intersect(self::preference('default_platforms'), array_keys(self::platformOptions()))))
            ->descriptions([
                // COMPLIANCE P-02: platform limits shown next to the toggle.
                'instagram' => 'Reels: 3 s to 15 min, up to 300 MB.',
                'facebook' => 'Reels: 3 to 90 s.',
                // COMPLIANCE P-04: YouTube ToS link next to the YouTube toggle.
                'youtube' => new HtmlString('Shorts: up to 180 s. By publishing to YouTube you agree to the <a href="'.self::YOUTUBE_TOS_URL.'" target="_blank" rel="noopener" style="text-decoration: underline;">YouTube Terms of Service</a>.'),
            ])
            ->afterStateUpdated(function (?array $state, ?array $old) use ($refreshCreator): void {
                if (in_array('tiktok', $state ?? [], true) && ! in_array('tiktok', $old ?? [], true)) {
                    $refreshCreator();
                }
            })
            ->helperText(fn (): ?string => self::platformOptions() === []
                ? 'No active accounts. Connect one on the Connect page first.'
                : null)
            ->columns(2)
            ->columnSpanFull();
    }

    /** COMPLIANCE P-04. */
    protected static function youtubeSection(): Section
    {
        $audited = (bool) config('clippipeline.youtube_audited');

        return Section::make('YouTube')
            ->visible(fn (Get $get): bool => in_array('youtube', (array) $get('platforms'), true))
            ->schema([
                Callout::make(self::YOUTUBE_PRIVATE_NOTICE)
                    ->warning()
                    ->visible(! $audited),
                Select::make('yt_privacy')
                    ->label('Privacy')
                    ->options(self::YOUTUBE_PRIVACY)
                    ->default($audited ? null : 'private')
                    ->disabled(! $audited)
                    ->dehydrated()
                    ->required(fn (Get $get): bool => in_array('youtube', (array) $get('platforms'), true))
                    ->in(fn (): array => $audited ? array_keys(self::YOUTUBE_PRIVACY) : ['private']),
                Checkbox::make('yt_guidelines')
                    ->label(new HtmlString('I confirm this follows YouTube\'s <a href="'.self::YOUTUBE_GUIDELINES_URL.'" target="_blank" rel="noopener" style="text-decoration: underline;">Community Guidelines</a>'))
                    ->accepted(fn (Get $get): bool => in_array('youtube', (array) $get('platforms'), true))
                    ->validationMessages(['accepted' => "Confirm the video follows YouTube's Community Guidelines."]),
            ]);
    }

    /** COMPLIANCE T-01 … T-08, T-10. */
    protected static function tiktokSection(Closure $creator, Closure $creatorError): Section
    {
        $selected = fn (Get $get): bool => in_array('tiktok', (array) $get('platforms'), true);
        $audited = fn (): bool => (bool) ($creator()['audited'] ?? config('clippipeline.tiktok_audited'));

        return Section::make('TikTok')
            ->visible($selected)
            ->schema([
                Callout::make(fn (): string => 'Could not load your TikTok account: '.($creatorError() ?? 'unknown error').'. Reconnect TikTok on the Connect page.')
                    ->danger()
                    ->visible(fn (): bool => $creatorError() !== null),
                Image::make(fn (): string => (string) ($creator()['creator_avatar_url'] ?? ''), 'TikTok avatar')
                    ->imageSize(48)
                    ->visible(fn (): bool => filled($creator()['creator_avatar_url'] ?? null)),
                Text::make(fn (): string => 'Posting to @'.(($creator()['creator_username'] ?? '') ?: ($creator()['creator_nickname'] ?? ''))
                    .(filled($creator()['creator_nickname'] ?? null) ? ' ('.$creator()['creator_nickname'].')' : ''))
                    ->visible(fn (): bool => $creator() !== null),
                Callout::make(fn (): string => 'TikTok says this account cannot post right now'.(filled($creator()['reason'] ?? null) ? ': '.$creator()['reason'] : '.'))
                    ->danger()
                    ->visible(fn (): bool => $creator() !== null && ! ($creator()['can_post'] ?? true)),
                Callout::make(self::TIKTOK_PRIVATE_NOTICE)
                    ->warning()
                    ->visible(fn (): bool => ! $audited()),
                Select::make('tt_privacy_level')
                    ->label('Who can view this video')
                    // T-02: built only from creator_info, no default.
                    ->options(fn (): array => collect((array) ($creator()['privacy_level_options'] ?? []))
                        ->mapWithKeys(fn (string $o) => [$o => self::TIKTOK_PRIVACY_LABELS[$o] ?? $o])
                        ->all())
                    ->placeholder('Choose who can see this video')
                    ->required($selected)
                    ->live()
                    // T-05: Branded content cannot be private.
                    ->disableOptionWhen(fn (string $value, Get $get): bool => $value === 'SELF_ONLY' && (bool) $get('tt_disclose') && (bool) $get('tt_brand_content'))
                    ->helperText(fn (Get $get): ?string => $get('tt_disclose') && $get('tt_brand_content')
                        ? "Branded content can't be posted as Only me."
                        : null)
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($creator, $creatorError): void {
                            if ($creatorError() !== null || $creator() === null) {
                                $fail('Your TikTok account details could not be loaded. Reconnect TikTok and try again.');
                            } elseif (! ($creator()['can_post'] ?? true)) {
                                $fail('TikTok says this account cannot post right now.');
                            } elseif (! in_array($value, (array) ($creator()['privacy_level_options'] ?? []), true)) {
                                $fail('Choose one of the privacy options TikTok offers for your account.');
                            }
                        },
                    ]),
                // T-03: interaction toggles start off; disabled when the creator turned them off.
                ...collect(['comment' => 'Allow comments', 'duet' => 'Allow Duet', 'stitch' => 'Allow Stitch'])
                    ->map(fn (string $label, string $key) => Toggle::make("tt_allow_{$key}")
                        ->label($label)
                        ->default(false)
                        ->disabled(fn (): bool => (bool) ($creator()["{$key}_disabled"] ?? false))
                        ->helperText(fn (): ?string => ($creator()["{$key}_disabled"] ?? false)
                            ? 'Turned off in your TikTok privacy settings.'
                            : null))
                    ->values()
                    ->all(),
                // T-04
                Text::make(fn (): string => 'Videos can be up to '.(int) ($creator()['max_video_post_duration_sec'] ?? 0).' seconds long for this account.')
                    ->visible(fn (): bool => (int) ($creator()['max_video_post_duration_sec'] ?? 0) > 0),
                // T-05 / T-06
                Toggle::make('tt_disclose')
                    ->label('Disclose video content')
                    ->helperText('Turn on if this video promotes a brand, product or service.')
                    ->default(false)
                    ->live(),
                Checkbox::make('tt_brand_organic')
                    ->label('Your brand')
                    ->helperText(fn (Get $get): ?string => $get('tt_brand_organic') ? self::BRAND_ORGANIC_LABEL : 'You are promoting yourself or your own business.')
                    ->visible(fn (Get $get): bool => (bool) $get('tt_disclose'))
                    ->live()
                    ->accepted(fn (Get $get): bool => $selected($get) && (bool) $get('tt_disclose') && ! $get('tt_brand_content'))
                    ->validationMessages(['accepted' => 'Choose Your brand, Branded content, or both.']),
                Checkbox::make('tt_brand_content')
                    ->label('Branded content')
                    ->helperText(fn (Get $get): ?string => $get('tt_brand_content') ? self::BRAND_CONTENT_LABEL : 'You are promoting another brand or a third party.')
                    ->visible(fn (Get $get): bool => (bool) $get('tt_disclose'))
                    ->live()
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if ($value && $get('tt_disclose') && $get('tt_privacy_level') === 'SELF_ONLY') {
                                $fail("Branded content can't be posted as Only me. Choose another privacy option.");
                            }
                        },
                    ]),
                // T-10
                Toggle::make('tt_is_aigc')
                    ->label('AI-generated content')
                    ->default(false),
            ]);
    }

    /** COMPLIANCE P-01, T-07, P-06. */
    protected static function reviewFields(Closure $creator): array
    {
        return [
            // USER_SIM item 5 / TikTok CSG: preview the video before publishing.
            Text::make(fn (Get $get): HtmlString => self::videoPreview($get('video')))
                ->visible(fn (Get $get): bool => filled($get('video'))),
            Section::make('Check before publishing')
                ->schema([
                    Text::make(fn (Get $get): HtmlString => self::summary($get, $creator())),
                ]),
            Text::make(fn (Get $get): HtmlString => new HtmlString(
                'By posting, you agree to TikTok\'s <a href="'.self::TIKTOK_MUSIC_URL.'" target="_blank" rel="noopener" style="text-decoration: underline;">Music Usage Confirmation</a>'
                .($get('tt_disclose') && $get('tt_brand_content')
                    ? ' and <a href="'.self::TIKTOK_BRANDED_URL.'" target="_blank" rel="noopener" style="text-decoration: underline;">Branded Content Policy</a>'
                    : '')
                .'.'
            ))->visible(fn (Get $get): bool => in_array('tiktok', (array) $get('platforms'), true)),
            Callout::make(self::PROCESSING_NOTICE)->info(),
        ];
    }

    /** Platforms that have a brand mark in public/images/brands (U-05). TikTok only once the official asset is added. */
    protected static function logoUrl(string $platform): ?string
    {
        $file = "images/brands/{$platform}.svg";

        return is_file(public_path($file)) ? asset($file) : null;
    }

    /** U-05: option labels with the platform's brand mark (escaped name, unmodified asset). @return array<string, string> */
    public static function platformOptionsWithLogos(): array
    {
        $out = [];
        foreach (self::platformOptions() as $key => $label) {
            $logo = self::logoUrl((string) $key);
            $img = $logo ? '<img src="'.e($logo).'" alt="" width="20" height="20" style="display: inline-block; vertical-align: middle; margin-right: 0.4rem;">' : '';
            $out[$key] = $img.e($label);
        }

        return $out;
    }

    /** @return array{src: ?string, sizeMb: ?float, tiktokMax: int} */
    public static function videoCheckData(mixed $state): array
    {
        $file = is_array($state) ? reset($state) : $state;
        $src = null;
        $sizeMb = null;

        if ($file instanceof TemporaryUploadedFile) {
            try {
                $src = $file->temporaryUrl();
                $sizeMb = round(((int) $file->getSize()) / 1048576, 1);
            } catch (Throwable) {
                // preview/size unavailable: browser checks simply stay silent
            }
        }

        return ['src' => $src, 'sizeMb' => $sizeMb, 'tiktokMax' => 0];
    }

    /** @param  mixed  $state  the FileUpload state (temporary upload(s)) */
    protected static function videoPreview(mixed $state): HtmlString
    {
        $file = is_array($state) ? reset($state) : $state;

        try {
            $url = $file instanceof TemporaryUploadedFile ? $file->temporaryUrl() : null;
        } catch (Throwable) {
            $url = null;
        }

        if (! $url) {
            return new HtmlString('');
        }

        return new HtmlString('<video controls preload="metadata" src="'.e($url).'" style="max-height: 320px; max-width: 100%; border-radius: 0.5rem;"></video>');
    }

    /** The confirm-step summary: destinations, caption and per-platform settings. */
    protected static function summary(Get $get, ?array $creator): HtmlString
    {
        $platforms = (array) $get('platforms');
        $labels = self::platformOptions();
        $handles = self::handles();
        $e = fn (?string $v): string => e((string) $v);

        $rows = [];
        $rows[] = '<dt><strong>Title</strong></dt><dd>'.$e($get('title') ?: '—').'</dd>';
        $rows[] = '<dt><strong>Caption</strong></dt><dd>'.nl2br($e($get('caption') ?: '—')).'</dd>';
        $tags = array_map(fn ($t) => '#'.ltrim((string) $t, '#'), (array) $get('hashtags'));
        $rows[] = '<dt><strong>Hashtags</strong></dt><dd>'.$e($tags ? implode(' ', $tags) : '—').'</dd>';
        $rows[] = '<dt><strong>When</strong></dt><dd>'.$e($get('scheduled_at') ? 'Scheduled for '.$get('scheduled_at') : 'Publish now').'</dd>';

        if ($platforms === []) {
            $rows[] = '<dt><strong>Destinations</strong></dt><dd>None selected</dd>';
        }

        foreach ($platforms as $platform) {
            $settings = [];
            if ($platform === 'youtube') {
                $settings[] = 'Privacy: '.(self::YOUTUBE_PRIVACY[$get('yt_privacy') ?: (config('clippipeline.youtube_audited') ? '' : 'private')] ?? '—');
                $settings[] = $get('yt_guidelines') ? 'Community Guidelines confirmed' : 'Community Guidelines not confirmed';
            }
            if ($platform === 'tiktok') {
                $settings[] = 'Privacy: '.(self::TIKTOK_PRIVACY_LABELS[$get('tt_privacy_level')] ?? 'not chosen');
                $settings[] = 'Comments '.($get('tt_allow_comment') ? 'on' : 'off');
                $settings[] = 'Duet '.($get('tt_allow_duet') ? 'on' : 'off');
                $settings[] = 'Stitch '.($get('tt_allow_stitch') ? 'on' : 'off');
                if ($get('tt_disclose')) {
                    $settings[] = 'Disclosed: '.implode(' + ', array_filter([
                        $get('tt_brand_organic') ? 'Your brand' : null,
                        $get('tt_brand_content') ? 'Branded content' : null,
                    ]) ?: ['nothing chosen']);
                }
                if ($get('tt_is_aigc')) {
                    $settings[] = 'AI-generated';
                }
            }

            $handle = $platform === 'tiktok' && $creator
                ? '@'.(($creator['creator_username'] ?? '') ?: ($creator['creator_nickname'] ?? ''))
                : ($handles[$platform] ?? null);

            $rows[] = '<dt><strong>'.$e($labels[$platform] ?? ucfirst((string) $platform)).'</strong></dt><dd>'
                .($handle ? $e($handle).' · ' : '')
                .$e($settings ? implode(' · ', $settings) : 'Default settings').'</dd>';
        }

        return new HtmlString('<dl style="display: grid; grid-template-columns: max-content 1fr; gap: 0.35rem 1rem;">'.implode('', $rows).'</dl>');
    }

    /**
     * Saved platform_options per platform, in the POST /api/posts shape (§12).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    public static function platformOptionsFromData(array $data): array
    {
        $platforms = (array) ($data['platforms'] ?? []);
        $options = [];

        if (in_array('youtube', $platforms, true)) {
            $options['youtube'] = [
                'privacy_status' => (string) ($data['yt_privacy'] ?? 'private'),
                'guidelines_confirmed' => ! empty($data['yt_guidelines']),
            ];
        }

        if (in_array('tiktok', $platforms, true)) {
            $disclose = ! empty($data['tt_disclose']);
            $options['tiktok'] = [
                'privacy_level' => $data['tt_privacy_level'] ?? null,
                'disable_comment' => empty($data['tt_allow_comment']),
                'disable_duet' => empty($data['tt_allow_duet']),
                'disable_stitch' => empty($data['tt_allow_stitch']),
                'brand_organic_toggle' => $disclose && ! empty($data['tt_brand_organic']),
                'brand_content_toggle' => $disclose && ! empty($data['tt_brand_content']),
                'is_aigc' => ! empty($data['tt_is_aigc']),
                // T-07: the consent line is shown on the confirm step above Publish.
                'music_consent' => true,
            ];
        }

        return $options;
    }

    /**
     * Only the user's active accounts (the set POST /api/posts accepts).
     *
     * @return array<string, string>
     */
    public static function platformOptions(): array
    {
        $options = [];
        foreach (app(CrossPostService::class)->optionsForUser((int) auth()->id()) as $opt) {
            if ($opt['publishable']) {
                $options[$opt['provider']] = $opt['label'];
            }
        }

        return $options;
    }

    /** @return array<string, string> platform => handle of the user's active accounts */
    protected static function handles(): array
    {
        return SocialAccount::query()
            ->where('user_id', auth()->id())
            ->get()
            ->mapWithKeys(fn (SocialAccount $a) => [$a->provider => (string) ($a->handle ?: $a->display_name)])
            ->filter()
            ->all();
    }

    /** A saved preference (PreferencesService, same as GET /api/me/preferences); [] when unavailable. */
    protected static function preference(string $key): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        try {
            return array_values((array) (app(PreferencesService::class)->get($user)[$key] ?? []));
        } catch (Throwable) {
            return [];
        }
    }
}
