# Graph Report - clippipeline.com  (2026-10-09)

## Corpus Check
- 108 files · ~76,999 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 5614 nodes · 18391 edges · 151 communities (113 shown, 12 thin omitted)
- Extraction: 88% EXTRACTED · 12% INFERRED · 0% AMBIGUOUS · INFERRED: 2258 edges (avg confidence: 0.85)
- Token cost: 50,000 input · 4,000 output

## Community Hubs (Navigation)
- CodeMirror Editor Core
- ProseMirror Rich Editor
- Chart.js Core
- Chart.js Layout Engine
- Stats Chart Rendering
- Editor & Chart Utilities
- Editor Commands & Shortcuts
- Rich Editor Node Views
- Code Editor Indentation
- Chart Tick & Grid Layout
- Markdown Editor Core
- Editor Extensions Manager
- Slider Component
- ProseMirror Transactions
- Lezer Code Parser
- Chart Scale Lifecycle
- Chart Element Lifecycle
- Chart Tick Computation
- Editor Coordinate Mapping
- Chart Date Formatting
- Syntax Tree Cursor
- Markdown Parser
- ProseMirror DOM Serialization
- Filament Support Core
- Chart Data Limits
- Editor Color & Misc
- Table Select Column
- Laravel Echo WebSockets
- Editor Composition Widgets
- Filament Support Lifecycle
- Chart Color Utilities
- Rich Editor Marks & Rules
- Luxon DateTime
- Markdown Autosave
- Filament Notifications
- Filament Tables
- Rich Editor Helpers
- Chart Event Binding
- Chart Bar Rendering
- ProseMirror Step Mapping
- Editor Change Sets
- Editor Shared Utilities
- Laravel Domain Models
- Filament Support Utilities
- Editor Range Decorations
- Table Select Column 2
- Chart Rendering 2
- Billing & Social Auth Controllers
- TikTok Scraper & Polling
- Code Editor Misc
- Editor & Echo Bundle
- Filament Support 2
- Code Editor 3
- Code Editor 4
- Select Component
- Stats Chart 2
- Select Component 2
- Select Component 3
- Code Editor 5
- Table Select & Tables
- Core DB Migrations
- File Upload & Rich Editor
- Filament App Bundle
- Chart Misc
- Chart & Stats
- Filament Pages (Insights/Connect)
- Admin Panel Provider
- NPM Package Manifest
- Select Component 4
- Filament Support 3
- Chart Misc 2
- Filament Tables 2
- Project README / Overview
- Code Editor 6
- Select Component 5
- Chart Misc 3
- Top Posts Widgets
- Composer Dependencies
- Color Picker Component
- File Upload Component
- Database Seeders
- Date-Time Picker Component
- Controllers 2
- Composer Dependencies 2
- Filament Support 4
- Performance Chart Widgets
- YouTube Publishing Jobs
- Markdown Editor 2
- Select Component 6
- Filament Schemas
- Analytics Overview Widgets
- Composer Dependencies 3
- Composer Dependencies 4
- Composer Dependencies 5
- Composer Dependencies 6
- File Upload 2
- File Upload 3
- Console Kernel (Scheduler)
- Feature Tests
- Filament Actions
- File Upload & Code Editor
- File Upload & Slider
- File Upload 4
- Schema Actions
- Table Select 3
- App Service Provider
- App Bootstrap / Entry
- Composer Dependencies 7
- Logging Config
- File Upload 5
- Filament Support 5
- Chart Misc 4
- Event Service Provider
- Composer Dependencies 8
- Composer Dependencies 9
- Unit Tests
- File Upload 6
- Hero Slider (Landing)
- Composer Dependencies 10
- TikTok Insights View
- Console Routes
- App Layout View
- Base Controller
- robots.txt
- Empty Test Fixture

## God Nodes (most connected - your core abstractions)
1. `constructor()` - 142 edges
2. `update()` - 138 edges
3. `resolve()` - 94 edges
4. `y()` - 88 edges
5. `_update()` - 87 edges
6. `_update()` - 86 edges
7. `te()` - 77 edges
8. `node()` - 77 edges
9. `constructor()` - 74 edges
10. `o()` - 73 edges

## Surprising Connections (you probably didn't know these)
- `kS()` --indirect_call--> `Qp()`  [INFERRED]
  public/js/filament/forms/components/code-editor.js → public/js/filament/forms/components/file-upload.js
