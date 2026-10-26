<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Modules\Core\Application\Documents\AppDocuments;
use Modules\Core\Application\Documents\DocumentSettings;
use Modules\Core\Application\Documents\DocumentSettingsInvalid;
use Modules\Core\Application\Documents\DocumentSource;
use Modules\Core\Domain\Documents\OutputDelivery;
use Modules\Core\Domain\Documents\OutputMode;
use Modules\Core\Domain\Documents\OutputPlacement;
use Modules\Core\Domain\Documents\PaperSize;
use Modules\Core\Domain\Documents\TemplateLayout;
use Modules\Core\Domain\Documents\ValueFormat;
use Modules\Core\Models\DocumentOutput;
use Modules\Core\Models\DocumentTemplate;

/**
 * アプリの設定 → 帳票出力 (AppSettings, ?tab=documents; administrators): the app's 帳票テンプレート — テンプレートを追加する
 * (an uploaded background PDF or a blank sheet), 編集 (the designer, ?template=<id>), 複製, 削除 — and its 出力設定:
 * the buttons printing a template, where they are shown (several places), 1つのPDF / ZIP for several records,
 * opening or downloading, ファイル名 and 管理者のみ; dragged into the order of the buttons.
 */
trait ManagesDocuments
{
    /** アプリの設定 tab: '' = 設定, 'documents' = 帳票出力 */
    #[Url(as: 'tab')]
    public string $settingsSection = '';

    /** 帳票出力: the template open in the designer */
    #[Url(as: 'template')]
    public ?int $designing = null;

    private ?DocumentSource $documentSource = null;

    public function isDocumentsTab(): bool
    {
        return $this->settingsSection === 'documents';
    }

    public function documentsUrl(?int $template = null): string
    {
        return static::getUrl(['tab' => 'documents', ...($template !== null ? ['template' => $template] : [])]);
    }

    /** @return Collection<int, DocumentTemplate> */
    public function documentTemplates(): Collection
    {
        return $this->app()->documentTemplates()->withCount('outputs')->get();
    }

    /** @return Collection<int, DocumentOutput> */
    public function documentOutputs(): Collection
    {
        return $this->app()->documentOutputs()->with('template')->get();
    }

    public function designedTemplate(): ?DocumentTemplate
    {
        return $this->designing === null ? null : $this->app()->documentTemplates()->whereKey($this->designing)->first();
    }

    /**
     * Data of the designer (sfkDocumentDesigner, core::filament.documents.designer).
     *
     * @return array<string, mixed>
     */
    public function designerConfig(): array
    {
        $template = $this->designedTemplate();
        abort_if($template === null, 404);
        $source = $this->documentSource();

        $tables = [];
        foreach ($source->tables() as $name => $table) {
            $tables[] = [
                'name' => $name,
                'label' => $table['label'],
                'columns' => array_map(fn (string $column, string $label): array => ['name' => $column, 'label' => $label], array_keys($table['columns']), $table['columns']),
            ];
        }

        return [
            'name' => $template->name,
            'pages' => $template->pages,
            'elements' => $template->elements(),
            'background' => $template->backgroundFile() !== null ? route('core.documents.background', ['template' => $template, 'v' => $template->updated_at?->timestamp]) : null,
            'pdfjs' => asset('vendor/pdfjs/pdf.min.js'),
            'pdfjsWorker' => asset('vendor/pdfjs/pdf.worker.min.js'),
            'pdfjsCmaps' => asset('vendor/pdfjs/cmaps').'/',
            'pdfjsFonts' => asset('vendor/pdfjs/standard_fonts').'/',
            'fields' => self::pairs($source->fields()),
            'system' => self::pairs(TemplateLayout::SYSTEM_FIELDS),
            'tables' => $tables,
            'formats' => self::pairs(ValueFormat::options()),
            'fonts' => self::pairs(TemplateLayout::FONTS),
            'aligns' => self::pairs(TemplateLayout::ALIGNS),
            'valigns' => self::pairs(TemplateLayout::VERTICAL_ALIGNS),
            'overflows' => self::pairs(TemplateLayout::OVERFLOWS),
            'previewUrl' => route('core.documents.preview', ['template' => $template]),
            'records' => $this->previewRecords(),
            'csrf' => csrf_token(),
        ];
    }

    /**
     * The designer's 保存; returns the errors by input, or null when saved.
     *
     * @return array<string, string>|null
     */
    public function saveDesign(mixed $input): ?array
    {
        abort_unless(static::canAccess(), 403);
        $template = $this->designedTemplate();
        if ($template === null) {
            return ['name' => 'テンプレートが見つかりません。'];
        }

        $input = is_array($input) ? $input : [];

        try {
            app(DocumentSettings::class)->saveDesign($template, $input['name'] ?? null, $input['elements'] ?? [], $this->documentUserId());
        } catch (DocumentSettingsInvalid $invalid) {
            return $invalid->errors;
        }

        Notification::make()->title('テンプレートを保存しました')->success()->send();

        return null;
    }

