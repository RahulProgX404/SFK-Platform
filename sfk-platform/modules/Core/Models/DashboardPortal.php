<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Home page (ポータル) content of one dashboard: the お知らせ HTML (from the rich editor) and the ordered
 * アプリショートカット. Edited by administrators on the page itself.
 *
 * @property int $id
 * @property string $panel_id
 * @property string|null $notice
 * @property list<int>|null $shortcut_app_ids
 */
final class DashboardPortal extends Model
{
    protected $fillable = ['panel_id', 'notice', 'shortcut_app_ids'];

    protected function casts(): array
    {
        return [
            'shortcut_app_ids' => 'array',
        ];
    }

    public static function for(string $panelId): self
    {
        return self::query()->firstOrNew(['panel_id' => $panelId]);
    }
}
