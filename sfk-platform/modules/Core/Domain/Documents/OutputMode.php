<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Documents;

/** How several records are put out by one click (a single record is always one PDF). */
enum OutputMode: string
{
    /** 1つのPDFにまとめる: the documents one after the other */
    case Merged = 'merged';
    /** レコードごとのPDF（ZIP）: one file per record, named by the file name of the 出力設定 */
    case Zip = 'zip';

    public function label(): string
    {
        return match ($this) {
            self::Merged => '1つのPDFにまとめる',
            self::Zip => 'レコードごとのPDF（ZIP）',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return [self::Merged->value => self::Merged->label(), self::Zip->value => self::Zip->label()];
    }
}
