<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Domain\Documents\DocumentValue;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\Records\RecordTable;
use Throwable;

/**
 * The fields a 帳票 of an app can print, read from the app's record page (its infolist): every field shown there with
 * its legacy label and its text as shown (amounts with ¥, names instead of ids, dates), and every table (サブテーブル)
 * with its columns and rows. So every app has its 帳票 fields without code of its own, and they follow the record
 * page when it changes. Fields drawn as HTML there (e.g. the ユーザー情報 popup) are read from the model instead.
 *
 * フィールドのアクセス権 apply: fields the user may not see (なし) are left out and print empty.
 */
final class DocumentSource
{
    /** @var array<string, string>|null name => label */
    private ?array $fields = null;

    /** @var array<string, array{label: string, columns: array<string, string>}>|null */
    private ?array $tables = null;

    private ?Schema $recordSchema = null;

    /** @var list<string> */
    private array $hidden;

    /**
     * @param  class-string<AppResource>  $resource
     * @param  class-string<ViewRecord>|null  $page  the app's record page
     */
    private function __construct(
        public readonly string $resource,
        private readonly ?string $page,
        ?User $user,
    ) {
        $app = $resource::app();
        $levels = $user !== null && $app !== null ? FieldAccess::levels($user, $app) : [];
        $this->hidden = array_keys(array_filter($levels, fn ($level): bool => ! $level->canView()));
    }

    /** @param  class-string<AppResource>  $resource */
    public static function for(string $resource, ?User $user): self
    {
        self::usePanelOf($resource);
        $registration = $resource::getPages()['view'] ?? null;
        $page = $registration?->getPage();

        return new self($resource, is_string($page) && is_subclass_of($page, ViewRecord::class) ? $page : null, $user);
    }

    /**
     * The fields of the records (name => label), in the order of the record page.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        $this->readCatalog();

        return $this->fields ?? [];
    }

    /**
     * The tables of the records with their columns (name => label).
     *
     * @return array<string, array{label: string, columns: array<string, string>}>
     */
    public function tables(): array
    {
        $this->readCatalog();

        return $this->tables ?? [];
    }

    /** What a 帳票 prints of the record. */
    public function data(Model $record): RecordData
    {
        $schema = $this->recordSchema($record);
        $fields = [];
        $tables = [];

        if ($schema !== null) {
            $this->walk(
                $schema,
                function (Entry $entry) use (&$fields, $record): void {
                    $name = $entry->getName();
                    if (! array_key_exists($name, $fields) && ! in_array($name, $this->hidden, true)) {
                        $fields[$name] = $this->entryValue($entry, $record);
                    }
                },
                function (RepeatableEntry $entry) use (&$tables, $record): void {
                    $name = $entry->getName();
                    if (! array_key_exists($name, $tables) && ! in_array($name, $this->hidden, true)) {
                        $tables[$name] = $this->rows($entry, $record);
                    }
                },
            );
        }

        return new RecordData((string) $record->getKey(), $this->title($record), $fields, $tables);
    }

    /** A record for the preview without data: every field shows its label, tables three rows of column labels. */
    public function sample(): RecordData
    {
        $fields = array_map(DocumentValue::text(...), $this->fields());
        $tables = [];
        foreach ($this->tables() as $name => $table) {
            $row = array_map(DocumentValue::text(...), $table['columns']);
            $tables[$name] = [$row, $row, $row];
        }

        return new RecordData('', '', $fields, $tables);
    }

    /** The record's title on its page (e.g. 納品書番号). */
    public function title(Model $record): string
    {
        try {
            return ModelValues::plain((string) $this->resource::getRecordTitle($record));
        } catch (Throwable) {
            return (string) $record->getKey();
        }
    }

    /**
     * The dashboard implementing the app becomes the current panel (outside its pages, e.g. in the PDF routes), so
     * the record page builds as on screen.
     *
     * @param  class-string<AppResource>  $resource
     */
    private static function usePanelOf(string $resource): void
    {
        $current = Filament::getCurrentPanel();
        if ($current !== null && in_array($resource, $current->getResources(), true)) {
            return;
        }

        foreach (Filament::getPanels() as $panel) {
            if (in_array($resource, $panel->getResources(), true)) {
                Filament::setCurrentPanel($panel);

                return;
            }
        }
    }

