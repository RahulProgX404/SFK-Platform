<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders\Pages;

use DateTimeImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Orders\Application\FaxImport\FaxDocument;
use Modules\Orders\Application\FaxImport\FaxImportFailed;
use Modules\Orders\Application\FaxImport\FaxOrderImport;
use Modules\Orders\Domain\FaxImport\FaxImportRules;
use Modules\Orders\Domain\FaxImport\FaxOrderMapper;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;

/**
 * New order of 04. 受注入力. 注番 is numbered on save (BR-OR-01). 「…受注書を読み込む」 (legacy create-screen button)
 * reads an uploaded purchase order of the configured issuer (Application FaxOrderImport) and, after
 * 「フォームに反映する内容を確認」, fills the form with it; nothing is saved until 保存.
 */
final class CreateOrder extends AppCreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = '受注を追加';

    /** @var array<string, mixed>|null the purchase order just read (FaxImportResult::toArray()) */
    public ?array $faxImport = null;

    protected function getHeaderActions(): array
    {
        return [...parent::getHeaderActions(), $this->faxImportAction()];
    }

    public function faxImportAction(): Action
    {
        $label = self::faxImportLabel();
        $config = (array) config('orders.fax_import');

        return Action::make('faxImport')
            ->label($label)
            ->icon(Heroicon::OutlinedDocumentArrowUp)
            ->color('gray')
            ->extraAttributes(['class' => 'sfk-toolbar-end'])
            ->modalHeading($label)
            ->modalWidth('md')
            ->modalSubmitActionLabel('読み込む')
            ->modalCancelActionLabel('キャンセル')
            ->schema([
                FileUpload::make('file')
                    ->label('受注書（画像 / PDF）')
                    ->acceptedFileTypes(FaxDocument::MEDIA_TYPES)
                    ->maxSize((int) ($config['max_file_size'] ?? 20480))
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $file = $data['file'] ?? null;
                $file = is_array($file) ? reset($file) : $file;
                if (! $file instanceof TemporaryUploadedFile) {
                    return;
                }

                try {
                    $result = app(FaxOrderImport::class)->import(
                        FaxDocument::fromContents((string) $file->get(), $file->getMimeType(), $file->getClientOriginalName()),
                        FaxImportRules::fromConfig((array) config('orders.fax_import')),
                        new DateTimeImmutable('today'),
                    );
                } catch (FaxImportFailed $failed) {
                    Notification::make()->title($failed->getMessage())->danger()->persistent()->send();

                    return;
                }

                $this->faxImport = $result->toArray();
                $this->replaceMountedAction('faxPreview');
            });
    }

    /** フォームに反映する内容を確認 → この内容でフォームに反映 */
    public function faxPreviewAction(): Action
    {
        return Action::make('faxPreview')
            ->modalHeading('フォームに反映する内容を確認')
            ->modalWidth('3xl')
            ->modalContent(fn () => view('orders::filament.fax-preview', ['import' => $this->faxImport ?? []]))
            ->modalSubmitActionLabel('この内容でフォームに反映')
            ->modalCancelActionLabel('キャンセル')
            ->action(fn () => $this->applyFaxImport());
    }

    /** Puts the values read into the form (the user checks and saves). */
    public function applyFaxImport(): void
    {
        $values = (array) ($this->faxImport['values'] ?? []);
        foreach ($values as $field => $value) {
            if (is_string($field) && $value !== null && $value !== '') {
                $this->data[$field] = $value;
            }
        }
        $this->faxImport = null;

        Notification::make()->title('発注書の内容をフォームに反映しました。内容を確認して保存してください。')->success()->send();
    }

    /** 「ハート封筒受注書を読み込む」: the issuer's name without its legal form. */
    public static function faxImportLabel(): string
    {
        return FaxOrderMapper::companyKey((string) config('orders.fax_import.issuer')).'受注書を読み込む';
    }

    /** The tables are saved after the record: recalculate, then start the 変更履歴. */
    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        if ($record instanceof Order) {
            $record->refreshTotals();
        }

        parent::afterCreate();
    }

    /**
     * レコードを再利用する: 注番 is numbered anew; the legacy id, the attachments and the actual results written by other
     * apps (製造済数量, 売上済, 仕入, 社内原価) are not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return [
            'order_number', 'legacy_id', 'work_sheet_order_files', 'work_sheet_sales_files', 'work_sheet_draft_files', 'manufactured_quantity',
            'sold_quantity', 'sold_amount', 'purchase_amount', 'purchase_cutting_amount', 'purchase_plate_amount', 'purchase_stored_plate_amount',
            'purchase_spot_color_amount', 'internal_cost', 'manufacturing_sales', 'closing_day', 'customer_rounding',
        ];
    }

    /**
     * 担当者 and the rows of the tables are copied too.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        if ($source instanceof Order) {
            $data['assignees'] = $source->assignees()->pluck('users.id')->all();
            foreach (Order::TABLES as $table => $model) {
                $rows = [];
                foreach ($source->{$table}()->get() as $row) {
                    $rows[(string) Str::uuid()] = array_intersect_key($row->attributesToArray(), array_flip($model::COLUMNS));
                }
                $data[$table] = $rows;
            }
        }

        return $data;
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