    public function addTemplateAction(): Action
    {
        return Action::make('addTemplate')
            ->label('テンプレートを追加する')
            ->icon(Heroicon::Plus)
            ->modalHeading('テンプレートを追加する')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('作成する')
            ->modalCancelActionLabel('キャンセル')
            ->schema([
                TextInput::make('name')->label('テンプレート名')->required()->maxLength(100),
                ...self::backgroundFields(),
            ])
            ->action(function (array $data): void {
                $template = $this->settingsCall(fn (): DocumentTemplate => app(DocumentSettings::class)->createTemplate($this->app(), [
                    'name' => $data['name'] ?? null,
                    'background' => ($data['source'] ?? 'pdf') === 'pdf' ? ($data['background'] ?? null) : null,
                    'background_name' => $data['background_name'] ?? null,
                    'paper' => $data['paper'] ?? null,
                ], $this->documentUserId()));

                Notification::make()->title('テンプレートを作成しました')->success()->send();
                $this->redirect($this->documentsUrl($template->id));
            });
    }

    public function replaceBackgroundAction(): Action
    {
        return Action::make('replaceBackground')
            ->label('背景を差し替える')
            ->modalHeading('背景を差し替える')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('差し替える')
            ->modalCancelActionLabel('キャンセル')
            ->schema(self::backgroundFields())
            ->action(function (array $data): void {
                $template = $this->designedTemplate();
                if ($template === null) {
                    return;
                }

                $pdf = ($data['source'] ?? 'pdf') === 'pdf';
                $this->settingsCall(fn (): DocumentTemplate => app(DocumentSettings::class)->replaceBackground(
                    $template,
                    $pdf ? ($data['background'] ?? null) : null,
                    $data['background_name'] ?? null,
                    $data['paper'] ?? null,
                    $this->documentUserId(),
                ));

                Notification::make()->title('背景を差し替えました')->success()->send();
                $this->redirect($this->documentsUrl($template->id));
            });
    }

    public function copyTemplate(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $template = $this->app()->documentTemplates()->whereKey($id)->first();

        if ($template !== null) {
            app(DocumentSettings::class)->copyTemplate($template, $this->documentUserId());
            Notification::make()->title('テンプレートを複製しました')->success()->send();
        }
    }

    public function deleteTemplateAction(): Action
    {
        return Action::make('deleteTemplate')
            ->label('削除')
            ->link()
            ->color('gray')
            ->icon(Heroicon::OutlinedTrash)
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalWidth('md')
            ->modalHeading(fn (array $arguments): string => '「'.($this->app()->documentTemplates()->whereKey((int) ($arguments['template'] ?? 0))->value('name') ?? '').'」を削除しますか？')
            ->modalSubmitActionLabel('削除する')
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->modalCancelActionLabel('キャンセル')
            ->action(function (array $arguments): void {
                $template = $this->app()->documentTemplates()->whereKey((int) ($arguments['template'] ?? 0))->first();
                if ($template === null) {
                    return;
                }

                try {
                    app(DocumentSettings::class)->deleteTemplate($template);
                } catch (DocumentSettingsInvalid $invalid) {
                    Notification::make()->title(array_values($invalid->errors)[0] ?? '削除できません。')->danger()->send();

                    return;
                }

                Notification::make()->title('テンプレートを削除しました')->success()->send();
            });
    }