    private function readCatalog(): void
    {
        if ($this->fields !== null) {
            return;
        }

        $this->fields = [];
        $this->tables = [];
        $model = $this->resource::getModel();
        $schema = $this->page === null ? null : $this->schemaOf(new $model);
        if ($schema === null) {
            return;
        }

        $this->walk(
            $schema,
            function (Entry $entry): void {
                $name = $entry->getName();
                if (! array_key_exists($name, $this->fields ?? []) && ! in_array($name, $this->hidden, true)) {
                    $this->fields[$name] = self::label($entry);
                }
            },
            function (RepeatableEntry $entry): void {
                $name = $entry->getName();
                if (array_key_exists($name, $this->tables ?? []) || in_array($name, $this->hidden, true)) {
                    return;
                }

                $headers = RecordTable::columnLabels($entry);
                $columns = [];
                $index = 0;
                foreach ($entry->getChildComponents() as $child) {
                    if ($child instanceof Entry) {
                        $columns[$child->getName()] = $headers[$index] ?? self::label($child);
                        $index++;
                    }
                }
                $this->tables[$name] = ['label' => self::label($entry), 'columns' => $columns];
            },
        );
    }

    /** The record page's schema bound to the record (built once, then rebound: fast for many records). */
    private function recordSchema(Model $record): ?Schema
    {
        if ($this->page === null) {
            return null;
        }

        if ($this->recordSchema === null) {
            return $this->recordSchema = $this->schemaOf($record);
        }

        return $this->recordSchema->record($record);
    }

    private function schemaOf(Model $record): ?Schema
    {
        try {
            /** @var ViewRecord $page */
            $page = app((string) $this->page);
            $page->record = $record;

            return $page->getSchema('infolist');
        } catch (Throwable $error) {
            report($error);

            return null;
        }
    }

    /**
     * Visits the entries of a schema in order; tables are visited as a whole (not their columns).
     *
     * @param  callable(Entry): void  $field
     * @param  callable(RepeatableEntry): void  $table
     */
    private function walk(Schema $schema, callable $field, callable $table): void
    {
        foreach ($schema->getComponents() as $component) {
            if ($component instanceof RepeatableEntry) {
                $table($component);

                continue;
            }
            if ($component instanceof Entry) {
                $field($component);
            }
            foreach ($component->getChildSchemas() as $child) {
                $this->walk($child, $field, $table);
            }
        }
    }

    /** @return list<array<string, DocumentValue>> */
    private function rows(RepeatableEntry $entry, Model $record): array
    {
        $rows = [];
        try {
            foreach ($entry->getItems() as $item) {
                $row = [];
                $rowRecord = $item->getRecord();
                $this->walk(
                    $item,
                    function (Entry $column) use (&$row, $rowRecord, $record): void {
                        $row[$column->getName()] = $this->entryValue($column, $rowRecord instanceof Model ? $rowRecord : $record);
                    },
                    fn (RepeatableEntry $nested) => null,
                );
                $rows[] = $row;
            }
        } catch (Throwable $error) {
            report($error);
        }

        return $rows;
    }

    private function entryValue(Entry $entry, Model $record): DocumentValue
    {
        try {
            $state = $entry->getState();

            if ($state instanceof Htmlable || ($entry instanceof TextEntry && $entry->isHtml())) {
                return ModelValues::of($record, $entry->getName());
            }

            if ($state instanceof Collection || is_array($state)) {
                $items = $state instanceof Collection ? $state->all() : $state;
                $texts = array_values(array_filter(array_map(fn (mixed $item): string => self::text($entry, $item), $items), fn (string $text): bool => $text !== ''));

                return DocumentValue::text(implode('、', $texts));
            }

            return new DocumentValue(self::text($entry, $state), ModelValues::raw($state));
        } catch (Throwable) {
            return ModelValues::of($record, $entry->getName());
        }
    }

    /** The text of a value as the entry shows it. */
    private static function text(Entry $entry, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return ModelValues::text($entry instanceof TextEntry ? $entry->formatState($value) : $value);
    }

    private static function label(Entry $entry): string
    {
        $label = $entry->getLabel();
        $text = $label instanceof Htmlable ? ModelValues::plain($label->toHtml()) : trim((string) $label);

        return $text !== '' ? $text : $entry->getName();
    }
}
