<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Core\Domain\Documents\OutputPlacement;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DocumentOutput;
use WeakMap;

/**
 * The 出力設定 of an app a user gets as buttons at a place (the app screens ask several times per page; read once
 * per request). 管理者のみ ones are left out for employees.
 */
final class AppDocuments
{
    /** @var WeakMap<Request, array<int, Collection<int, DocumentOutput>>>|null app id => outputs, per request */
    private static ?WeakMap $perRequest = null;

    /** @return list<DocumentOutput> */
    public static function at(?BusinessApp $app, ?User $user, OutputPlacement $placement): array
    {
        if ($app === null || $user === null) {
            return [];
        }

        return array_values(self::of($app)
            ->filter(fn (DocumentOutput $output): bool => $output->isShownAt($placement) && $output->isAvailableTo($user))
            ->all());
    }

    /** @return Collection<int, DocumentOutput> the app's outputs with their templates, in button order */
    private static function of(BusinessApp $app): Collection
    {
        self::$perRequest ??= new WeakMap;
        $request = request();
        $cached = self::$perRequest[$request] ?? [];

        if (! isset($cached[$app->id])) {
            $cached[$app->id] = $app->documentOutputs()->with('template')->get();
            self::$perRequest[$request] = $cached;
        }

        return $cached[$app->id];
    }

    public static function forgetCached(): void
    {
        self::$perRequest = null;
    }
}
