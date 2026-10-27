<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Tables;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Search\SearchQuery;
use Modules\Core\Filament\AppPages\AppListRecords;

/**
 * Text column of an app list: in the row being edited by インライン編集 it shows the input of its InlineField
 * (AppListRecords::inlineFields()) instead of the value, and that row does not open the record on a click.
 */
class AppTextColumn extends TextColumn
{
    public function toEmbeddedHtml(): string
    {
        $page = $this->editingPage();
        $field = $page?->inlineField($this->getName());

        if ($page === null || $field === null) {
            return parent::toEmbeddedHtml();
        }

        return $field->toHtml('inlineData', (string) $this->getLabel(), $page->getErrorBag(), $page->inlineData[$field->name] ?? null);
    }

    /**
     * The list search on this column compares the normalized forms (SearchQuery: 全角 / 半角, case, カタカナ / ひらがな,
     * romaji), so its trigram index is used. searchable(query: …) columns search their own way — for a related
     * record's column through SearchQuery::through().
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public function applySearchConstraint(Builder $query, string $search, bool &$isFirst): Builder
    {
        if ($this->searchQuery !== null || $this->hasRelationship($query->getModel())) {
            return parent::applySearchConstraint($query, $search, $isFirst);
        }

        foreach ($this->getSearchColumns($query->getModel()) as $column) {
            SearchQuery::where($query, $query->qualifyColumn($column), $search, $isFirst ? 'and' : 'or');
            $isFirst = false;
        }

        return $query;
    }

    public function isClickDisabled(): bool
    {
        return $this->editingPage() !== null || parent::isClickDisabled();
    }

    /** The list page while the column's record is being edited inline. */
    private function editingPage(): ?AppListRecords
    {
        $page = $this->getLivewire();
        $record = $this->getRecord();

        return $page instanceof AppListRecords && $record instanceof Model && $page->isInlineEditing($record) ? $page : null;
    }
}
