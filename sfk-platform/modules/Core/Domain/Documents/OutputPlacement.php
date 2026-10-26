<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Documents;

/**
 * Where the button of a 出力設定 is shown (the places the legacy PrintCreator loader used, SYSTEM_BRAIN 08_PRINTCREATOR:
 * the menu of the record and list screens, and the space above them): on a record it puts out that record, on the
 * list the records as filtered and searched, or the checked rows.
 */
enum OutputPlacement: string
{
    /** レコード詳細画面のメニュー: a button before 編集 */
    case RecordMenu = 'record_menu';
    /** レコード詳細画面の上部スペース: 帳票 drop-down + 出力 under the toolbar */
    case RecordSpace = 'record_space';
    /** 一覧画面のメニュー: a button right of 一覧 / 絞り込む, for the listed records */
    case ListMenu = 'list_menu';
    /** 一覧画面のメニュー: a button for the rows checked in the list */
    case ListChecked = 'list_checked';
    /** 一覧画面の上部スペース: 帳票 drop-down + 出力 under the toolbar, for the listed records */
    case ListSpace = 'list_space';

    public function label(): string
    {
        return match ($this) {
            self::RecordMenu => 'レコード詳細画面のメニュー',
            self::RecordSpace => 'レコード詳細画面の上部スペース',
            self::ListMenu => '一覧画面のメニュー（絞り込んだレコード）',
            self::ListChecked => '一覧画面のメニュー（チェックしたレコード）',
            self::ListSpace => '一覧画面の上部スペース（絞り込んだレコード）',
        };
    }

    /** On the list screen (several records), else on the record screen (one record). */
    public function isList(): bool
    {
        return in_array($this, [self::ListMenu, self::ListChecked, self::ListSpace], true);
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
