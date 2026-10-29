<?php

declare(strict_types=1);

namespace Modules\Core\Application\Legacy;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Core\Models\Position;

/**
 * The users of the legacy system's records: logins ({code: email, name: 「【07】小嶋　昌一」}) of 作成者 / 更新者 / user
 * fields, and employees of 02. 従業員マスタ by name or 作業員コード.
 *
 * A login is the user with its email; else the employee of the same name (taken over from 02 without an email: the
 * placeholder address becomes the login's); else a new employee with a random temporary password and no dashboards,
 * so nobody signs in before an administrator issues a password. 「【07】」 becomes the 営業担当者コード and a leading
 * 会長 / 社長 / 副社長 / 専務 / 常務 the 役職. Logins that are no email (e.g. Administrator) are nobody.
 */
final class LegacyUsers
{
    /** Address of employees taken over without a login (02 has no emails): <作業員コード>@employees.invalid. */
    public const PLACEHOLDER_DOMAIN = 'employees.invalid';

    private const TITLES = ['会長', '社長', '副社長', '専務', '常務'];

    /** @var array<string, User|null> login => user */
    private array $logins = [];

    /** @var array<string, int>|null normalized name => user id */
    private ?array $names = null;

    private int $created = 0;

    public function __construct(private readonly UserAccountService $accounts) {}

    /** @param  array{code: string, name: string}|null  $login */
    public function login(?array $login): ?User
    {
        if ($login === null) {
            return null;
        }

        $email = trim($login['code']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        if (array_key_exists($email, $this->logins)) {
            return $this->logins[$email];
        }

        [$code, $title, $name] = self::splitName($login['name']);
        $user = User::query()->where('email', $email)->first()
            ?? $this->takeOverEmployee($name, $email, $code)
            ?? $this->createEmployee($name, $email, $code, $title);

        return $this->logins[$email] = $user;
    }

    /** @param  array{code: string, name: string}|null  $login */
    public function id(?array $login): ?int
    {
        return $this->login($login)?->id;
    }

    /** The employee of a name (e.g. 作業者 of a daily report: 「池田　康啓」, 「【07】小嶋　昌一」). */
    public function named(?string $name): ?User
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $id = $this->names()[self::normalize(self::splitName($name)[2])] ?? null;

        return $id !== null ? User::query()->find($id) : null;
    }

    /** Users created so far (for the import's summary). */
    public function created(): int
    {
        return $this->created;
    }

    /** Forget what was looked up (after users changed outside, e.g. 02 was taken over). */
    public function forget(): void
    {
        $this->logins = [];
        $this->names = null;
    }

    public static function isPlaceholder(string $email): bool
    {
        return str_ends_with(strtolower($email), '@'.self::PLACEHOLDER_DOMAIN);
    }

    /**
     * 「【02】社長 白石　陽一」 → ['02', '社長', '白石　陽一'].
     *
     * @return array{0: string|null, 1: string|null, 2: string}
     */
    public static function splitName(string $name): array
    {
        $name = trim($name);
        $code = null;
        if (preg_match('/^【([^】]+)】\s*(.*)$/u', $name, $m) === 1) {
            $code = $m[1];
            $name = $m[2];
        }

        $title = null;
        foreach (self::TITLES as $candidate) {
            if (preg_match('/^'.$candidate.'[\s　]+(.+)$/u', $name, $m) === 1) {
                $title = $candidate;
                $name = $m[1];
                break;
            }
        }

        return [$code, $title, trim($name)];
    }

    /** A name compared without spaces and width differences: 「菊池 毅」 = 「菊池　毅」. */
    public static function normalize(string $name): string
    {
        return (string) preg_replace('/[\s\x{3000}]+/u', '', mb_convert_kana($name, 'asKV'));
    }

    /** The employee of the name taken over from 02 without a login gets the login's email. */
    private function takeOverEmployee(string $name, string $email, ?string $code): ?User
    {
        $user = $this->named($name);
        if ($user === null || ! self::isPlaceholder($user->email)) {
            return null;
        }

        $user->forceFill([
            'email' => $email,
            'sales_staff_code' => $user->sales_staff_code ?? $this->freeSalesStaffCode($code),
        ])->save();

        return $user;
    }

    private function createEmployee(string $name, string $email, ?string $code, ?string $title): User
    {
        $parts = preg_split('/[\s　]+/u', $name, 2) ?: [$name];
        $user = $this->accounts->create([
            'name' => $name,
            'email' => $email,
            'sales_staff_code' => $this->freeSalesStaffCode($code),
            'last_name' => $parts[0],
            'first_name' => $parts[1] ?? null,
            'position_id' => $title !== null ? Position::query()->firstOrCreate(['name' => $title])->id : null,
        ])->user;
        $this->created++;
        if ($this->names !== null) {
            $this->names[self::normalize($name)] ??= $user->id;
        }

        return $user;
    }

    private function freeSalesStaffCode(?string $code): ?string
    {
        return $code !== null && ! User::query()->where('sales_staff_code', $code)->exists() ? $code : null;
    }

    /** @return array<string, int> */
    private function names(): array
    {
        if ($this->names === null) {
            $this->names = [];
            foreach (DB::table('users')->orderBy('id')->get(['id', 'name']) as $user) {
                $this->names[self::normalize((string) $user->name)] ??= (int) $user->id;
            }
        }

        return $this->names;
    }
}
