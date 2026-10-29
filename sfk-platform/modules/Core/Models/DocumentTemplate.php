<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 帳票テンプレート of an app (アプリの設定 → 帳票出力): a background PDF uploaded by an administrator — or a blank sheet —
 * with the record's fields placed on its pages in the designer (Modules\Core\Domain\Documents\TemplateLayout).
 *
 * @property int $id
 * @property int $business_app_id
 * @property string $name
 * @property string|null $background_path on the uploads disk (Application\Documents\TemplateBackgrounds); null = 白紙
 * @property string|null $background_name file name of the uploaded PDF
 * @property list<array{width: float, height: float}> $pages sizes of the pages in mm
 * @property array{elements?: list<array<string, mixed>>} $layout
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BusinessApp $app
 */
final class DocumentTemplate extends Model
{
    protected $fillable = ['business_app_id', 'name', 'background_path', 'background_name', 'pages', 'layout', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'pages' => 'array',
            'layout' => 'array',
        ];
    }

    /** @return BelongsTo<BusinessApp, $this> */
    public function app(): BelongsTo
    {
        return $this->belongsTo(BusinessApp::class, 'business_app_id');
    }

    /** @return HasMany<DocumentOutput, $this> the 出力設定 printing it */
    public function outputs(): HasMany
    {
        return $this->hasMany(DocumentOutput::class);
    }

    /** @return list<array<string, mixed>> */
    public function elements(): array
    {
        return $this->layout['elements'] ?? [];
    }

    public function pageCount(): int
    {
        return max(1, count($this->pages));
    }

    /** 用紙 as listed: the PDF's name, else the size of the blank sheet. */
    public function paperLabel(): string
    {
        if (filled($this->background_name)) {
            return (string) $this->background_name;
        }

        $page = $this->pages[0] ?? ['width' => 210, 'height' => 297];

        return '白紙 '.rtrim(rtrim(number_format((float) $page['width'], 1), '0'), '.').' × '.rtrim(rtrim(number_format((float) $page['height'], 1), '0'), '.').' mm';
    }
}
