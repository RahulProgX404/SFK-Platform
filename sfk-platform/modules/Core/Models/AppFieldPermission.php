<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Apps\AccessSubject;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Models\Concerns\HasAccessSubject;

/**
 * An entry of the フィールドのアクセス権 of an app: for one field (an attribute of the app's records, as in its list
 * columns), who (個人 / 従業員カテゴリ / 全員) may 編集 / 閲覧 it or see nothing. The entries of a field are read in
 * `sort_order`; the first that applies to the user decides (Modules\Core\Access\FieldAccess).
 *
 * @property int $id
 * @property int $business_app_id
 * @property string $field
 * @property int $sort_order
 * @property FieldAccessLevel $access
 */
final class AppFieldPermission extends Model
{
    use HasAccessSubject;

    protected $fillable = ['field', 'sort_order', 'subject_type', 'user_id', 'employee_category_id', 'access'];

    protected function casts(): array
    {
        return [
            'subject_type' => AccessSubject::class,
            'access' => FieldAccessLevel::class,
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<BusinessApp, $this> */
    public function app(): BelongsTo
    {
        return $this->belongsTo(BusinessApp::class, 'business_app_id');
    }
}
