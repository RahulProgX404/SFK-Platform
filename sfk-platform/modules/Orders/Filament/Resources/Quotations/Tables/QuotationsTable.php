<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Quotations\Tables;

use App\Models\User;
use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Filament\Resources\Quotations\QuotationLayout;
use Modules\Orders\Filament\Resources\Quotations\QuotationResource;
use Modules\Orders\Models\Quotation;

/**
 * Record list of 09. 見積書管理 with the legacy view 見積一覧 (SYSTEM_BRAIN APP_09 views.md, newest first); （すべて） shows
 * columns() in order and the search box replaces 見積一覧(検索特化) (its legacy HTML was empty). インライン編集 changes the
 * specification texts and 備考.
 */
final class QuotationsTable
{
    public const VIEW_LIST = 'list';

    public const LIST_COLUMNS = ['quoted_on', 'quote_number', 'customer_id', 'product_name', 'size', 'paper', 'printing', 'window', 'flap_glue', 'total'];

    private const TEXTS = ['product_name', 'size', 'paper', 'printing', 'window', 'flap_glue', 'free_text_1', 'free_text_2', 'free_text_3'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'quoted_on', 'customer_id', 'assignees', 'quote_number', 'product_name', 'size', 'paper', 'printing', 'window', 'flap_glue', 'total', 'notes',
            'pdf_files', 'customer_code', 'file_name', 'free_text_1', 'free_text_2', 'free_text_3', 'created_at'] as $name) {
            $columns[$name] = QuotationLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(self::VIEW_LIST, '見積一覧', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']])];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'assignees']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(QuotationResource::class))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateHeading('見積書がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_09 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => QuotationLayout::label($name);

        return [
            FilterField::date('quoted_on', $label('quoted_on')),
            FilterField::choice('customer_id', $label('customer_id'), fn (): array => Partner::query()->orderByDesc('id')->pluck('name', 'id')
                ->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::user('assignees', $label('assignees'))->relationship('assignees', 'id'),
            FilterField::text('quote_number', $label('quote_number')),
            ...array_map(fn (string $name): FilterField => FilterField::text($name, $label($name)), self::TEXTS),
            FilterField::number('total', $label('total')),
            FilterField::textarea('notes', $label('notes')),
            FilterField::number('id', $label('id')),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /** @return list<InlineField> */
    public static function inlineFields(): array
    {
        return [
            ...array_map(fn (string $name): InlineField => InlineField::text($name)->maxLength(255), self::TEXTS),
            InlineField::textarea('notes'),
        ];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(QuotationLayout::label($name))->placeholder('');
        $partner = fn (string $attribute): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            Partner::query()->select("partners.{$attribute}")->whereColumn('partners.id', 'quotations.customer_id'),
            $direction,
        );

        return match (true) {
            in_array($name, ['quote_number', ...self::TEXTS], true) => $column->searchable(),
            $name === 'notes' => $column->searchable()->limit(60),
            $name === 'quoted_on' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            $name === 'total' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            $name === 'customer_id' => $column
                ->state(fn (Quotation $record): ?string => $record->customer?->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('customer', fn (Builder $q): Builder => $q
                    ->whereLike('name', "%{$search}%")->orWhereLike('short_name', "%{$search}%")))
                ->sortable(query: $partner('name')),
            $name === 'customer_code' => $column->state(fn (Quotation $record): ?string => $record->customer?->code)->sortable(query: $partner('code')),
            $name === 'file_name' => $column->state(fn (Quotation $record): string => $record->fileName())
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('quotations.quote_number', $direction)),
            $name === 'pdf_files' => $column->state(fn (Quotation $record): array => array_map('basename', (array) $record->pdf_files))->listWithLineBreaks()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('quotations.id', $direction)),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            $name === 'assignees' => $column
                ->state(fn (Quotation $record): HtmlString => UserPopup::list($record->assignees))
                ->html()
                ->disabledClick()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    User::query()->select('users.name')->join('quotation_user', 'quotation_user.user_id', '=', 'users.id')
                        ->whereColumn('quotation_user.quotation_id', 'quotations.id')->orderBy('users.name')->limit(1),
                    $direction,
                )),
            default => $column,
        };
    }
}
