<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Apps\FieldPermissionService;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\DashboardSettings\Concerns\HasSettingsChrome;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AccessEntryFields;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AppSettingsResource;
use Modules\Core\Models\BusinessApp;

/**
 * フィールドのアクセス権 of one app (FieldAccess): per field, who may 編集 / 閲覧 it or see nothing, read top to bottom
 * (fields not listed: everyone who may edit the record). キャンセル / 保存 at the top, like the アプリ permissions.
 *
 * @property BusinessApp $record
 */
final class EditFieldPermissions extends EditRecord
{
    use HasSettingsChrome;

    protected static string $resource = AppSettingsResource::class;

    public function getTitle(): string
    {
        return "{$this->record->name} のフィールドのアクセス権";
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
        return $schema->columns(1)->components([
            Section::make('フィールドのアクセス権')
                ->schema([
                    Repeater::make('fields')
                        ->hiddenLabel()
                        ->defaultItems(0)
                        ->reorderable()
                        ->addActionLabel('フィールドを追加')
                        ->itemLabel(fn (array $state): string => $this->knownFields()[(string) ($state['field'] ?? '')] ?? 'フィールド')
                        ->extraAttributes(['class' => 'sfk-access sfk-access-numbered'])
                        ->schema([
                            Select::make('field')
                                ->placeholder('-----')
                                ->label('フィールド')
                                ->options($this->knownFields())
                                ->required()
                                ->distinct()
                                ->native(false)
                                ->searchable(),
                            AccessEntryFields::forFields(),
                        ]),
                ]),
        ]);
    }

    /** @return array<string, string> the app's fields (name => label) */
    private function knownFields(): array
    {
        $resource = AppResource::forCode($this->record->code);

        return $resource === null ? [] : $resource::permissionFields();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ['fields' => app(FieldPermissionService::class)->state($this->record)];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BusinessApp $record */
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        app(FieldPermissionService::class)->save($record, array_values((array) ($data['fields'] ?? [])), $this->knownFields(), $user, 'data.fields');

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
        return Notification::make()->success()->title('フィールドのアクセス権を保存しました');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
