<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Views\ListViewType;

/**
 * A 一覧 of an app (アプリの設定 → 一覧, or 保存 in the 絞り込む dialog): its 表示形式, columns or calendar fields,
 * 絞り込み and ソート. Shown in the view dropdown by sort_order, before the built-in （すべて）; the first is the default.
 *
 * @property int $id
 * @property int $business_app_id
 * @property string $name 一覧名
 * @property ListViewType $type 表形式 / カレンダー形式
 * @property list<string>|null $columns 表形式: the columns, in order
 * @property string|null $date_field カレンダー形式: 日付
 * @property string|null $title_field カレンダー形式: タイトル
 * @property array<string, mixed> $filter Modules\Core\Domain\Filters\RecordFilter::toArray()
 * @property int $sort_order
 * @property int|null $created_by
 * @property-read BusinessApp $app
 */
final class AppListView extends Model
{
    protected $fillable = ['business_app_id', 'name', 'type', 'columns', 'date_field', 'title_field', 'filter', 'sort_order', 'created_by'];

    protected $attributes = [
        'type' => 'table',
    ];

    protected function casts(): array
    {
        return [
            'type' => ListViewType::class,
            'columns' => 'array',
            'filter' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<BusinessApp, $this> */
    public function app(): BelongsTo
    {
        return $this->belongsTo(BusinessApp::class, 'business_app_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