- `X0()` --indirect_call--> `la()`  [INFERRED]
  public/js/filament/forms/components/rich-editor.js → public/js/filament/forms/components/file-upload.js
- `te()` --indirect_call--> `Pr()`  [INFERRED]
  public/js/filament/forms/components/markdown-editor.js → public/js/filament/filament/echo.js
- `constructor()` --indirect_call--> `co()`  [INFERRED]
  public/js/filament/forms/components/code-editor.js → public/js/filament/forms/components/markdown-editor.js
- `constructor()` --indirect_call--> `pa()`  [INFERRED]
  public/js/filament/forms/components/code-editor.js → public/js/filament/forms/components/markdown-editor.js

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **TikTok to YouTube/Instagram Cross-Posting Flow** — readme_tiktok_scraper, readme_youtube_integration, readme_instagram_integration, readme_clippipeline_project [EXTRACTED 1.00]
- **OAuth Account Connection Subsystem** — readme_integrations_ui, readme_connect_accounts_page, readme_social_auth_controller, readme_social_accounts_table [EXTRACTED 1.00]
- **Dashboard Analytics Subsystem** — readme_dashboard_dummy_data_seeder, readme_performance_chart_widget, readme_views_trend_chart [INFERRED 0.85]

## Communities (151 total, 12 thin omitted)

### Community 0 - "CodeMirror Editor Core"
Cohesion: 0.01
Nodes (111): ac(), ak(), AP(), atLastNode(), attrs(), [b.Blockquote](), [b.ListItem](), baseTheme() (+103 more)

### Community 1 - "ProseMirror Rich Editor"
Cohesion: 0.01
Nodes (167): [g](), vl(), accepts(), addAttributes(), addHackNode(), addNodeMark(), addTextblockHacks(), an() (+159 more)

### Community 2 - "Chart.js Core"
Cohesion: 0.01
Nodes (119): $a(), aa(), acquireContext(), addControllers(), addPlugins(), addScales(), ag(), ah() (+111 more)

### Community 3 - "Chart.js Layout Engine"
Cohesion: 0.02
Nodes (233): ad(), add(), addBox(), addEventListener(), af(), apply(), applyStack(), ar() (+225 more)

### Community 4 - "Stats Chart Rendering"
Cohesion: 0.02
Nodes (169): Bf(), Ia(), _a(), aa(), acquireContext(), active(), add(), addControllers() (+161 more)

### Community 5 - "Editor & Chart Utilities"
Cohesion: 0.02
Nodes (174): accept(), active(), add(), addChunk(), addEventListener(), addInfoPane(), addInner(), addWindowListeners() (+166 more)

### Community 6 - "Editor Commands & Shortcuts"
Cohesion: 0.05
Nodes (145): Xf(), $a(), ac(), Ad(), addCommands(), addKeyboardShortcuts(), after(), allowedMarks() (+137 more)

### Community 7 - "Rich Editor Node Views"
Cohesion: 0.04
Nodes (137): addNodeView(), ag(), al(), B(), bd(), bg(), bp(), cd() (+129 more)

### Community 8 - "Code Editor Indentation"
Cohesion: 0.04
Nodes (135): aa(), ad(), addActive(), addElement(), Ag(), an(), aS(), balance() (+127 more)

### Community 9 - "Chart Tick & Grid Layout"
Cohesion: 0.03
Nodes (124): We(), adjustHitBoxes(), At(), bh(), bi(), buildTicks(), calculateLabelRotation(), _calculatePadding() (+116 more)

### Community 10 - "Markdown Editor Core"
Cohesion: 0.04
Nodes (96): ad(), af(), ai(), ao(), bf(), Bt(), Cc(), Cn() (+88 more)

### Community 11 - "Editor Extensions Manager"
Cohesion: 0.03
Nodes (102): zu(), add(), addExtensions(), addNode(), applyInitialSize(), bt(), cg(), configure() (+94 more)

### Community 12 - "Slider Component"
Cohesion: 0.05
Nodes (89): Ae(), ar(), Be(), Bt(), Ce(), De(), _e(), Ee() (+81 more)

### Community 13 - "ProseMirror Transactions"
Cohesion: 0.05
Nodes (90): VariableDefinition(), addProseMirrorPlugins(), apply(), applyInner(), applyTransaction(), Ba(), bx(), cellsInRect() (+82 more)

