<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Documents\OutputDelivery;
use Modules\Core\Domain\Documents\OutputMode;
use Modules\Core\Domain\Documents\OutputPlacement;
use Modules\Core\Domain\Documents\PaperSize;
use Modules\Core\Domain\Documents\TemplateLayout;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DocumentOutput;
use Modules\Core\Models\DocumentTemplate;

/**
 * アプリの設定 → 帳票出力 (administrators): the app's 帳票テンプレート (テンプレートを追加する with a background PDF or a
 * blank sheet, the designer's 保存, 背景PDFを差し替える, 複製, 削除) and 出力設定 (追加 / 編集 / 削除, drag order = button
 * order). Saves apply at once.
 */
final class DocumentSettings
{
    public function __construct(private readonly TemplateBackgrounds $backgrounds) {}

    /**
     * テンプレートを追加する: name, and either background (uploaded PDF on the local disk) + background_name, or paper.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTemplate(BusinessApp $app, array $data, ?int $userId): DocumentTemplate
    {
        $name = $this->templateName($app, $data['name'] ?? null);

        $background = null;
        $pages = [PaperSize::tryFrom((string) ($data['paper'] ?? ''))?->size() ?? PaperSize::A4Portrait->size()];
        if (filled($data['background'] ?? null)) {
            $background = $this->backgrounds->store($app, (string) $data['background']);
            $pages = $background['pages'];
        }

        return DocumentTemplate::query()->create([
            'business_app_id' => $app->id,
            'name' => $name,
            'background_path' => $background['path'] ?? null,
            'background_name' => $background !== null ? (self::fileName($data['background_name'] ?? null) ?? 'background.pdf') : null,
            'pages' => $pages,
            'layout' => ['elements' => []],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /** The designer's 保存: テンプレート名 and the placed elements. */
    public function saveDesign(DocumentTemplate $template, mixed $name, mixed $elements, ?int $userId): DocumentTemplate
    {
        $template->forceFill([
            'name' => $this->templateName($template->app, $name, $template->id),
            'layout' => ['elements' => TemplateLayout::normalize($elements, $template->pageCount())],
            'updated_by' => $userId,
        ])->save();

        return $template;
    }

    /** 背景PDFを差し替える: a new PDF (or a blank sheet); elements on pages it no longer has move to its last page. */
    public function replaceBackground(DocumentTemplate $template, ?string $uploadedPath, ?string $originalName, ?string $paper, ?int $userId): DocumentTemplate
    {
        $old = $template->background_path;

        if (filled($uploadedPath)) {
            $background = $this->backgrounds->store($template->app, (string) $uploadedPath);
            $template->forceFill([
                'background_path' => $background['path'],
                'background_name' => self::fileName($originalName) ?? 'background.pdf',
                'pages' => $background['pages'],
            ]);
        } else {
            $template->forceFill([
                'background_path' => null,
                'background_name' => null,
                'pages' => [PaperSize::tryFrom((string) $paper)?->size() ?? PaperSize::A4Portrait->size()],
            ]);
        }

        $template->forceFill([
            'layout' => ['elements' => TemplateLayout::normalize($template->elements(), count($template->pages))],
            'updated_by' => $userId,
        ])->save();

        if ($old !== null && $old !== $template->background_path) {
            Storage::disk(DocumentTemplate::DISK)->delete($old);
        }

        return $template;
    }

