<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Notifications\RecordDirectory;
use Modules\Core\Filament\AppResource;

/**
 * The record of a 通知 seen through the app screens (AppResource) showing its model, in any dashboard: link, app name
 * and who may open it. A record no app shows has no link and may be seen by every recipient.
 */
final class AppRecordDirectory implements RecordDirectory
{
    /** @var array<class-string<Model>, list<array{0: class-string<AppResource>, 1: string}>> model => [resource, panel id] */
    private array $resources = [];

    public function url(Model $record): ?string
    {
        foreach ($this->resourcesFor($record) as [$resource, $panel]) {
            if ($resource::hasPage('view')) {
                return $resource::getUrl('view', ['record' => $record], panel: $panel);
            }
        }

        return null;
    }

    public function appName(Model $record): ?string
    {
        foreach ($this->resourcesFor($record) as [$resource]) {
            return $resource::app()->name ?? $resource::appName();
        }

        return null;
    }

    /**
     * Asked as the user: the screens decide from the signed-in user (AppAccess, the app's getEloquentQuery() narrowing,
     * RecordAccess), so the user is signed in for the question only and the previous one restored.
     */
    public function visibleTo(User $user, Model $record): bool
    {
        $resources = $this->resourcesFor($record);
        if ($resources === []) {
            return true;
        }

        $guard = auth()->guard('web');
        $previous = $guard->user();
        $guard->setUser($user);

        try {
            foreach ($resources as [$resource]) {
                if ($resource::canView($record) && $resource::getEloquentQuery()->whereKey($record->getKey())->exists()) {
                    return true;
                }
            }

            return false;
        } finally {
            $previous !== null ? $guard->setUser($previous) : $guard->forgetUser();
        }
    }

    /** @return list<array{0: class-string<AppResource>, 1: string}> */
    private function resourcesFor(Model $record): array
    {
        $model = $record::class;
        if (! array_key_exists($model, $this->resources)) {
            $found = [];
            foreach (Filament::getPanels() as $panel) {
                foreach ($panel->getResources() as $resource) {
                    if (is_subclass_of($resource, AppResource::class) && $resource::getModel() === $model) {
                        $found[] = [$resource, $panel->getId()];
                    }
                }
            }
            $this->resources[$model] = $found;
        }

        return $this->resources[$model];
    }
}