### Community 14 - "Lezer Code Parser"
Cohesion: 0.03
Nodes (90): _0(), acceptToken(), addActions(), advance(), advanceFully(), advanceStack(), allActions(), allows() (+82 more)

### Community 15 - "Chart Scale Lifecycle"
Cohesion: 0.04
Nodes (90): addElements(), ae(), afterBuildTicks(), afterCalculateLabelRotation(), afterDataLimits(), afterFit(), afterSetDimensions(), afterTickToLabelConversion() (+82 more)

### Community 16 - "Chart Element Lifecycle"
Cohesion: 0.04
Nodes (89): addElements(), afterBuildTicks(), afterCalculateLabelRotation(), afterDataLimits(), afterDatasetsUpdate(), afterDraw(), afterFit(), afterSetDimensions() (+81 more)

### Community 17 - "Chart Tick Computation"
Cohesion: 0.05
Nodes (89): adjustHitBoxes(), Ao(), bh(), bu(), buildTicks(), calculateLabelRotation(), _calculatePadding(), clear() (+81 more)

### Community 18 - "Editor Coordinate Mapping"
Cohesion: 0.04
Nodes (87): addRange(), Bi(), bidiSpans(), checkHover(), clear(), cn(), coordsAt(), coordsAtPos() (+79 more)

### Community 19 - "Chart Date Formatting"
Cohesion: 0.04
Nodes (87): abutsStart(), ae(), after(), Al(), Am(), before(), buildFormatParser(), cc() (+79 more)

### Community 20 - "Syntax Tree Cursor"
Cohesion: 0.05
Nodes (84): ah(), c$(), child(), childAfter(), childBefore(), continue(), cursor(), cursorAt() (+76 more)

### Community 21 - "Markdown Parser"
Cohesion: 0.08
Nodes (84): _a(), Ae(), ar(), as(), Ba(), bc(), bo(), ue() (+76 more)

### Community 22 - "ProseMirror DOM Serialization"
Cohesion: 0.04
Nodes (75): ab(), addAll(), addDOM(), addElement(), addElementByRule(), addTextNode(), addToSet(), allowsMarks() (+67 more)

### Community 23 - "Filament Support Core"
Cohesion: 0.06
Nodes (59): _a(), aa(), ba(), br(), T(), Bt(), ca(), close() (+51 more)

### Community 24 - "Chart Data Limits"
Cohesion: 0.04
Nodes (74): Ct(), Qa(), an(), Ar(), average(), cc(), dataset(), determineDataLimits() (+66 more)

### Community 25 - "Editor Color & Misc"
Cohesion: 0.09
Nodes (71): Cg(), jg(), Se(), [x](), al(), An(), at(), Be() (+63 more)

### Community 26 - "Table Select Column"
Cohesion: 0.09
Nodes (56): ai(), b(), bn(), ci(), Cn(), ct(), D(), di() (+48 more)

### Community 27 - "Laravel Echo WebSockets"
Cohesion: 0.05
Nodes (48): a(), ar(), b(), Be(), Ce(), cr(), d(), De() (+40 more)

### Community 28 - "Editor Composition Widgets"
Cohesion: 0.04
Nodes (58): AQ(), b1(), cO(), combine(), compositionend(), cX(), defineModifier(), DQ() (+50 more)

### Community 29 - "Filament Support Lifecycle"
Cohesion: 0.12
Nodes (54): ai(), c(), d(), destroy(), Dn(), ei(), f(), a() (+46 more)

### Community 30 - "Chart Color Utilities"
Cohesion: 0.05
Nodes (57): alpha(), Be(), bo(), $c(), Cm(), co(), color(), darken() (+49 more)

### Community 31 - "Rich Editor Marks & Rules"
Cohesion: 0.06
Nodes (56): addInputRules(), addMark(), addPasteRules(), addStoredMark(), Ao(), aw(), bc(), cw() (+48 more)

### Community 32 - "Luxon DateTime"
Cohesion: 0.05
Nodes (56): Cl(), clone(), create(), Dl(), dtFormatter(), Ec(), eras(), extract() (+48 more)

### Community 33 - "Markdown Autosave"
Cohesion: 0.05
Nodes (10): Rd(), Bi(), Bn(), br(), ji(), qd(), Ri(), te() (+2 more)

