<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Records;

use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Infolists\Components\Entry;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use Modules\Core\Application\Documents\AppDocuments;
use Modules\Core\Domain\Documents\OutputDelivery;
use Modules\Core\Domain\Documents\OutputPlacement;
use Modules\Core\Filament\Support\DocumentNameField;
use Modules\Core\Filament\Support\MenuButtons;
use Modules\Core\Models\DocumentOutput;

/**
 * エクスポート of the record page (帳票出力, アプリの設定 → 帳票出力): an entry per 出力設定 putting out this record,
 * asking first for the field of 「{ユーザー選択}」 in the ファイル名 where needed.
 *
 * Part of Modules\Core\Filament\AppPages\AppViewRecord (the shared record page).
 */
trait RecordExports
{
    /**
     * エクスポート (帳票出力, アプリの設定 → 帳票出力): an entry per 出力設定 of the record page, putting out this record
     * (a new tab, or a download). 出力設定 with the same ボタン名 are one entry with a sub-menu of their サブボタン名 (e.g.
     * the legacy 作業明細表 — 注文書 / 営業控 / 原稿添付用, SYSTEM_BRAIN 08_PRINTCREATOR). When the ファイル名 has
     * 「{ユーザー選択}」 the user first chooses the field of the name.
     *
     * @return list<Action|ActionGroup>
     */
    private function exportActions(): array
    {
        if ($this->printMode || $this->historyVersion !== null) {
            return [];
        }

        $outputs = collect([...$this->documentOutputsAt(OutputPlacement::RecordMenu), ...$this->documentOutputsAt(OutputPlacement::RecordSpace)])
            ->unique('id')->values()->all();

        return array_map(fn (array $group): Action|ActionGroup => MenuButtons::exportEntry(array_map(
            fn (DocumentOutput $output): Action => $this->recordDocumentAction($output, count($group) > 1 ? $output->optionLabel() : $output->name),
            $group,
        ), $group[0]->name), AppDocuments::groups($outputs));
    }

    private function recordDocumentAction(DocumentOutput $output, string $label): Action
    {
        $action = Action::make('document'.$output->id)->label($label)->icon(Heroicon::OutlinedPrinter);

        return $output->asksForNameField()
            ? $this->askingNameField($action, fn (): DocumentOutput => $output)
            : $action->url(fn (): string => $this->documentUrl($output))->openUrlInNewTab($output->delivery === OutputDelivery::Open);
    }

    /** @return list<DocumentOutput> the 出力設定 the user gets at the place */
    private function documentOutputsAt(OutputPlacement $placement): array
    {
        $user = auth()->user();

        return AppDocuments::at($this->appResource()::app(), $user instanceof User ? $user : null, $placement);
    }

    private function documentUrl(DocumentOutput $output, ?string $nameField = null): string
    {
        return route('core.documents.record', array_filter([
            'output' => $output,
            'record' => $this->record->getKey(),
            'name_field' => $nameField,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * 出力 asking first which field goes into the ファイル名 (「{ユーザー選択}」), then opening this record's 帳票 in a
     * new tab, or downloading it.
     *
     * @param  Closure(array<string, mixed>): ?DocumentOutput  $output
     */
    private function askingNameField(Action $action, Closure $output): Action
    {
        return $action
            ->icon(Heroicon::OutlinedPrinter)
            ->modalIcon(Heroicon::OutlinedPrinter)
            ->modalWidth('md')
            ->modalHeading(fn (array $arguments): string => $output($arguments)->name ?? '帳票出力')
            ->modalSubmitActionLabel('出力する')
            ->modalCancelActionLabel('キャンセル')
            ->schema([DocumentNameField::make($this->appResource())])
            ->action(function (array $data, array $arguments) use ($output): void {
                $document = $output($arguments);
                if ($document === null) {
                    return;
                }

                $url = $this->documentUrl($document, is_string($data['name_field'] ?? null) ? $data['name_field'] : null);
                $this->js($document->delivery === OutputDelivery::Open
                    ? 'window.open('.Js::from($url).', "_blank")'
                    : 'window.location.href = '.Js::from($url));
            });
    }
}
