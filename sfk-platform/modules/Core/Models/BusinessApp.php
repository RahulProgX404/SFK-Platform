<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Apps\AppStatus;

/**
 * アプリ: one feature of the system (e.g. 01. 取引先マスタ, 04. 受注入力 — SYSTEM_BRAIN 03_APPS). Created by a
 * super admin as 開発中, outside any space; `code` links it to the screens that implement it
 * (Modules\Core\Filament\AppResource). `status` changes only through AppLifecycle.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $icon_text short text shown on the app's icon / shortcut tile (e.g. 受注)
 * @property AppStatus $status
 * @property int|null $space_id
 * @property int $sort_order
 * @property Carbon|null $list_views_seeded_at when the code-defined 一覧 were stored (ListViewService)
 * @property-read Space|null $space
 */
final class BusinessApp extends Model
{
    protected $fillable = ['code', 'name', 'description', 'icon_text', 'space_id', 'sort_order'];

    protected $attributes = [
        'status' => 'developing',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => AppStatus::class,
            'sort_order' => 'integer',
            'list_views_seeded_at' => 'datetime',
        ];
    }

    /** Name without the legacy numbering, e.g. 「04. 受注入力」 → 「受注入力」. */
    public function displayName(): string
    {
        return (string) preg_replace('/^\d+(?:-\d+)?\.\s*/u', '', $this->name);
    }

    /** Text on the app icon: icon_text, else the first two characters of the display name. */
    public function iconText(): string
    {
        return filled($this->icon_text) ? (string) $this->icon_text : mb_substr($this->displayName(), 0, 2);
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /** @return HasMany<AppPermission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(AppPermission::class);
    }

    /** @return HasMany<AppListView, $this> the app's 一覧, in dropdown order */
    public function listViews(): HasMany
    {
        return $this->hasMany(AppListView::class)->orderBy('sort_order')->orderBy('id');
    }
}