### Community 34 - "Filament Notifications"
Cohesion: 0.06
Nodes (31): actions(), button(), c(), close(), configureAnimations(), configureTransitions(), constructor(), danger() (+23 more)

### Community 35 - "Filament Tables"
Cohesion: 0.12
Nodes (48): ae(), be(), C(), Ce(), D(), De(), E(), ee() (+40 more)

### Community 36 - "Rich Editor Helpers"
Cohesion: 0.09
Nodes (50): Ah(), Bh(), Bi(), ch(), ct(), dh(), Eh(), fh() (+42 more)

### Community 37 - "Chart Event Binding"
Cohesion: 0.06
Nodes (50): Eo(), addEventListener(), al(), bindEvents(), bindResponsiveEvents(), bindUserEvents(), bs(), Bt() (+42 more)

### Community 38 - "Chart Bar Rendering"
Cohesion: 0.07
Nodes (50): hh(), ac(), applyStack(), buildOrUpdateScales(), _calculateBarIndexPixels(), _calculateBarValuePixels(), ch(), countVisibleElements() (+42 more)

### Community 39 - "ProseMirror Step Mapping"
Cohesion: 0.06
Nodes (47): a0(), addInner(), addMaps(), addStep(), addTransform(), ak(), appendMap(), appendMapping() (+39 more)

### Community 40 - "Editor Change Sets"
Cohesion: 0.06
Nodes (47): addChanges(), addSelection(), after(), before(), BO(), Ch(), changeByRange(), changes() (+39 more)

### Community 41 - "Editor Shared Utilities"
Cohesion: 0.22
Nodes (41): G(), b(), $c(), X(), D(), _e(), f(), se() (+33 more)

### Community 42 - "Laravel Domain Models"
Cohesion: 0.08
Nodes (16): Post, PostDistribution, SocialAccount, PostAnalyticFactory, PostFactory, SocialAccountFactory, UserFactory, Illuminate\Database\Eloquent\Attributes\Fillable (+8 more)

### Community 43 - "Filament Support Utilities"
Cohesion: 0.14
Nodes (41): Cn(), b(), Ce(), De(), dn(), _e(), F(), Fe() (+33 more)

### Community 44 - "Editor Range Decorations"
Cohesion: 0.07
Nodes (43): activeForPoint(), Ar(), at(), be(), boundChange(), commit(), comparePoint(), compareRange() (+35 more)

### Community 45 - "Table Select Column 2"
Cohesion: 0.11
Nodes (43): addBadgesForSelectedOptions(), addSingleBadge(), addSingleSelectionDisplay(), applyDisabledState(), closeDropdown(), constructor(), createBadgeElement(), createOptionElement() (+35 more)

### Community 46 - "Chart Rendering 2"
Cohesion: 0.06
Nodes (42): ac(), afterAutoSkip(), bl(), buildLookupTable(), dn(), Fa(), fc(), getDecimalForPixel() (+34 more)

### Community 47 - "Billing & Social Auth Controllers"
Cohesion: 0.12
Nodes (8): BillingController, SocialAuthController, User, Illuminate\Foundation\Auth\User, Illuminate\Http\JsonResponse, Illuminate\Http\RedirectResponse, Illuminate\Http\Request, Illuminate\Support\Facades\Route

### Community 48 - "TikTok Scraper & Polling"
Cohesion: 0.11
Nodes (17): PollTikTokFeed, PollTikTokLatest, TestSocialUploads, DownloadTikTokVideoJob, PublishToInstagramReelsJob, PublishToYouTubeShortsJob, InstagramService, TikTokScraperService (+9 more)

### Community 49 - "Code Editor Misc"
Cohesion: 0.08
Nodes (40): addBlock(), addBlockWidget(), addBreak(), addComposition(), addDelimiter(), addInlineWidget(), addLine(), addLineDeco() (+32 more)

### Community 50 - "Editor & Echo Bundle"
Cohesion: 0.10
Nodes (39): Pr(), Lr(), Ac(), bl(), ca(), ee(), ce(), cl() (+31 more)

### Community 51 - "Filament Support 2"
Cohesion: 0.12
Nodes (38): ae(), B(), Be(), He(), Jt(), Me(), Pe(), wt() (+30 more)

### Community 52 - "Code Editor 3"
Cohesion: 0.07
Nodes (37): addCompletion(), addCompletions(), addNamespace(), addNamespaceObject(), Ao(), applyChanges(), AX(), balanced() (+29 more)

