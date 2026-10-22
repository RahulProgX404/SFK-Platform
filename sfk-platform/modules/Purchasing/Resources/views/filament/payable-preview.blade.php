{{--
    「(新) 前月未作成」 result of 23. 買掛管理 (Modules\Purchasing\Filament\Resources\Payables\Pages\ListPayables::payablePreviewResultAction(),
    PayablePreview): the legacy 「計算結果（テスト表示）」 — what 前月未作成一括作成 would write, nothing saved.
    $month: YearMonth, $rows: PayablePreview::forMonth().
--}}
@php
    use Modules\Core\Filament\Support\AppFormat;
    use Modules\Purchasing\Application\Payables\PayablePreview;
    use Modules\Purchasing\Filament\Resources\Payables\PayableLayout;

    $counts = array_count_values(array_column($rows, 'action'));
    $shownCounts = array_filter(
        [PayablePreview::CREATE, PayablePreview::CREATE_PAYMENT_ONLY, PayablePreview::UPDATE_PAYMENT, PayablePreview::UPDATE, PayablePreview::DELETE],
        fn(string $action, int $index): bool => $index < 3 || ($counts[$action] ?? 0) > 0,
        ARRAY_FILTER_USE_BOTH,
    );
@endphp

<div class="sfk-preview">
    <p class="sfk-notice" role="note">
        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="sfk-notice-icon" />
        これはテスト表示です。実際のレコードは作成・更新されていません。
    </p>

    <ul class="sfk-preview-counts">
        @foreach ($shownCounts as $action)
            <li>{{ $action }} <strong>{{ number_format($counts[$action] ?? 0) }}</strong> 件</li>
        @endforeach
    </ul>

    <div class="sfk-data-table-wrap">
        <table class="sfk-data-table">
            <thead>
                <tr>
                    <th>処理</th>
                    <th>{{ PayableLayout::label('year_month') }}</th>
                    <th>{{ PayableLayout::label('supplier_id') }}</th>
                    <th>仕入カテゴリ</th>
                    @foreach (PayablePreview::FIGURES as $figure)
                        <th class="is-number">{{ PayableLayout::label($figure) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['action'] }}</td>
                        <td>{{ $month->label() }}</td>
                        <td>{{ $row['supplier'] }}</td>
                        <td>{{ $row['categories'] }}</td>
                        @foreach (PayablePreview::FIGURES as $figure)
                            <td class="is-number">{{ AppFormat::yen($row['figures'][$figure]) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
