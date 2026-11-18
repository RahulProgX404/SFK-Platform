{{-- メッセージ管理 (Modules\Messaging\Filament\Pages\MessagingAdminPage): 設定 · チャンネル · 統計 · 監査ログ — figures and names, never message texts --}}
<x-filament-panels::page>
    <style>
        .sfk-madmin { display: flex; flex-direction: column; gap: var(--sfk-space-5); padding: var(--sfk-space-5) var(--sfk-gutter) var(--sfk-space-8); }
        .sfk-madmin-tabs { display: flex; gap: var(--sfk-space-1); border-bottom: 1px solid var(--sfk-line); }
        .sfk-madmin-tabs button { height: 40px; padding: 0 var(--sfk-space-4); border-bottom: 2px solid transparent; color: var(--sfk-muted); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-medium); }
        .sfk-madmin-tabs button.is-active { border-bottom-color: var(--sfk-primary); color: var(--sfk-primary); }
        .sfk-madmin-card { padding: var(--sfk-space-5); border-radius: var(--sfk-radius-lg); background: var(--sfk-surface); box-shadow: var(--sfk-ring), var(--sfk-shadow); }
        .sfk-madmin-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--sfk-space-4) var(--sfk-space-6); }
        .sfk-madmin-field { display: flex; flex-direction: column; gap: 6px; }
        .sfk-madmin-field > span { color: var(--kt-label); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); }
        .sfk-madmin-field input[type=number], .sfk-madmin-field select { height: var(--sfk-control); padding: 0 10px; border: 1px solid var(--sfk-line-strong); border-radius: var(--sfk-radius); background: var(--sfk-surface); font-size: var(--sfk-fs-body); }
        .sfk-madmin-field small { color: var(--sfk-danger-text); font-size: var(--sfk-fs-small); }
        .sfk-madmin-check { display: inline-flex; align-items: center; gap: 8px; min-height: var(--sfk-control); }
        .sfk-madmin-actions { display: flex; justify-content: flex-end; margin-top: var(--sfk-space-5); }
        .sfk-madmin-table { width: 100%; border-collapse: collapse; font-size: var(--sfk-fs-body); }
        .sfk-madmin-table th { padding: 8px 12px; background: var(--sfk-list-head); color: #fff; font-weight: var(--sfk-fw-semibold); text-align: left; white-space: nowrap; }
        .sfk-madmin-table td { padding: 8px 12px; border-bottom: 1px solid var(--sfk-line-soft); }
        .sfk-madmin-table tr.is-archived td { color: var(--sfk-muted); }
        .sfk-madmin-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--sfk-space-4); }
        .sfk-madmin-stat span { display: block; color: var(--sfk-muted); font-size: var(--sfk-fs-small); }
        .sfk-madmin-stat strong { font-size: var(--sfk-fs-figure); font-variant-numeric: tabular-nums; }
        .sfk-madmin-bar { display: grid; grid-template-columns: 160px 1fr 60px; align-items: center; gap: var(--sfk-space-3); padding: 4px 0; font-size: var(--sfk-fs-small); }
        .sfk-madmin-bar i { display: block; height: 8px; border-radius: 9999px; background: var(--sfk-primary); }
        .sfk-madmin-bar b { text-align: right; font-variant-numeric: tabular-nums; font-weight: var(--sfk-fw-semibold); }
    </style>

    <div class="sfk-madmin">
        <nav class="sfk-madmin-tabs">
            @foreach (['settings' => '設定', 'channels' => 'チャンネル', 'stats' => '統計', 'audit' => '監査ログ'] as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" @class(['is-active' => $tab === $key])>{{ $label }}</button>
            @endforeach
        </nav>

        @if ($tab === 'settings')
            <form class="sfk-madmin-card" wire:submit="save">
                <div class="sfk-madmin-form">
                    @foreach ($definitions as $key => [$path, $type, $label])
                        <label class="sfk-madmin-field">
                            <span>{{ $label }}</span>
                            @if ($type === 'bool')
                                <span class="sfk-madmin-check"><input type="checkbox" class="sfk-ui-check" wire:model="settings.{{ $key }}"> 有効</span>
                            @elseif ($type === 'who')
                                <select wire:model="settings.{{ $key }}">
                                    <option value="everyone">全員</option>
                                    <option value="administrators">管理者のみ</option>
                                </select>
                            @else
                                <input type="number" min="0" wire:model="settings.{{ $key }}" placeholder="{{ in_array($type, ['minutes', 'days'], true) ? '無期限' : '' }}">
                            @endif
                            @error('settings.'.$key) <small>{{ $message }}</small> @enderror
                        </label>
                    @endforeach
                </div>
                <div class="sfk-madmin-actions">
                    <x-filament::button type="submit">保存</x-filament::button>
                </div>
            </form>
        @elseif ($tab === 'channels')
            <div class="sfk-madmin-card" style="padding: 0; overflow: auto">
                <table class="sfk-madmin-table">
                    <thead><tr><th>チャンネル</th><th>種類</th><th>公開</th><th>投稿</th><th>メンバー</th><th>作成者</th><th>最新</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($channels as $channel)
                            <tr @class(['is-archived' => $channel['archived']]) wire:key="channel-{{ $channel['id'] }}">
                                <td>{{ $channel['name'] }}{{ $channel['archived'] ? '（アーカイブ）' : '' }}</td>
                                <td>{{ $channel['kind'] }}</td>
                                <td>{{ $channel['public'] ? '公開' : '招待のみ' }}</td>
                                <td>{{ $channel['readonly'] ? '管理者のみ' : '全メンバー' }}</td>
                                <td style="text-align: right">{{ $channel['members'] }}</td>
                                <td>{{ $channel['by'] }}</td>
                                <td>{{ $channel['last'] }}</td>
                                <td style="text-align: right; white-space: nowrap">
                                    <x-filament::button size="sm" color="gray" wire:click="archive({{ $channel['id'] }}, {{ $channel['archived'] ? 'false' : 'true' }})">{{ $channel['archived'] ? '再開' : 'アーカイブ' }}</x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif ($tab === 'stats')
            <div class="sfk-madmin-stats">
                @foreach ([['メッセージ（全期間）', number_format($stats['messages'])], ['メッセージ（30日）', number_format($stats['messages30'])], ['活動中のトーク（30日）', number_format($stats['conversations30'])], ['利用者（30日）', number_format($stats['users30']).'人'], ['グループ', number_format($stats['groups'])], ['チャンネル', number_format($stats['channels'])], ['添付ファイル容量', number_format($stats['storage'] / 1048576, 1).' MB'], ['未読アナウンス', number_format($stats['unreadAnnouncements'])]] as [$label, $value])
                    <div class="sfk-madmin-card sfk-madmin-stat"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                @endforeach
            </div>
            <div class="sfk-madmin-card">
                <h3 style="margin: 0 0 var(--sfk-space-3); font-size: var(--sfk-fs-heading)">従業員カテゴリ別のメッセージ（30日）</h3>
                @php($max = max(1, ...array_values($stats['byDepartment'] ?: [1])))
                @foreach ($stats['byDepartment'] as $name => $count)
                    <div class="sfk-madmin-bar"><span>{{ $name }}</span><i style="width: {{ max(2, (int) round($count / $max * 100)) }}%"></i><b>{{ number_format($count) }}</b></div>
                @endforeach
            </div>
        @else
            <div class="sfk-madmin-card" style="padding: 0; overflow: auto">
                <table class="sfk-madmin-table">
                    <thead><tr><th>日時</th><th>操作者</th><th>操作</th><th>トーク</th><th>内容</th></tr></thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td style="white-space: nowrap">{{ $log['at'] }}</td>
                                <td>{{ $log['user'] }}</td>
                                <td>{{ $labels[$log['action']] ?? $log['action'] }}</td>
                                <td>{{ $log['conversation'] }}</td>
                                <td>{{ $log['details'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