### Community 53 - "Code Editor 4"
Cohesion: 0.08
Nodes (36): Bg(), charCategorizer(), cs(), di(), Ed(), f1(), fe(), getChild() (+28 more)

### Community 54 - "Select Component"
Cohesion: 0.09
Nodes (26): applyDisabledState(), be(), disable(), en(), enable(), gt(), In(), jn() (+18 more)

### Community 55 - "Stats Chart 2"
Cohesion: 0.08
Nodes (36): afterDatasetsUpdate(), Ao(), beforeDatasetDraw(), beforeDatasetsDraw(), beforeDraw(), co(), _drawDatasets(), generateLabels() (+28 more)

### Community 56 - "Select Component 2"
Cohesion: 0.16
Nodes (31): addSingleSelectionDisplay(), closeDropdown(), constructor(), createOptionElement(), deferPositionDropdown(), destroy(), filterOptions(), focusNextOption() (+23 more)

### Community 57 - "Select Component 3"
Cohesion: 0.16
Nodes (31): ai(), bi(), bn(), ci(), di(), Dt(), Et(), gi() (+23 more)

### Community 58 - "Code Editor 5"
Cohesion: 0.09
Nodes (30): addChild(), addGaps(), addLeafElement(), addNode(), ATXHeading(), char(), complete(), elt() (+22 more)

### Community 59 - "Table Select & Tables"
Cohesion: 0.11
Nodes (30): Ae(), bi(), Bt(), Ce(), De(), ei(), en(), fn() (+22 more)

### Community 60 - "Core DB Migrations"
Cohesion: 0.11
Nodes (3): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema

### Community 61 - "File Upload & Rich Editor"
Cohesion: 0.08
Nodes (11): dm(), la(), lm(), om(), qe(), Qp(), yt(), jc() (+3 more)

### Community 62 - "Filament App Bundle"
Cohesion: 0.13
Nodes (19): B(), close(), E(), F(), G(), I(), L(), N() (+11 more)

### Community 63 - "Chart Misc"
Cohesion: 0.09
Nodes (25): average(), bm(), fromHTTP(), fromSQL(), getCenterPoint(), Gr(), hasValue(), hs() (+17 more)

### Community 64 - "Chart & Stats"
Cohesion: 0.08
Nodes (24): Bc(), bg(), chartOptionScopes(), constructor(), data(), el(), features(), fg() (+16 more)

### Community 65 - "Filament Pages (Insights/Connect)"
Cohesion: 0.17
Nodes (7): ConnectAccounts, BackedEnum, TiktokInsights, PostAnalytic, BackedEnum, Filament\Pages\Page, Illuminate\Support\Carbon

### Community 66 - "Admin Panel Provider"
Cohesion: 0.11
Nodes (18): AdminPanelProvider, Filament\Http\Middleware\Authenticate, Filament\Http\Middleware\AuthenticateSession, Filament\Http\Middleware\DisableBladeIconComponents, Filament\Http\Middleware\DispatchServingFilamentEvent, Filament\Pages\Dashboard, Filament\Panel, Filament\PanelProvider (+10 more)

### Community 67 - "NPM Package Manifest"
Cohesion: 0.11
Nodes (17): concurrently, laravel-vite-plugin, devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite (+9 more)

### Community 68 - "Select Component 4"
Cohesion: 0.23
Nodes (18): Ae(), Bt(), Ce(), ct(), De(), ei(), fn(), ht() (+10 more)

### Community 69 - "Filament Support 3"
Cohesion: 0.14
Nodes (18): bo(), es(), hs(), is(), kn(), Mo(), ns(), Oe() (+10 more)

### Community 70 - "Chart Misc 2"
Cohesion: 0.14
Nodes (18): active(), _animateOptions(), cancel(), _createAnimations(), _createDescriptors(), _descriptors(), ensureScalesHaveIDs(), _notify() (+10 more)

### Community 71 - "Filament Tables 2"
Cohesion: 0.21
Nodes (17): areRecordsSelected(), areRecordsToggleable(), canSelectAllRecords(), deselectAllRecords(), deselectRecords(), getRecordsOnPage(), getSelectedRecordsCount(), handleCheckboxClick() (+9 more)

