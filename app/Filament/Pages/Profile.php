<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\User;
use App\Services\Account\AccountDeletionService;
use App\Services\Account\MeService;
use App\Services\Account\PreferencesService;
use App\Services\Analytics\ExportService;
use App\Services\AnalyticsService;
use App\Services\PostUploadService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profile (PARITY R2, R4–R10): name, preferences, plan/usage, policy links,
 * sign out and account deletion. Uses the same services as /api/me*.
 */
class Profile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Profile';

    protected static ?string $title = 'Profile';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.profile';

    public const DELETE_EXPLANATION = 'This permanently deletes your ClipPipeline account: connected accounts (access is revoked at YouTube, Meta and TikTok), posts, stored videos, analytics, notifications and devices. Videos already published on the platforms stay there. This cannot be undone.';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $me = $this->me();

        $this->form->fill([
            'name' => $me['user']['name'],
            ...$me['preferences'],
        ]);
    }

    /** The exact array GET /api/me returns for this user. */
    public function me(): array
    {
        return app(MeService::class)->present($this->user());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Account')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->minLength(1)
                            ->maxLength(100),
                        TextInput::make('email')
                            ->default(fn (): string => (string) $this->user()->email)
                            ->formatStateUsing(fn (): string => (string) $this->user()->email)
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
                Section::make('Preferences')
                    ->schema([
                        Toggle::make('notifications_enabled')
                            ->label('Push notifications')
                            ->helperText('Published, failed and reconnect alerts on your phone. They always appear in the bell here.'),
                        Toggle::make('auto_sync')
                            ->label('Auto-sync analytics')
                            ->helperText('Refresh views, likes and followers from your connected accounts every few hours.'),
                        CheckboxList::make('default_platforms')
                            ->label('Default platforms for new posts')
                            ->options(PostsTable::PLATFORM_OPTIONS)
                            ->columns(2),
                        TagsInput::make('default_hashtags')
                            ->label('Default hashtags')
                            ->placeholder('Add a hashtag')
                            ->splitKeys([' ', ',']),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = $this->user();

        try {
            DB::transaction(function () use ($user, $data): void {
                $user->forceFill(['name' => trim((string) $data['name'])])->save();

                app(PreferencesService::class)->update($user, [
                    'notifications_enabled' => (bool) ($data['notifications_enabled'] ?? false),
                    'auto_sync' => (bool) ($data['auto_sync'] ?? false),
                    'default_platforms' => array_values((array) ($data['default_platforms'] ?? [])),
                    'default_hashtags' => PostUploadService::normalizeHashtags($data['default_hashtags'] ?? []),
                ]);
            });
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn (array $m, string $k) => ['data.'.preg_replace('/\.\d+$/', '', $k) => $m])->all(),
            );
        }

        $this->mount();

        Notification::make()->title('Profile saved')->success()->send();
    }

    /** PARITY R7: same CSV as Insights / GET /api/analytics/export. */
    public function exportCsvAction(): Action
    {
        return Action::make('exportCsv')
            ->label('Download analytics CSV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->schema([
                Select::make('period')
                    ->options(Dashboard::PERIOD_OPTIONS)
                    ->default('7d')
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->action(function (array $data, ExportService $export): StreamedResponse {
                $period = array_key_exists($data['period'] ?? '', AnalyticsService::PERIODS) ? $data['period'] : '7d';
                $csv = $export->csv($this->user(), $period);

                return response()->streamDownload(function () use ($csv): void {
                    echo $csv;
                }, ExportService::filename($period), ['Content-Type' => 'text/csv']);
            });
    }

    public function deleteAccountAction(): Action
    {
        return Action::make('deleteAccount')
            ->label('Delete account')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading('Delete your ClipPipeline account?')
            ->modalDescription(self::DELETE_EXPLANATION)
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalSubmitActionLabel('Delete my account')
            ->schema([
                TextInput::make('confirm')
                    ->label('Type DELETE to confirm')
                    ->required()
                    ->in(['DELETE'])
                    ->validationMessages(['in' => 'Type DELETE in capital letters to confirm.']),
            ])
            ->action(function (AccountDeletionService $deletion): void {
                $deletion->delete($this->user());

                Filament::auth()->logout();
                session()->invalidate();
                session()->regenerateToken();

                $this->redirect(url('/delete-account?status=deleted'));
            });
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