    /** 出力設定を追加する / 編集 (arguments: output). */
    public function outputAction(): Action
    {
        return Action::make('output')
            ->label('出力設定を追加する')
            ->icon(Heroicon::Plus)
            ->modalHeading(fn (array $arguments): string => isset($arguments['output']) ? '出力設定を編集する' : '出力設定を追加する')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('保存')
            ->modalCancelActionLabel('キャンセル')
            ->fillForm(function (array $arguments): array {
                $output = isset($arguments['output']) ? $this->app()->documentOutputs()->whereKey((int) $arguments['output'])->first() : null;

                return $output === null
                    ? [
                        'document_template_id' => $this->app()->documentTemplates()->value('id'),
                        'placements' => [OutputPlacement::RecordMenu->value],
                        'mode' => OutputMode::Merged->value,
                        'delivery' => OutputDelivery::Open->value,
                        'admin_only' => false,
                    ]
                    : [
                        'name' => $output->name,
                        'document_template_id' => $output->document_template_id,
                        'placements' => $output->placements,
                        'mode' => $output->mode->value,
                        'delivery' => $output->delivery->value,
                        'file_name' => $output->file_name,
                        'admin_only' => $output->admin_only,
                    ];
            })
            ->schema([
                TextInput::make('name')->label('ボタン名')->required()->maxLength(64),
                Select::make('document_template_id')
                    ->label('テンプレート')
                    ->options(fn (): array => $this->app()->documentTemplates()->pluck('name', 'id')->all())
                    ->native(false)
                    ->required(),
                CheckboxList::make('placements')->label('表示する場所')->options(OutputPlacement::options())->required(),
                Radio::make('mode')->label('複数のレコードを出力するとき')->options(OutputMode::options())->required(),
                Radio::make('delivery')->label('出力したファイル')->options(OutputDelivery::options())->required(),
                TextInput::make('file_name')->label('ファイル名')->maxLength(200)->placeholder('{'.(array_key_first($this->documentSource()->fields()) ?? 'id').'}'),
                Select::make('file_name_field')
                    ->label('ファイル名にフィールドを追加')
                    ->options(fn (): array => $this->documentSource()->fields())
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if (filled($state)) {
                            $set('file_name', $get('file_name').'{'.$state.'}');
                            $set('file_name_field', null);
                        }
                    }),
                Checkbox::make('admin_only')->label('管理者にだけ表示する'),
            ])
            ->action(function (array $data, array $arguments): void {
                $output = isset($arguments['output']) ? $this->app()->documentOutputs()->whereKey((int) $arguments['output'])->first() : null;
                $this->settingsCall(fn (): DocumentOutput => app(DocumentSettings::class)->saveOutput($this->app(), $output, $data));
                AppDocuments::forgetCached();

                Notification::make()->title('出力設定を保存しました')->success()->send();
            });
    }

    public function deleteOutputAction(): Action
    {
        return Action::make('deleteOutput')
            ->label('削除')
            ->link()
            ->color('gray')
            ->icon(Heroicon::OutlinedTrash)
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalWidth('md')
            ->modalHeading(fn (array $arguments): string => '「'.($this->app()->documentOutputs()->whereKey((int) ($arguments['output'] ?? 0))->value('name') ?? '').'」を削除しますか？')
            ->modalSubmitActionLabel('削除する')
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->modalCancelActionLabel('キャンセル')
            ->action(function (array $arguments): void {
                $output = $this->app()->documentOutputs()->whereKey((int) ($arguments['output'] ?? 0))->first();
                if ($output !== null) {
                    app(DocumentSettings::class)->deleteOutput($output);
                    Notification::make()->title('出力設定を削除しました')->success()->send();
                }
            });
    }

    /** @param  list<int|string>  $ids  the 出力設定 in their new order */
    public function reorderOutputs(array $ids): void
    {
        abort_unless(static::canAccess(), 403);
        app(DocumentSettings::class)->reorderOutputs($this->app(), array_map('intval', $ids));
    }

    /** @return list<Component> 背景: an uploaded PDF or a blank sheet of a size */
    private static function backgroundFields(): array
    {
        return [
            Radio::make('source')
                ->label('背景')
                ->options(['pdf' => 'PDFファイル', 'blank' => '白紙'])
                ->default('pdf')
                ->inline()
                ->live()
                ->required(),
            FileUpload::make('background')
                ->label('PDFファイル')
                ->disk(DocumentTemplate::DISK)
                ->directory('documents/uploads')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(20480)
                ->storeFileNamesIn('background_name')
                ->visible(fn (Get $get): bool => $get('source') !== 'blank')
                ->required(fn (Get $get): bool => $get('source') !== 'blank'),
            Select::make('paper')
                ->label('用紙')
                ->options(PaperSize::options())
                ->default(PaperSize::A4Portrait->value)
                ->native(false)
                ->visible(fn (Get $get): bool => $get('source') === 'blank')
                ->required(fn (Get $get): bool => $get('source') === 'blank'),
        ];
    }

    /**
     * Runs a settings change; an invalid input shows its message and keeps the dialog open.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function settingsCall(callable $call): mixed
    {
        abort_unless(static::canAccess(), 403);

        try {
            return $call();
        } catch (DocumentSettingsInvalid $invalid) {
            Notification::make()->title(array_values($invalid->errors)[0] ?? '保存できません。')->danger()->send();

            throw new Halt;
        }
    }

    private function documentSource(): DocumentSource
    {
        $user = auth()->user();

        return $this->documentSource ??= DocumentSource::for($this->appResource(), $user instanceof User ? $user : null);
    }

    /** @return list<array{key: string, title: string}> the latest records, for プレビュー */
    private function previewRecords(): array
    {
        $resource = $this->appResource();
        $source = $this->documentSource();
        $model = $resource::getModel();
        $key = (new $model)->getQualifiedKeyName();

        return $resource::getEloquentQuery()->reorder()->orderByDesc($key)->limit(50)->get()
            ->map(fn (Model $record): array => ['key' => (string) $record->getKey(), 'title' => $source->title($record)])
            ->values()
            ->all();
    }

    private function documentUserId(): ?int
    {
        $user = auth()->user();

        return $user instanceof User ? $user->id : null;
    }

    /**
     * @param  array<string, string>  $options
     * @return list<array{value: string, label: string}>
     */
    private static function pairs(array $options): array
    {
        return array_map(fn (string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys($options), $options);
    }
}
