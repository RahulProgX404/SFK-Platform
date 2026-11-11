<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\AppSettings;

use App\Models\User;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Modules\Core\Access\UserType;
use Modules\Core\Domain\Apps\AccessSubject;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Domain\Apps\PermissionSubject;
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
     * Rows of an app's or a space's アクセス権 (`permissions`): 全員 (one), a 従業員カテゴリ or one employee, each with
     * 閲覧・追加・編集・削除・ファイル読み込み・ファイル書き出し. 個人 overrides 従業員カテゴリ, which overrides 全員.
     *
     * @param  Closure(Get): array<int, string>  $employeeOptions  employees a 個人 row may name
     */
    public static function permissionRows(Closure $employeeOptions): Repeater
    {
        return Repeater::make('permissions')
            ->hiddenLabel()
            ->defaultItems(0)
            ->reorderable(false)
            ->addActionLabel('権限を追加')
            ->itemLabel(fn (array $state): string => self::subjectLabel($state))
            ->extraAttributes(['class' => 'sfk-access'])
            ->columns(6)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $everyone = array_filter((array) $value, fn (mixed $item): bool => is_array($item)
                    && ($item['subject_type'] ?? null) === PermissionSubject::Everyone->value);
                if (count($everyone) > 1) {
                    $fail('「全員」の権限は1つだけ設定できます。');
                }
            })
            ->schema([
                Select::make('subject_type')
                    ->label('対象')
                    ->options(PermissionSubject::options())
                    ->default(PermissionSubject::Everyone->value)
                    ->required()
                    ->native(false)
                    ->live()
                    ->columnSpan(2),
                Select::make('employee_category_id')
                    ->live()
                    ->label('従業員カテゴリ')
                    ->options(fn (mixed $state): array => EmployeeCategory::options($state))
                    ->visible(fn (Get $get): bool => $get('subject_type') === PermissionSubject::Category->value)
                    ->required(fn (Get $get): bool => $get('subject_type') === PermissionSubject::Category->value)
                    ->distinct()
                    ->native(false)
                    ->columnSpan(4),
                Select::make('user_id')
                    ->live()
                    ->label('従業員')
                    ->options($employeeOptions)
                    ->visible(fn (Get $get): bool => $get('subject_type') === PermissionSubject::User->value)
                    ->required(fn (Get $get): bool => $get('subject_type') === PermissionSubject::User->value)
                    ->distinct()
                    ->searchable()
                    ->columnSpan(4),
                self::rights(self::abilityBoxes())->columnSpanFull(),
            ]);
    }

    /**
     * 閲覧 … ファイル書き出し and アプリ管理: ticking アプリ管理 ticks all the others, which stay ticked while it is
     * (GrantsAppAbilities saves them so too).
     *
     * @return list<Checkbox>
     */
    private static function abilityBoxes(): array
    {
        $manage = AppAbility::Manage->column();

        return array_map(fn (AppAbility $ability): Checkbox => $ability === AppAbility::Manage
            ? Checkbox::make($manage)
                ->label($ability->label())
                ->live()
                ->afterStateUpdated(function (bool $state, Set $set) use ($manage): void {
                    if ($state) {
                        foreach (AppAbility::columns() as $column) {
                            if ($column !== $manage) {
                                $set($column, true);
                            }
                        }
                    }
                })
            : Checkbox::make($ability->column())
                ->label($ability->label())
                ->disabled(fn (Get $get): bool => (bool) $get($manage))
                ->dehydrated(), AppAbility::cases());
    }

    /** @return array<int, string> every employee, by name */
    public static function employees(): array
    {
        return User::query()->where('user_type', UserType::Employee->value)->orderBy('name')->pluck('name', 'id')->all();
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
