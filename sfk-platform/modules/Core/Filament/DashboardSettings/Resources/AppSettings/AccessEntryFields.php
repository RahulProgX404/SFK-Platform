<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\AppSettings;

use App\Models\User;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Modules\Core\Access\UserType;
use Modules\Core\Domain\Apps\AccessSubject;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Models\EmployeeCategory;

/**
 * The entries of a レコード / フィールド アクセス権 setting (who, then what), read top to bottom: drag to reorder.
 */
final class AccessEntryFields
{
    /** レコード: 作成者 / 個人 / 従業員カテゴリ / 全員 with 閲覧・編集・削除. */
    public static function forRecords(): Repeater
    {
        return self::repeater(true, [
            self::rights([
                Checkbox::make('can_view')->label('閲覧'),
                Checkbox::make('can_edit')->label('編集'),
                Checkbox::make('can_delete')->label('削除'),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * The rights of an entry as one row of chips (core::filament.ui, sfk-access-rights).
     *
     * @param  list<Component>  $rights
     */
    public static function rights(array $rights): Group
    {
        return Group::make($rights)->extraAttributes(['class' => 'sfk-access-rights']);
    }

    /** フィールド: 個人 / 従業員カテゴリ / 全員 with 編集 / 閲覧 / なし. */
    public static function forFields(): Repeater
    {
        return self::repeater(false, [
            Radio::make('access')
                ->label('権限')
                ->options(FieldAccessLevel::options())
                ->default(FieldAccessLevel::Edit->value)
                ->inline()
                ->required()
                ->columnSpan(3),
        ]);
    }

    /**
     * Header of an entry (also of a row of the アプリ permissions): who it is for, e.g. 「従業員カテゴリ：営業」.
     *
     * @param  array<string, mixed>  $state
     */
    public static function subjectLabel(array $state): string
    {
        $subject = AccessSubject::tryFrom((string) ($state['subject_type'] ?? ''));

        return match ($subject) {
            AccessSubject::User => '個人：'.(filled($state['user_id'] ?? null) ? (User::query()->whereKey($state['user_id'])->value('name') ?? '—') : '（未選択）'),
            AccessSubject::Category => '従業員カテゴリ：'.(filled($state['employee_category_id'] ?? null) ? (EmployeeCategory::query()->whereKey($state['employee_category_id'])->value('name') ?? '—') : '（未選択）'),
            null => '対象',
            default => $subject->label(),
        };
    }

    /** @param  list<Component>  $rights */
    private static function repeater(bool $withCreator, array $rights): Repeater
    {
        return Repeater::make('entries')
            ->label('対象')
            ->defaultItems(1)
            ->reorderable()
            ->addActionLabel('対象を追加')
            ->itemLabel(fn (array $state): string => self::subjectLabel($state))
            ->extraAttributes(['class' => 'sfk-access sfk-access-entries'])
            ->columns(12)
            ->schema([
                Select::make('subject_type')
                    ->placeholder('-----')
                    ->label('対象')
                    ->options(AccessSubject::options($withCreator))
                    ->default(AccessSubject::Everyone->value)
                    ->required()
                    ->native(false)
                    ->live()
                    ->columnSpan(3),
                Select::make('user_id')
                    ->live()
                    ->placeholder('-----')
                    ->label('従業員')
                    ->options(fn (): array => User::query()->where('user_type', UserType::Employee->value)->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (Get $get): bool => $get('subject_type') === AccessSubject::User->value)
                    ->required(fn (Get $get): bool => $get('subject_type') === AccessSubject::User->value)
                    ->searchable()
                    ->columnSpan(5),
                Select::make('employee_category_id')
                    ->live()
                    ->placeholder('-----')
                    ->label('従業員カテゴリ')
                    ->options(fn (mixed $state): array => EmployeeCategory::options($state))
                    ->visible(fn (Get $get): bool => $get('subject_type') === AccessSubject::Category->value)
                    ->required(fn (Get $get): bool => $get('subject_type') === AccessSubject::Category->value)
                    ->native(false)
                    ->columnSpan(5),
                ...$rights,
            ]);
    }
}