    /** 複製: 「<名前> - コピー」 with its own copy of the background. */
    public function copyTemplate(DocumentTemplate $template, ?int $userId): DocumentTemplate
    {
        $app = $template->app;
        $base = mb_substr($template->name, 0, 90).' - コピー';
        $name = $base;
        for ($number = 2; $app->documentTemplates()->where('name', $name)->exists(); $number++) {
            $name = $base.' ('.$number.')';
        }

        return DocumentTemplate::query()->create([
            'business_app_id' => $template->business_app_id,
            'name' => $name,
            'background_path' => $this->backgrounds->copy($template),
            'background_name' => $template->background_name,
            'pages' => $template->pages,
            'layout' => $template->layout,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /** 削除; a template still printed by a 出力設定 stays. */
    public function deleteTemplate(DocumentTemplate $template): void
    {
        $outputs = $template->outputs()->pluck('name')->all();
        if ($outputs !== []) {
            throw DocumentSettingsInvalid::of('template', '出力設定（'.implode('、', $outputs).'）で使われているため削除できません。');
        }

        $template->delete();
    }

    /**
     * 出力設定 → 保存 (new when $output is null): name, template, placements, mode, delivery, file_name, admin_only.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveOutput(BusinessApp $app, ?DocumentOutput $output, array $data): DocumentOutput
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'ボタン名を入力してください。';
        } elseif (mb_strlen($name) > 64) {
            $errors['name'] = 'ボタン名は64文字以内で入力してください。';
        } elseif ($app->documentOutputs()->where('name', $name)->when($output !== null, fn ($query) => $query->whereKeyNot($output?->id))->exists()) {
            $errors['name'] = '同じ名前の出力設定があります。';
        }

        $template = $app->documentTemplates()->whereKey((int) ($data['document_template_id'] ?? 0))->first();
        if ($template === null) {
            $errors['document_template_id'] = 'テンプレートを選択してください。';
        }

        $placements = array_values(array_unique(array_filter(
            array_map('strval', (array) ($data['placements'] ?? [])),
            fn (string $placement): bool => OutputPlacement::tryFrom($placement) !== null,
        )));
        if ($placements === []) {
            $errors['placements'] = '表示する場所を選択してください。';
        }

        $fileName = trim((string) ($data['file_name'] ?? ''));
        if (mb_strlen($fileName) > 200) {
            $errors['file_name'] = 'ファイル名は200文字以内で入力してください。';
        }

        if ($errors !== []) {
            throw new DocumentSettingsInvalid($errors);
        }

        $output ??= new DocumentOutput([
            'business_app_id' => $app->id,
            'sort_order' => (int) $app->documentOutputs()->max('sort_order') + 1,
        ]);
        $output->fill([
            'name' => $name,
            'document_template_id' => $template?->id,
            'placements' => $placements,
            'mode' => (OutputMode::tryFrom((string) ($data['mode'] ?? '')) ?? OutputMode::Merged)->value,
            'delivery' => (OutputDelivery::tryFrom((string) ($data['delivery'] ?? '')) ?? OutputDelivery::Open)->value,
            'file_name' => $fileName === '' ? null : $fileName,
            'admin_only' => (bool) ($data['admin_only'] ?? false),
        ])->save();

        return $output;
    }

    public function deleteOutput(DocumentOutput $output): void
    {
        $output->delete();
    }

    /** @param  list<int>  $ids  the app's 出力設定 in their new order */
    public function reorderOutputs(BusinessApp $app, array $ids): void
    {
        DB::transaction(function () use ($app, $ids): void {
            foreach ($ids as $position => $id) {
                $app->documentOutputs()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    private function templateName(BusinessApp $app, mixed $name, ?int $ignore = null): string
    {
        $name = trim(is_string($name) ? $name : '');

        $error = match (true) {
            $name === '' => 'テンプレート名を入力してください。',
            mb_strlen($name) > 100 => 'テンプレート名は100文字以内で入力してください。',
            $app->documentTemplates()->where('name', $name)->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore))->exists() => '同じ名前のテンプレートがあります。',
            default => null,
        };

        if ($error !== null) {
            throw DocumentSettingsInvalid::of('name', $error);
        }

        return $name;
    }

    private static function fileName(mixed $name): ?string
    {
        return is_string($name) && trim($name) !== '' ? mb_substr(basename(trim($name)), 0, 200) : null;
    }
}
