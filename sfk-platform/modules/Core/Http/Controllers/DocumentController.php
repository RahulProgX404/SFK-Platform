<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Application\Documents\DocumentPrinter;
use Modules\Core\Application\Documents\DocumentSource;
use Modules\Core\Application\Documents\PrintedFile;
use Modules\Core\Application\Documents\PrintJobs;
use Modules\Core\Domain\Documents\OutputDelivery;
use Modules\Core\Domain\Documents\TemplateLayout;
use Modules\Core\Filament\AppResource;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DocumentOutput;
use Modules\Core\Models\DocumentTemplate;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The files of 帳票出力 (signed-in users):
 * - GET  /documents/templates/{template}/background  the background PDF, for the designer (administrators)
 * - GET|POST /documents/templates/{template}/preview  プレビュー of a template — the saved layout, or the designer's
 *   unsaved one (POST layout) — with a record (?record=) or the field labels (administrators)
 * - GET  /documents/outputs/{output}/records/{record}  the 帳票 of one record (its record page's buttons)
 * - GET  /documents/print/{token}  the 帳票 of the records chosen on a list (PrintJobs)
 * Records are read through the app's screens: app permissions, レコード and フィールド access rights apply.
 */
final class DocumentController
{
    public function background(DocumentTemplate $template): SymfonyResponse
    {
        $this->administrator();
        $file = $template->backgroundFile();
        abort_if($file === null, 404);

        return response()->file($file, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }

    public function preview(Request $request, DocumentTemplate $template, DocumentPrinter $printer): Response
    {
        $user = $this->administrator();
        $resource = $this->resource($template->app);
        $source = DocumentSource::for($resource, $user);

        $key = $request->input('record');
        $record = filled($key) && is_scalar($key) ? $this->findRecord($resource, (string) $key) : null;

        $elements = null;
        if ($request->isMethod('post')) {
            $layout = json_decode((string) $request->input('layout', '[]'), true);
            $elements = TemplateLayout::normalize($layout, $template->pageCount());
        }

        return self::respond($printer->preview($template, $source, $record, $user, $elements), download: false);
    }

    public function record(DocumentOutput $output, string $record, DocumentPrinter $printer): Response
    {
        $user = $this->user();
        abort_unless($output->isForRecord() && $output->isAvailableTo($user), 403);

        $resource = $this->resource($output->app);
        abort_unless($resource::canViewAny(), 403);
        $model = $this->findRecord($resource, $record) ?? abort(404);

        return self::respond(
            $printer->single($output, DocumentSource::for($resource, $user), $model, $user),
            download: $output->delivery === OutputDelivery::Download,
        );
    }

    public function batch(string $token, PrintJobs $jobs, DocumentPrinter $printer): Response
    {
        $user = $this->user();
        $job = $jobs->find($token);
        abort_if($job === null || $job['user'] !== $user->id, 404);

        $output = DocumentOutput::query()->find($job['output']) ?? abort(404);
        abort_unless($output->isForList() && $output->isAvailableTo($user), 403);

        $resource = $this->resource($output->app);
        abort_unless($resource::canViewAny(), 403);

        // the records the user may still see, in the order of the list
        $keys = array_slice($job['keys'], 0, DocumentOutput::MAX_RECORDS);
        $records = $resource::getEloquentQuery()->whereKey($keys)->get()->keyBy(fn (Model $model): string => (string) $model->getKey());
        $ordered = [];
        foreach ($keys as $key) {
            $model = $records->get((string) $key);
            if ($model !== null && $resource::canView($model)) {
                $ordered[] = $model;
            }
        }

        return self::respond(
            $printer->many($output, DocumentSource::for($resource, $user), $ordered, $user),
            download: $output->delivery === OutputDelivery::Download,
        );
    }

    private static function respond(PrintedFile $file, bool $download): Response
    {
        $fallback = (string) preg_replace('/[^\x20-\x7E]|[%\/\\\\"]/', '_', $file->name);

        return response($file->contents, 200, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition($download || ! $file->isPdf() ? 'attachment' : 'inline', $file->name, $fallback),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return class-string<AppResource> */
    private function resource(BusinessApp $app): string
    {
        return AppResource::forCode($app->code) ?? abort(404);
    }

    /** @param  class-string<AppResource>  $resource */
    private function findRecord(string $resource, string $key): ?Model
    {
        $model = $resource::getEloquentQuery()->whereKey($key)->first();

        return $model !== null && $resource::canView($model) ? $model : null;
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function administrator(): User
    {
        $user = $this->user();
        abort_unless($user->user_type->isAdministrator(), 403);

        return $user;
    }
}
