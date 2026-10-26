{{--
    アプリの設定 → 帳票出力 (Modules\Core\Filament\AppPages\Concerns\ManagesDocuments):
    - 帳票テンプレート: ＋ テンプレートを追加する; rows with 用紙 (the PDF's name or 白紙) and 編集 (designer) / 複製 / 削除.
    - 出力設定: ＋ 出力設定を追加する; rows in button order (ドラッグして並び替え) with テンプレート, 表示する場所 and
      編集 / 削除.
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div class="sfk-settings sfk-docs">
    <section class="sfk-views" aria-labelledby="sfk-docs-templates">
        <div class="sfk-views-head">
            <h2 id="sfk-docs-templates" class="sfk-views-title">帳票テンプレート</h2>
            <button type="button" class="sfk-app-add sfk-views-add" wire:click="mountAction('addTemplate')" title="テンプレートを追加する">
                <x-filament::icon :icon="Heroicon::Plus" class="sfk-app-add-icon" />
                <span class="fi-sr-only">テンプレートを追加する</span>
            </button>
        </div>

        <ul class="sfk-views-list">
            @foreach ($this->documentTemplates() as $template)
                <li class="sfk-views-row" wire:key="doc-template-{{ $template->id }}">
                    <span class="sfk-views-handle is-placeholder" aria-hidden="true"></span>
                    <x-filament::icon :icon="Heroicon::OutlinedDocumentText" class="sfk-views-type" />
                    <span class="sfk-views-name">{{ $template->name }}</span>
                    <span class="sfk-docs-meta">{{ $template->paperLabel() }}</span>
                    <span class="sfk-docs-meta">{{ $template->pageCount() }}ページ</span>
                    <span class="sfk-views-actions">
                        <a href="{{ $this->documentsUrl($template->id) }}" class="sfk-views-action">
                            <x-filament::icon :icon="Heroicon::OutlinedPencil" class="sfk-views-action-icon" />編集
                        </a>
                        <button type="button" class="sfk-views-action" wire:click="copyTemplate({{ $template->id }})">
                            <x-filament::icon :icon="Heroicon::OutlinedDocumentDuplicate" class="sfk-views-action-icon" />複製
                        </button>
                        {{ ($this->deleteTemplateAction)(['template' => $template->id]) }}
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="sfk-views" aria-labelledby="sfk-docs-outputs">
        <div class="sfk-views-head">
            <h2 id="sfk-docs-outputs" class="sfk-views-title">出力設定</h2>
            <button type="button" class="sfk-app-add sfk-views-add" wire:click="mountAction('output')" title="出力設定を追加する">
                <x-filament::icon :icon="Heroicon::Plus" class="sfk-app-add-icon" />
                <span class="fi-sr-only">出力設定を追加する</span>
            </button>
        </div>

        <ul
            class="sfk-views-list"
            x-data
            x-sortable
            data-sortable-animation-duration="150"
            x-on:end.stop="$wire.reorderOutputs($event.target.sortable.toArray())"
        >
            @foreach ($this->documentOutputs() as $output)
                <li class="sfk-views-row sfk-docs-output" x-sortable-item="{{ $output->id }}" wire:key="doc-output-{{ $output->id }}">
                    <button type="button" class="sfk-views-handle" x-sortable-handle title="ドラッグして並び替え">
                        <x-filament::icon :icon="Heroicon::Bars3" class="sfk-views-handle-icon" />
                        <span class="fi-sr-only">ドラッグして並び替え</span>
                    </button>
                    <x-filament::icon :icon="Heroicon::OutlinedPrinter" class="sfk-views-type" />
                    <span class="sfk-docs-output-body">
                        <span class="sfk-views-name">{{ $output->name }}</span>
                        <span class="sfk-docs-tags">
                            <span class="sfk-docs-tag">{{ $output->template->name }}</span>
                            @foreach ($output->placementLabels() as $placement)
                                <span class="sfk-docs-tag is-place">{{ $placement }}</span>
                            @endforeach
                            @if ($output->isForList())
                                <span class="sfk-docs-tag">{{ $output->mode->label() }}</span>
                            @endif
                            <span class="sfk-docs-tag">{{ $output->delivery->label() }}</span>
                            @if ($output->admin_only)
                                <span class="sfk-docs-tag is-admin">管理者のみ</span>
                            @endif
                        </span>
                    </span>
                    <span class="sfk-views-actions">
                        <button type="button" class="sfk-views-action" wire:click="mountAction('output', @js(['output' => $output->id]))">
                            <x-filament::icon :icon="Heroicon::OutlinedPencil" class="sfk-views-action-icon" />編集
                        </button>
                        {{ ($this->deleteOutputAction)(['output' => $output->id]) }}
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
