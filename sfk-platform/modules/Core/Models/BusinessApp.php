<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Apps\AppStatus;

/**
 * アプリ: one feature of the system (e.g. 01. 取引先マスタ, 04. 受注入力 — SYSTEM_BRAIN 03_APPS). Created by a
 * super admin as 開発中, outside any space, then placed in one or more spaces (of one or more dashboards); `code` links it to the screens that implement it
 * (Modules\Core\Filament\AppResource). `status` changes only through AppLifecycle.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $icon_text short text shown on the app's icon / shortcut tile (e.g. 受注)
 * @property AppStatus $status
 * @property int $sort_order
 * @property Carbon|null $list_views_seeded_at when the code-defined 一覧 were stored (ListViewService)
 * @property bool $record_duplicate_enabled その他の設定: 「レコードの再利用」 on the record page
 * @property bool $record_history_enabled その他の設定: 「変更履歴」 of the records
 * @property bool $inline_edit_enabled その他の設定: インライン編集 (pencil and × on the list rows)
 * @property bool $record_comments_enabled その他の設定: コメント機能 on the record page
 * @property bool $bulk_delete_enabled その他の設定: 一括削除 of the listed records
 * @property-read Collection<int, Space> $spaces
 */
final class BusinessApp extends Model
{
    /** その他の設定 switches (アプリの設定 → 設定). */
    public const SETTINGS = [
        'inline_edit_enabled', 'record_comments_enabled', 'record_duplicate_enabled', 'record_history_enabled', 'bulk_delete_enabled',
    ];

    protected $fillable = ['code', 'name', 'description', 'icon_text', 'sort_order'];

    protected $attributes = [
        'status' => 'developing',
        'sort_order' => 0,
        'record_duplicate_enabled' => true,
        'record_history_enabled' => true,
        'inline_edit_enabled' => true,
        'record_comments_enabled' => true,
        'bulk_delete_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => AppStatus::class,
            'sort_order' => 'integer',
            'list_views_seeded_at' => 'datetime',
            'record_duplicate_enabled' => 'boolean',
            'record_history_enabled' => 'boolean',
            'inline_edit_enabled' => 'boolean',
            'record_comments_enabled' => 'boolean',
            'bulk_delete_enabled' => 'boolean',
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

    /** @return BelongsToMany<Space, $this> the spaces the app is placed in, in their order */
    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class)->withTimestamps()->orderBy('spaces.sort_order')->orderBy('spaces.id');
    }

    /**
     * The space the app is shown under on a dashboard (sidebar group, breadcrumb): the first of its spaces there the
     * user is a member of, else the first of its spaces there; null when it is in none of that dashboard's spaces.
     */
    public function spaceOn(string $panelId, ?User $user): ?Space
    {
        $spaces = $this->spaces->where('panel_id', $panelId)->values();
        $member = $user === null ? null : $spaces->first(fn (Space $space): bool => $space->users()->whereKey($user->id)->exists());

        return $member ?? $spaces->first();
    }

    /** @return HasMany<AppPermission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(AppPermission::class);
    }

    /** @return HasMany<AppRecordRule, $this> the レコードのアクセス権 rules, in priority order */
    public function recordRules(): HasMany
    {
        return $this->hasMany(AppRecordRule::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<AppFieldPermission, $this> the フィールドのアクセス権 entries, by field in order */
    public function fieldPermissions(): HasMany
    {
        return $this->hasMany(AppFieldPermission::class)->orderBy('field')->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<AppListView, $this> the app's 一覧, in dropdown order */
    public function listViews(): HasMany
    {
        return $this->hasMany(AppListView::class)->orderBy('sort_order')->orderBy('id');
    }
}