### Community 72 - "Project README / Overview"
Cohesion: 0.15
Nodes (17): ClipPipeline Cross-Posting Pipeline, Filament ConnectAccounts Page, DashboardDummyDataSeeder, Instagram (Facebook/Meta) Integration, Integrations UI, Laravel Framework, OAuth Token Storage Flow, PerformanceChartWidget (+9 more)

### Community 73 - "Code Editor 6"
Cohesion: 0.17
Nodes (16): addToSet(), bd(), childString(), clearDelayedAndroidKey(), delayAndroidKey(), flushIOSKey(), forceFlush(), ii() (+8 more)

### Community 74 - "Select Component 5"
Cohesion: 0.18
Nodes (16): b(), Cn(), D(), Dn(), _e(), ft(), he(), K() (+8 more)

### Community 75 - "Chart Misc 3"
Cohesion: 0.15
Nodes (15): beforeDatasetsDraw(), beforeDraw(), dataset(), generateLabels(), getDataVisibility(), _getSortedDatasetMetas(), getSortedVisibleDatasetMetas(), getVisibleDatasetCount() (+7 more)

### Community 76 - "Top Posts Widgets"
Cohesion: 0.19
Nodes (9): PopularPostsWidget, TopPostsTable, Filament\Tables, Filament\Tables\Columns\BadgeColumn, Filament\Tables\Columns\TextColumn, Filament\Tables\Table, Filament\Widgets\TableWidget, Illuminate\Database\Eloquent\Builder (+1 more)

### Community 77 - "Composer Dependencies"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+5 more)

### Community 79 - "File Upload Component"
Cohesion: 0.23
Nodes (13): am(), be(), cm(), De(), oe(), pe(), Rt(), sm() (+5 more)

### Community 80 - "Database Seeders"
Cohesion: 0.23
Nodes (8): Carbon, Carbon\Carbon, Carbon\CarbonPeriod, DashboardDummyDataSeeder, random_float(), DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 81 - "Date-Time Picker Component"
Cohesion: 0.29
Nodes (7): d(), e(), i(), m(), r(), s(), t()

### Community 82 - "Controllers 2"
Cohesion: 0.20
Nodes (6): Filament\Facades\Filament, Illuminate\Support\Facades\Auth, Illuminate\Support\Facades\Hash, Illuminate\Support\Str, Laravel\Socialite\Facades\Socialite, Pdo\Mysql

### Community 83 - "Composer Dependencies 2"
Cohesion: 0.18
Nodes (11): scripts, dev, post-update-cmd, pre-package-uninstall, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::prePackageUninstall, npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others (+3 more)

### Community 84 - "Filament Support 4"
Cohesion: 0.24
Nodes (11): apply(), as(), At(), it(), Ka(), Mt(), _o(), rr() (+3 more)

### Community 85 - "Performance Chart Widgets"
Cohesion: 0.24
Nodes (3): PerformanceChartWidget, ViewsTrendChart, Filament\Widgets\ChartWidget

### Community 86 - "YouTube Publishing Jobs"
Cohesion: 0.31
Nodes (6): YouTubeService, Google_Client, Google_Service_YouTube, Google_Service_YouTube_Video, Google_Service_YouTube_VideoSnippet, Google_Service_YouTube_VideoStatus

### Community 87 - "Markdown Editor 2"
Cohesion: 0.27
Nodes (10): Aa(), cf(), Jc(), Ln(), ma(), qa(), qc(), uo() (+2 more)

### Community 88 - "Select Component 6"
Cohesion: 0.33
Nodes (9): addBadgesForSelectedOptions(), addSingleBadge(), createBadgeElement(), createRemoveButton(), getLabelForSingleSelection(), getLabelsForMultipleSelection(), getSelectedOptionLabel(), getSelectedOptionLabels() (+1 more)

### Community 90 - "Analytics Overview Widgets"
Cohesion: 0.32
Nodes (4): AnalyticsOverview, StatsOverviewWidget, Filament\Widgets\StatsOverviewWidget, Filament\Widgets\StatsOverviewWidget\Stat

### Community 91 - "Composer Dependencies 3"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 92 - "Composer Dependencies 4"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install --ignore-scripts, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 93 - "Composer Dependencies 5"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 94 - "Composer Dependencies 6"
Cohesion: 0.29
Nodes (7): require, filament/filament, laravel/framework, laravel/socialite, laravel/tinker, php, socialiteproviders/tiktok

