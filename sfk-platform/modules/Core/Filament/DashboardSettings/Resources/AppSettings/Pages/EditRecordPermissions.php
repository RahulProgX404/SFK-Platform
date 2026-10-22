<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Apps\RecordRuleService;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Domain\Filters\FilterFieldType;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\Filters\RecordFilter;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\DashboardSettings\Concerns\HasSettingsChrome;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AccessEntryFields;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AppSettingsResource;
use Modules\Core\Models\BusinessApp;

/**
 * レコードのアクセス権 of one app (RecordAccess): the settings in priority order, each with the records it covers
 * (conditions on the app's 絞り込む fields; none = every record) and who may 閲覧 / 編集 / 削除 them, read top to bottom.
 * キャンセル / 保存 at the top, like the アプリ permissions.
 *
 * @property BusinessApp $record
 */
final class EditRecordPermissions extends EditRecord
{
    use HasSettingsChrome;

    protected static string $resource = AppSettingsResource::class;

    public function getTitle(): string
    {
        return "{$this->record->name} のレコードのアクセス権";
    }

    public function settingsPageKind(): string
    {
        return 'edit';
    }

    protected function settingsRecordTitle(): string
    {
        return $this->record->name;
    }

    public function form(Schema $schema): Schema
    {
        $fields = $this->fields();

        return $schema->columns(1)->components([
            Section::make('レコードのアクセス権')
                ->schema([
                    Repeater::make('rules')
                        ->hiddenLabel()
                        ->defaultItems(0)
                        ->reorderable()
                        ->addActionLabel('設定を追加')
                        ->itemLabel(fn (array $state): string => self::ruleLabel($state))
                        ->extraAttributes(['class' => 'sfk-access sfk-access-numbered'])
                        ->schema([
                            Radio::make('match')
                                ->label('条件')
                                ->options([RecordFilter::MATCH_ALL => 'すべての条件を満たす', RecordFilter::MATCH_ANY => 'いずれかの条件を満たす'])
                                ->default(RecordFilter::MATCH_ALL)
                                ->inline(),
                            Repeater::make('conditions')
                                ->hiddenLabel()
                                ->defaultItems(0)
                                ->reorderable(false)
                                ->addActionLabel('条件を追加')
                                ->extraAttributes(['class' => 'sfk-access sfk-access-conditions'])
                                ->columns(12)
                                ->schema(self::conditionFields($fields)),
                            AccessEntryFields::forRecords(),
                        ]),
                ]),
        ]);
    }

    /**
     * Field, comparison and value of a condition row; the value input follows the field's type.
     *
     * @param  array<string, FilterField>  $fields
     * @return list<Component>
     */
    private static function conditionFields(array $fields): array
    {
        $type = fn (Get $get): ?FilterFieldType => ($fields[(string) $get('field')] ?? null)?->type;
        $needsValue = fn (Get $get): bool => FilterOperator::tryFrom((string) $get('operator'))?->needsValue() ?? true;

        return [
            Select::make('field')
                ->placeholder('-----')
                ->label('フィールド')
                ->options(array_map(fn (FilterField $field): string => $field->label, $fields))
                ->required()
                ->native(false)
                ->searchable()
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state) use ($fields): void {
                    $field = $fields[(string) $state] ?? null;
                    $set('operator', $field?->type->operators()[0]->value);
                    $set('value', null);
                    $set('date', null);
                    $set('choices', []);
                })
                ->columnSpan(4),
            Select::make('operator')
                ->placeholder('-----')
                ->label('条件')
                ->options(function (Get $get) use ($type): array {
                    $fieldType = $type($get);
                    $options = [];
                    foreach ($fieldType?->operators() ?? [] as $operator) {
                        $options[$operator->value] = $operator->label($fieldType);
                    }

                    return $options;
                })
                ->required()
                ->native(false)
                ->live()
                ->columnSpan(3),
            TextInput::make('value')
                ->label('値')
                ->visible(fn (Get $get): bool => $needsValue($get) && in_array($type($get), [FilterFieldType::Text, FilterFieldType::Textarea, FilterFieldType::Number], true))
                ->required(fn (Get $get): bool => $type($get) === FilterFieldType::Number || in_array($get('operator'), [FilterOperator::Contains->value, FilterOperator::NotContains->value, FilterOperator::OneOf->value], true))
                ->maxLength(1000)
                ->columnSpan(5),
            DatePicker::make('date')
                ->label('値')
                ->visible(fn (Get $get): bool => $needsValue($get) && $type($get)?->isDate() === true)
                ->required(fn (Get $get): bool => $needsValue($get) && $type($get)?->isDate() === true)
                ->columnSpan(5),
            Select::make('choices')
                ->placeholder('-----')
                ->label('値')
                ->multiple()
                ->options(function (Get $get) use ($fields, $type): array {
                    $field = $fields[(string) $get('field')] ?? null;

                    return match ($type($get)) {
                        FilterFieldType::User => [FilterField::LOGIN_USER => 'ログインユーザー'] + User::query()->where('user_type', UserType::Employee->value)->orderBy('name')->pluck('name', 'id')->mapWithKeys(fn (string $name, int $id): array => [(string) $id => $name])->all(),
                        FilterFieldType::Choice => $field?->getOptions() ?? [],
                        default => [],
                    };
                })
                ->visible(fn (Get $get): bool => in_array($type($get), [FilterFieldType::Choice, FilterFieldType::User], true))
                ->required(fn (Get $get): bool => in_array($type($get), [FilterFieldType::Choice, FilterFieldType::User], true))
                ->native(false)
                ->columnSpan(5),
        ];
    }

    /**
     * Header of a setting: the records it covers (「すべてのレコード」 or how many conditions, all / any).
     *
     * @param  array<string, mixed>  $state
     */
    private static function ruleLabel(array $state): string
    {
        $count = count(array_filter((array) ($state['conditions'] ?? []), fn (mixed $row): bool => is_array($row) && filled($row['field'] ?? null)));

        return match (true) {
            $count === 0 => 'すべてのレコード',
            $count === 1 => '条件 1件',
            default => '条件 '.$count.'件（'.(($state['match'] ?? null) === RecordFilter::MATCH_ANY ? 'いずれかを満たす' : 'すべてを満たす').'）',
        };
    }

    /** @return array<string, FilterField> the app's 絞り込む fields */
    private function fields(): array
    {
        $resource = AppResource::forCode($this->record->code);

        return $resource === null ? [] : $resource::filterFieldMap();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ['rules' => app(RecordRuleService::class)->state($this->record, $this->fields())];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BusinessApp $record */
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        app(RecordRuleService::class)->save($record, array_values((array) ($data['rules'] ?? [])), $this->fields(), $user, 'data.rules');

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->label('キャンセル')->color('gray'),
            Action::make('saveSettings')->label('保存')->action(fn () => $this->save())->keyBindings(['mod+s']),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getSavedNotification(): Notification
    {
        return Notification::make()->success()->title('レコードのアクセス権を保存しました');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
