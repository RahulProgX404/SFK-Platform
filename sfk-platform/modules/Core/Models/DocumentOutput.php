<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Documents\OutputDelivery;
use Modules\Core\Domain\Documents\OutputMode;
use Modules\Core\Domain\Documents\OutputPlacement;

/**
 * 出力設定 of an app (アプリの設定 → 帳票出力): a button printing a 帳票テンプレート, the places it is shown in
 * (OutputPlacement), how several records come out (1つのPDF / ZIP), whether the file opens in a tab or is downloaded,
 * its ファイル名 and whether only administrators see it (the legacy system showed the PrintCreator buttons of
 * 06. 売上入力 to one administrator only, SYSTEM_BRAIN 08_PRINTCREATOR / 11_PERMISSIONS).
 *
 * @property int $id
 * @property int $business_app_id
 * @property int $document_template_id
 * @property string $name
 * @property list<string> $placements OutputPlacement values
 * @property OutputMode $mode
 * @property OutputDelivery $delivery
 * @property string|null $file_name FileNamePattern
 * @property bool $admin_only
 * @property int $sort_order
 * @property-read BusinessApp $app
 * @property-read DocumentTemplate $template
 */
final class DocumentOutput extends Model
{
    /** Most records put out by one click (the legacy limit, PrintCreator sample: 500件). */
    public const MAX_RECORDS = 500;

    protected $fillable = ['business_app_id', 'document_template_id', 'name', 'placements', 'mode', 'delivery', 'file_name', 'admin_only', 'sort_order'];

    protected $attributes = [
        'mode' => 'merged',
        'delivery' => 'open',
        'admin_only' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'placements' => 'array',
            'mode' => OutputMode::class,
            'delivery' => OutputDelivery::class,
            'admin_only' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<BusinessApp, $this> */
    public function app(): BelongsTo
    {
        return $this->belongsTo(BusinessApp::class, 'business_app_id');
    }

    /** @return BelongsTo<DocumentTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function isShownAt(OutputPlacement $placement): bool
    {
        return in_array($placement->value, $this->placements, true);
    }

    /** Shown on a list (several records) / on a record. */
    public function isForList(): bool
    {
        foreach ($this->placements as $placement) {
            if (OutputPlacement::tryFrom($placement)?->isList() === true) {
                return true;
            }
        }

        return false;
    }

    public function isForRecord(): bool
    {
        foreach ($this->placements as $placement) {
            if (OutputPlacement::tryFrom($placement)?->isList() === false) {
                return true;
            }
        }

        return false;
    }

    /** 管理者のみ: the button is for administrators. */
    public function isAvailableTo(User $user): bool
    {
        return ! $this->admin_only || $user->user_type->isAdministrator();
    }

    /** @return list<string> labels of the places it is shown in */
    public function placementLabels(): array
    {
        return array_values(array_filter(array_map(fn (string $placement): ?string => OutputPlacement::tryFrom($placement)?->label(), $this->placements)));
    }
}
