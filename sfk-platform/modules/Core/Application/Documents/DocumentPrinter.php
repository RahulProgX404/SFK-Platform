<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

use App\Models\User;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Documents\FileNamePattern;
use Modules\Core\Domain\Documents\OutputMode;
use Modules\Core\Models\DocumentOutput;
use Modules\Core\Models\DocumentTemplate;
use RuntimeException;
use ZipArchive;

/**
 * Puts out the 帳票 of a 出力設定 (replaces the legacy PrintCreator output, SYSTEM_BRAIN 08_PRINTCREATOR / I-01): one
 * record as a PDF, several records as one PDF or — レコードごとのPDF（ZIP） — a ZIP of one PDF per record. Files are
 * named by the 出力設定's ファイル名 (fields of the record in braces), else by the button name and the record's title.
 */
final class DocumentPrinter
{
    public function __construct(private readonly DocumentRenderer $renderer) {}

    public function single(DocumentOutput $output, DocumentSource $source, Model $record, ?User $user): PrintedFile
    {
        $data = $source->data($record);

        return new PrintedFile(
            $this->fileName($output, $data).'.pdf',
            'application/pdf',
            $this->renderer->render($output->template, [$data], $user?->name),
        );
    }

    /** @param  iterable<Model>  $records */
    public function many(DocumentOutput $output, DocumentSource $source, iterable $records, ?User $user): PrintedFile
    {
        // one record's data at a time: a long list is not held in memory twice
        $data = (function () use ($records, $source): Generator {
            foreach ($records as $record) {
                yield $source->data($record);
            }
        })();

        if ($output->mode !== OutputMode::Zip) {
            return new PrintedFile(
                (FileNamePattern::clean($output->name.'_'.CarbonImmutable::now()->format('Ymd_His')) ?? 'documents').'.pdf',
                'application/pdf',
                $this->renderer->render($output->template, $data, $user?->name),
            );
        }

        return $this->zip($output, $output->template, $data, $user);
    }

    /**
     * A preview of a template: the record's data, else every field showing its label.
     *
     * @param  list<array<string, mixed>>|null  $elements  the designer's unsaved layout
     */
    public function preview(DocumentTemplate $template, DocumentSource $source, ?Model $record, ?User $user, ?array $elements = null): PrintedFile
    {
        $data = $record !== null ? $source->data($record) : $source->sample();

        return new PrintedFile(
            (FileNamePattern::clean($template->name) ?? 'preview').'.pdf',
            'application/pdf',
            $this->renderer->render($template, [$data], $user?->name, $elements),
        );
    }

    /** @param  iterable<RecordData>  $records */
    private function zip(DocumentOutput $output, DocumentTemplate $template, iterable $records, ?User $user): PrintedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sfk-zip');
        if ($path === false) {
            throw new RuntimeException('No temporary file for the ZIP.');
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $names = [];
        foreach ($records as $record) {
            $name = $this->fileName($output, $record);
            $unique = $name;
            for ($number = 2; isset($names[$unique]); $number++) {
                $unique = $name.'_'.$number;
            }
            $names[$unique] = true;
            $zip->addFromString($unique.'.pdf', $this->renderer->render($template, [$record], $user?->name));
        }
        if ($names === []) {
            $zip->addFromString('.empty', '');
        }
        $zip->close();

        $contents = (string) file_get_contents($path);
        @unlink($path);

        return new PrintedFile(
            (FileNamePattern::clean($output->name.'_'.CarbonImmutable::now()->format('Ymd_His')) ?? 'documents').'.zip',
            'application/zip',
            $contents,
        );
    }

    /** The file name of a record's document, without extension. */
    private function fileName(DocumentOutput $output, RecordData $record): string
    {
        $name = filled($output->file_name)
            ? FileNamePattern::render((string) $output->file_name, fn (string $field): string => $record->value($field)->text)
            : null;

        return $name ?? FileNamePattern::clean($output->name.'_'.($record->title !== '' ? $record->title : $record->key)) ?? 'document';
    }
}
