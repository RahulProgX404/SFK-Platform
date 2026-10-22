<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Apps\AccessSubject;
use Modules\Core\Models\Concerns\HasAccessSubject;

/**
 * An entry of a レコードのアクセス権 rule: who (作成者 / 個人 / 従業員カテゴリ / 全員) may 閲覧 / 編集 / 削除 the records of
 * the rule. Like the legacy system, 編集 and 削除 need 閲覧: setting either sets 閲覧, removing 閲覧 removes them.
 *
 * @property int $id
 * @property int $app_record_rule_id
 * @property int $sort_order
 * @property bool $can_view
 * @property bool $can_edit
 * @property bool $can_delete
 */
final class AppRecordRuleEntry extends Model
{
    use HasAccessSubject;

    protected $fillable = ['sort_order', 'subject_type', 'user_id', 'employee_category_id', 'can_view', 'can_edit', 'can_delete'];

    protected function casts(): array
    {
        return [
            'subject_type' => AccessSubject::class,
            'sort_order' => 'integer',
            'can_view' => 'boolean',
            'can_edit' => 'boolean',
            'can_delete' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $entry): void {
            if ($entry->can_edit || $entry->can_delete) {
                $entry->can_view = true;
            }
        });
    }

    /** @return BelongsTo<AppRecordRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AppRecordRule::class, 'app_record_rule_id');
    }
}
