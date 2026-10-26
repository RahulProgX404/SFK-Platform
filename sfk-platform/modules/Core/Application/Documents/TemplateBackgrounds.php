<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Application\Documents\Pdf\JapanesePdf;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DocumentTemplate;
use Throwable;

/**
 * Background PDFs of the 帳票テンプレート, kept on the local disk under documents/templates/<app id>/. An uploaded PDF the
 * renderer's free PDF reader cannot open (compressed cross-reference streams of PDF 1.5+, encryption without a
 * password) is rewritten by qpdf (Apache-2.0, part of the PHP image) into a classic PDF first; the drawing stays the
 * same.
 */
final class TemplateBackgrounds
{
    public const MAX_PAGES = 20;

    private const DIRECTORY = 'documents/templates';

    /**
     * Stores an uploaded PDF (a file on the local disk, e.g. from the upload field) for the app.
     *
     * @return array{path: string, pages: list<array{width: float, height: float}>}
     *
     * @throws DocumentSettingsInvalid when the file is no readable PDF or has too many pages
     */
    public function store(BusinessApp $app, string $uploadedPath): array
    {
        $disk = Storage::disk(DocumentTemplate::DISK);
        if (! $disk->exists($uploadedPath)) {
            throw DocumentSettingsInvalid::of('background', 'PDFファイルを選択してください。');
        }

        $path = self::DIRECTORY.'/'.$app->id.'/'.Str::uuid()->toString().'.pdf';
        $disk->makeDirectory(self::DIRECTORY.'/'.$app->id);
        $disk->move($uploadedPath, $path);

        try {
            $pages = $this->pages($disk->path($path));
        } catch (DocumentSettingsInvalid $invalid) {
            $disk->delete($path);

            throw $invalid;
        }

        return ['path' => $path, 'pages' => $pages];
    }

    /** A copy of a template's background for another template (null for a blank sheet). */
    public function copy(DocumentTemplate $template): ?string
    {
        if ($template->background_path === null) {
            return null;
        }

        $disk = Storage::disk(DocumentTemplate::DISK);
        $path = self::DIRECTORY.'/'.$template->business_app_id.'/'.Str::uuid()->toString().'.pdf';

        return $disk->copy($template->background_path, $path) ? $path : null;
    }

    /**
     * The page sizes (mm) of a PDF, rewriting it with qpdf when it cannot be read as it is.
     *
     * @return list<array{width: float, height: float}>
     */
    public function pages(string $file): array
    {
        $pages = $this->read($file);
        if ($pages === null && $this->rewrite($file)) {
            $pages = $this->read($file);
        }

        if ($pages === null) {
            throw DocumentSettingsInvalid::of('background', 'このPDFファイルは読み込めません。別の方法で保存したPDFをお試しください。');
        }
        if ($pages === []) {
            throw DocumentSettingsInvalid::of('background', 'PDFファイルにページがありません。');
        }
        if (count($pages) > self::MAX_PAGES) {
            throw DocumentSettingsInvalid::of('background', 'PDFファイルのページは'.self::MAX_PAGES.'ページまでです。');
        }

        return $pages;
    }

    /** @return list<array{width: float, height: float}>|null null when the PDF cannot be read */
    private function read(string $file): ?array
    {
        try {
            $pdf = new JapanesePdf;
            $pages = [];
            foreach ($pdf->importBackground($file) as $template) {
                $size = $pdf->getTemplateSize($template);
                $pages[] = ['width' => round((float) $size['width'], 2), 'height' => round((float) $size['height'], 2)];
            }

            return $pages;
        } catch (Throwable) {
            return null;
        }
    }

    /** Rewrites the PDF in place without object streams and encryption; false when qpdf is missing or fails. */
    private function rewrite(string $file): bool
    {
        $rewritten = $file.'.qpdf';

        try {
            // exit code 3 = written with warnings
            $result = Process::timeout(60)->run(['qpdf', '--object-streams=disable', '--decrypt', $file, $rewritten]);
            if (! in_array($result->exitCode(), [0, 3], true) || ! is_file($rewritten)) {
                return false;
            }

            return rename($rewritten, $file);
        } catch (Throwable) {
            return false;
        } finally {
            if (is_file($rewritten)) {
                @unlink($rewritten);
            }
        }
    }
}
