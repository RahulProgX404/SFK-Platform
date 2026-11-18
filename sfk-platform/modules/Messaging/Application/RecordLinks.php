<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Core\Application\Notifications\RecordDirectory;
use Modules\Core\Filament\AppResource;
use Throwable;

/**
 * ERP records shared in a message (docs/messaging.md "ERP records"): a link to a record page of this system (受注,
 * 請求書, 日報 …, copied from the address bar) is drawn as a card — app name and the record's title — but only for a
 * reader who may open that record (app permissions, the app's narrowing, レコードのアクセス権 — Core RecordDirectory).
 * Anyone else sees the bare link, which the record page refuses them as well: receiving a message never reveals a
 * record. No outside address is ever fetched (no link previews of other sites: no server-side requests to addresses
 * users type).
 */
final class RecordLinks
{
    /** @var array<string, array{0: class-string<AppResource>, 1: string|int}|null> url => [resource, key] */
    private array $resolved = [];

    public function __construct(private readonly RecordDirectory $directory) {}

    /** @return list<string> the links to this system in the text (at most 3) */
    public static function urlsIn(string $text): array
    {
        $base = rtrim((string) config('app.url'), '/');
        if ($base === '' || ! preg_match_all('#https?://[^\s<>"\'「」]+#u', $text, $matches)) {
            return [];
        }

        $urls = [];
        foreach ($matches[0] as $url) {
            $url = rtrim($url, '.,);:!?。、');
            if (str_starts_with($url, $base.'/') && ! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return array_slice($urls, 0, 3);
    }

    /**
     * Cards of the record links in these texts, for this reader.
     *
     * @param  list<string>  $texts
     * @return array<string, array{url: string, app: string, title: string}>
     */
    public function cards(array $texts, User $viewer): array
    {
        $cards = [];
        foreach ($texts as $text) {
            foreach (self::urlsIn($text) as $url) {
                if (! array_key_exists($url, $cards) && ($card = $this->card($url, $viewer)) !== null) {
                    $cards[$url] = $card;
                }
            }
        }

        return $cards;
    }

    /** @return array{url: string, app: string, title: string}|null */
    private function card(string $url, User $viewer): ?array
    {
        $target = $this->resolve($url);
        if ($target === null) {
            return null;
        }

        [$resource, $key] = $target;
        try {
            $record = $resource::getModel()::query()->find($key);
            if (! $record instanceof Model || ! $this->directory->visibleTo($viewer, $record)) {
                return null;
            }

            return ['url' => $url, 'app' => $resource::displayName(), 'title' => trim(strip_tags((string) $resource::getRecordTitle($record)))];
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{0: class-string<AppResource>, 1: string|int}|null */
    private function resolve(string $url): ?array
    {
        if (array_key_exists($url, $this->resolved)) {
            return $this->resolved[$url];
        }

        $found = null;
        try {
            $route = app('router')->getRoutes()->match(Request::create($url));
            $name = (string) $route->getName();
            $record = $route->parameter('record');
            if ($record !== null && preg_match('/^filament\.([^.]+)\.resources\.(.+)\.(view|edit)$/', $name, $parts) === 1) {
                $panel = Filament::getPanel($parts[1], isStrict: false);
                foreach ($panel->getResources() as $resource) {
                    if (is_subclass_of($resource, AppResource::class) && $resource::getRouteBaseName($panel) === 'filament.'.$parts[1].'.resources.'.$parts[2]) {
                        $found = [$resource, is_scalar($record) ? $record : (string) $record];
                        break;
                    }
                }
            }
        } catch (Throwable) {
            $found = null;
        }

        return $this->resolved[$url] = $found;
    }
}