### Community 95 - "File Upload 2"
Cohesion: 0.29
Nodes (7): Ap(), bi(), Fp(), im(), ol(), xl(), yl()

### Community 96 - "File Upload 3"
Cohesion: 0.33
Nodes (7): Bp(), Cp(), Dp(), Op(), rl(), sa(), zp()

### Community 97 - "Console Kernel (Scheduler)"
Cohesion: 0.40
Nodes (3): Kernel, Illuminate\Console\Scheduling\Schedule, Illuminate\Foundation\Console\Kernel

### Community 98 - "Feature Tests"
Cohesion: 0.40
Nodes (3): Illuminate\Foundation\Testing\TestCase, ExampleTest, TestCase

### Community 99 - "Filament Actions"
Cohesion: 0.73
Nodes (5): closeModal(), generateModalId(), init(), openModal(), syncActionModals()

### Community 100 - "File Upload & Code Editor"
Cohesion: 0.33
Nodes (6): Fl(), S$(), _e(), Fe(), It(), Pp()

### Community 101 - "File Upload & Slider"
Cohesion: 0.33
Nodes (6): constructor(), define(), _getTestState(), getType(), registerListeners(), St()

### Community 102 - "File Upload 4"
Cohesion: 0.47
Nodes (6): em(), ha(), Ia(), ot(), ra(), Wt()

### Community 104 - "Table Select 3"
Cohesion: 0.40
Nodes (6): be(), gt(), jn(), O(), v(), x()

### Community 106 - "App Bootstrap / Entry"
Cohesion: 0.40
Nodes (3): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware

### Community 107 - "Composer Dependencies 7"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 108 - "Logging Config"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 109 - "File Upload 5"
Cohesion: 0.60
Nodes (5): clickPercent(), getPosition(), mouseUp(), movePlayhead(), timelineClicked()

### Community 110 - "Filament Support 5"
Cohesion: 0.50
Nodes (5): ar(), q(), Ft(), Nn(), Yt()

### Community 111 - "Chart Misc 4"
Cohesion: 0.40
Nodes (5): calculateCircumference(), _circumference(), _getCircumference(), _getRotation(), _getRotationExtents()

### Community 112 - "Event Service Provider"
Cohesion: 0.50
Nodes (3): EventServiceProvider, Illuminate\Foundation\Support\Providers\EventServiceProvider, SocialiteProviders\Manager\SocialiteWasCalled

### Community 113 - "Composer Dependencies 8"
Cohesion: 0.50
Nodes (4): post-autoload-dump, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, @php artisan filament:upgrade, @php artisan package:discover --ansi

### Community 114 - "Composer Dependencies 9"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 116 - "File Upload 6"
Cohesion: 0.67
Nodes (4): c(), o(), p(), s()

### Community 118 - "Composer Dependencies 10"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **67 isolated node(s):** `Controller`, `$schema`, `name`, `type`, `description` (+62 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 818 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **12 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Yi()` connect `Filament Support Core` to `Table Select Column`, `Select Component`?**
  _High betweenness centrality (0.071) - this node is a cross-community bridge._
- **Why does `io()` connect `Editor & Echo Bundle` to `Stats Chart Rendering`, `Editor Shared Utilities`, `Markdown Editor Core`, `Markdown Parser`, `Editor Color & Misc`?**
  _High betweenness centrality (0.050) - this node is a cross-community bridge._
- **Why does `As()` connect `Chart.js Layout Engine` to `ProseMirror Rich Editor`, `Chart.js Core`, `Chart Event Binding`, `Chart Rendering 2`, `Chart Element Lifecycle`?**
  _High betweenness centrality (0.043) - this node is a cross-community bridge._
- **Are the 11 inferred relationships involving `constructor()` (e.g. with `a()` and `h()`) actually correct?**
  _`constructor()` has 11 INFERRED edges - model-reasoned connections that need verification._
- **Are the 18 inferred relationships involving `update()` (e.g. with `a()` and `ia()`) actually correct?**
  _`update()` has 18 INFERRED edges - model-reasoned connections that need verification._
- **Are the 2 inferred relationships involving `resolve()` (e.g. with `s()` and `i()`) actually correct?**
  _`resolve()` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Are the 14 inferred relationships involving `y()` (e.g. with `$c()` and `D()`) actually correct?**
  _`y()` has 14 INFERRED edges - model-reasoned connections that need verification._