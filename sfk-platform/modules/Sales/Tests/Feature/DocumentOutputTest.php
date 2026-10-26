<?php

/*
 * 帳票出力 (Core, replaces the legacy PrintCreator — SYSTEM_BRAIN 08_PRINTCREATOR / I-01) through 10. 納品書管理(売上),
 * whose legacy document is the 納品書 with its rows: administrators add templates (an uploaded background PDF or a
 * blank sheet), place fields and the table in the designer and add 出力設定 shown on the record page, on the list
 * (the listed records or the checked rows) or in the 上部スペース; users get one PDF per record, the listed records
 * as one PDF or a ZIP, within their access rights. The legacy 06 PrintCreator buttons were for one administrator only
 * (08_PRINTCREATOR, 11_PERMISSIONS): 管理者のみ.
 */

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Application\Apps\FieldPermissionService;
use Modules\Core\Application\Documents\DocumentSettings;
use Modules\Core\Application\Documents\Pdf\JapanesePdf;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Domain\Documents\OutputPlacement;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DocumentOutput;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\Space;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Application\Documents\DeliveryNoteIssuer;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\DeliveryNoteSettings;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\ListDeliveryNotes;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\ViewDeliveryNote;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\Sale;

function documentsApp(): BusinessApp
{
    app(AppLifecycle::class)->syncWithCode(
        [DeliveryNoteResource::appCode() => DeliveryNoteResource::appName()],
        [DeliveryNoteResource::appCode() => DeliveryNoteResource::appSettingDefaults()],
    );
    $app = BusinessApp::query()->where('code', DeliveryNoteResource::appCode())->sole();
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->spaces()->attach(Space::query()->create(['panel_id' => 'erp', 'name' => '売上スペース']));

    return $app;
}

/** A delivery note of a customer with one row per product name. */
function documentNote(string $customer, array $products = ['長3封筒']): DeliveryNote
{
    $partner = Partner::query()->create([
        'name' => $customer, 'short_name' => mb_substr($customer, 0, 4), 'code' => 'C'.random_int(1000, 9999), 'is_customer' => true,
        'closing_day' => '20日', 'rounding' => '切捨', 'postal_code' => '530-0001', 'address' => '大阪市北区1-1',
        'invoice_issuance' => '郵送', 'delivery_note_issuance' => '郵送', 'collection_month' => '翌月', 'collection_day' => '末日',
    ]);
    $sales = array_map(fn (string $product): Sale => Sale::query()->create([
        'customer_id' => $partner->id, 'sold_on' => '2026-10-05', 'delivery_due_on' => '2026-10-06', 'product_name' => $product,
        'quantity' => '1000', 'unit_price' => '10', 'amount' => 10000,
    ]), $products);

    return app(DeliveryNoteIssuer::class)->issue($sales)[0];
}

/** A one-page A4 PDF, as an administrator would upload. */
function backgroundPdf(): string
{
    $pdf = new JapanesePdf;
    $pdf->newPage(210, 297);
    $pdf->textBox(80, 10, 50, 10, '納品書', ['size' => 18, 'border' => true]);

    return $pdf->Output('S');
}

function pdfStreams(string $pdf): string
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

    return implode("\n", array_map(fn (string $stream): string => @gzuncompress($stream) ?: $stream, $streams[1]));
}

function pdfText(string $text): string
{
    return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], (string) mb_convert_encoding($text, 'UCS-2BE', 'UTF-8'));
}

/** A template with 得意先名, the 納品書番号 and the rows (品名 / 小計), and a 出力設定 at the places. */
function documentOutput(BusinessApp $app, array $placements, array $output = []): DocumentOutput
{
    $settings = app(DocumentSettings::class);
    $name = ($output['name'] ?? '納品書').'（縦型）';
    $template = $settings->createTemplate($app, ['name' => $name, 'paper' => 'a4_portrait'], null);
    $settings->saveDesign($template, $name, [
        ['type' => 'field', 'field' => 'customer_id', 'x' => 20, 'y' => 30, 'w' => 90, 'h' => 8, 'suffix' => ' 御中'],
        ['type' => 'field', 'field' => 'number', 'x' => 150, 'y' => 20, 'w' => 40, 'h' => 8],
        ['type' => 'table', 'table' => 'lines', 'x' => 15, 'y' => 80, 'rows' => 2, 'rowHeight' => 8, 'columns' => [
            ['field' => 'product_name', 'width' => 100], ['field' => 'amount', 'width' => 40, 'align' => 'right'],
        ]],
    ], null);

    return $settings->saveOutput($app, null, ['name' => '納品書', 'document_template_id' => $template->id, 'placements' => $placements, ...$output]);
}

function printToken(mixed $component): string
{
    $expression = (string) ($component->effects['xjs'][0]['expression'] ?? '');
    preg_match('#documents\\\\?/print\\\\?/([A-Za-z0-9]{40})#', $expression, $match);

    return $match[1] ?? '';
}

function documentsEmployee(BusinessApp $app): User
{
    $columns = [];
    foreach (AppAbility::cases() as $ability) {
        $columns[$ability->column()] = $ability === AppAbility::View;
    }
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, ...$columns]);

    $employee = grantDashboards(User::factory()->create(), 'erp');
    $app->spaces()->first()?->users()->attach($employee);
    Space::forgetCached();

    return $employee;
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->documents = documentsApp();
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
});

it('adds the tab 帳票出力 to アプリの設定 and the gear menu, for administrators only', function () {
    $employee = documentsEmployee($this->documents);
    $this->actingAs($this->admin);

    $this->get(DeliveryNoteSettings::getUrl(['tab' => 'documents']))
        ->assertOk()
        ->assertSeeInOrder(['一覧', '設定', '帳票出力', '帳票テンプレート', 'テンプレートを追加する', '出力設定', '出力設定を追加する']);
    $this->get(DeliveryNoteResource::getUrl('index'))
        ->assertSeeInOrder(['アプリの設定メニュー', 'その他の設定', '帳票出力']);

    $this->actingAs($employee);
    $this->get(DeliveryNoteSettings::getUrl(['tab' => 'documents']))->assertForbidden();
});

it('adds a template from an uploaded PDF and opens the designer with the fields and tables of the record page', function () {
    Storage::fake('local');
    $this->actingAs($this->admin);
    $this->travelBack();

    Livewire::withQueryParams(['tab' => 'documents'])->test(DeliveryNoteSettings::class)
        ->callAction('addTemplate', data: [
            'name' => '納品書（縦型）',
            'source' => 'pdf',
            'background' => UploadedFile::fake()->createWithContent('nouhinsho.pdf', backgroundPdf()),
        ])
        ->assertHasNoActionErrors();

    $template = DocumentTemplate::query()->sole();
    expect($template->background_name)->toBe('nouhinsho.pdf')
        ->and($template->pages)->toBe([['width' => 210, 'height' => 297]])
        ->and(Storage::disk('local')->exists((string) $template->background_path))->toBeTrue();

    $designer = Livewire::withQueryParams(['tab' => 'documents', 'template' => $template->id])->test(DeliveryNoteSettings::class);
    $config = $designer->instance()->designerConfig();
    $fields = array_column($config['fields'], 'label', 'value');
    $lines = collect($config['tables'])->firstWhere('name', 'lines');

    expect($fields)->toMatchArray(['number' => '納品書番号', 'customer_id' => '得意先名', 'standard_tax' => '10%対象消費税', 'tax_total' => '消費税合計'])
        ->and($lines['label'])->toBe('納品内容詳細')
        ->and(array_column($lines['columns'], 'label', 'name'))->toMatchArray(['product_name' => '品名', 'quantity' => '数量', 'amount' => '小計'])
        ->and($config['background'])->toContain('/documents/templates/'.$template->id.'/background');

    $this->get(route('core.documents.background', ['template' => $template]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    // 保存 of the designer
    expect($designer->instance()->saveDesign(['name' => '', 'elements' => []]))->toBe(['name' => 'テンプレート名を入力してください。']);
    $designer->call('saveDesign', ['name' => '納品書', 'elements' => [
        ['type' => 'field', 'field' => 'customer_id', 'x' => 20, 'y' => 30, 'w' => 80, 'h' => 8],
    ]]);
    expect($template->refresh()->name)->toBe('納品書')
        ->and($template->elements())->toHaveCount(1)
        ->and($template->elements()[0])->toMatchArray(['field' => 'customer_id', 'x' => 20.0, 'font' => 'gothic']);
});

it('adds a blank template, copies it with its layout and keeps a template a 出力設定 prints', function () {
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['tab' => 'documents'])->test(DeliveryNoteSettings::class)
        ->callAction('addTemplate', data: ['name' => '白紙の納品書', 'source' => 'blank', 'paper' => 'a4_landscape'])
        ->assertHasNoActionErrors();
    $template = DocumentTemplate::query()->sole();
    expect($template->background_path)->toBeNull()
        ->and($template->pages)->toBe([['width' => 297, 'height' => 210]])
        ->and($template->paperLabel())->toBe('白紙 297 × 210 mm');

    Livewire::withQueryParams(['tab' => 'documents'])->test(DeliveryNoteSettings::class)
        ->call('copyTemplate', $template->id)
        ->assertSee('白紙の納品書 - コピー');

    $output = app(DocumentSettings::class)->saveOutput($this->documents, null, ['name' => '納品書', 'document_template_id' => $template->id, 'placements' => ['record_menu']]);
    Livewire::withQueryParams(['tab' => 'documents'])->test(DeliveryNoteSettings::class)
        ->callAction(TestAction::make('deleteTemplate')->arguments(['template' => $template->id]));
    expect(DocumentTemplate::query()->whereKey($template->id)->exists())->toBeTrue();

    $output->delete();
    Livewire::withQueryParams(['tab' => 'documents'])->test(DeliveryNoteSettings::class)
        ->callAction(TestAction::make('deleteTemplate')->arguments(['template' => $template->id]));
    expect(DocumentTemplate::query()->whereKey($template->id)->exists())->toBeFalse();
});

it('saves 出力設定 with their places, file name and 管理者のみ, in the dragged order', function () {
    $this->actingAs($this->admin);
    $template = app(DocumentSettings::class)->createTemplate($this->documents, ['name' => '納品書', 'paper' => 'a4_portrait'], null);

    $page = Livewire::withQueryParams(['tab' => 'documents'])->test(DeliveryNoteSettings::class)
        ->callAction('output', data: [
            'name' => '納品書を出力', 'document_template_id' => $template->id, 'placements' => ['record_menu', 'list_menu'],
            'mode' => 'zip', 'delivery' => 'download', 'file_name' => '{number}_{customer_id}', 'admin_only' => true,
        ])
        ->callAction('output', data: [
            'name' => '控え', 'document_template_id' => $template->id, 'placements' => ['record_space'], 'mode' => 'merged', 'delivery' => 'open',
        ]);

    [$first, $second] = DocumentOutput::query()->orderBy('sort_order')->get()->all();
    expect($first->placements)->toBe(['record_menu', 'list_menu'])
        ->and($first->mode->value)->toBe('zip')
        ->and($first->file_name)->toBe('{number}_{customer_id}')
        ->and($first->admin_only)->toBeTrue();

    Livewire::withQueryParams(['tab' => 'documents'])->test(DeliveryNoteSettings::class)
        ->assertSeeInOrder(['納品書を出力', 'レコード詳細画面のメニュー', '一覧画面のメニュー（絞り込んだレコード）', '管理者のみ', '控え']);

    // a 出力設定 needs a name, a template and a place
    $page->callAction('output', data: ['name' => '', 'document_template_id' => $template->id, 'placements' => [], 'mode' => 'merged', 'delivery' => 'open'])
        ->assertHasActionErrors(['name' => 'required', 'placements' => 'required']);

    $page = Livewire::withQueryParams(['tab' => 'documents'])->test(DeliveryNoteSettings::class);
    $page->call('reorderOutputs', [$second->id, $first->id]);
    expect(DocumentOutput::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['控え', '納品書を出力']);

    $page->callAction(TestAction::make('output')->arguments(['output' => $first->id]), data: [
        'name' => '納品書', 'document_template_id' => $template->id, 'placements' => ['list_checked'], 'mode' => 'merged', 'delivery' => 'open', 'admin_only' => false,
    ]);
    expect($first->refresh()->name)->toBe('納品書')->and($first->placements)->toBe(['list_checked']);

    $page->callAction(TestAction::make('deleteOutput')->arguments(['output' => $second->id]));
    expect(DocumentOutput::query()->pluck('name')->all())->toBe(['納品書']);
});

it('puts out a record from its button before 編集 as a PDF in a new tab', function () {
    $output = documentOutput($this->documents, ['record_menu']);
    $note = documentNote('株式会社得意先', ['長3封筒', '角2封筒', '洋形封筒']);
    $this->actingAs($this->admin);

    $url = route('core.documents.record', ['output' => $output, 'record' => $note->id]);
    Livewire::test(ViewDeliveryNote::class, ['record' => $note->getRouteKey()])
        ->assertActionVisible('document'.$output->id)
        ->assertActionHasUrl('document'.$output->id, $url)
        ->assertActionShouldOpenUrlInNewTab('document'.$output->id);

    $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');

    $pdf = (string) $response->getContent();
    $content = pdfStreams($pdf);
    expect($content)->toContain(pdfText('株式会社得意先 御中'))
        ->and($content)->toContain(pdfText($note->number))
        ->and($content)->toContain(pdfText('洋形封筒'))
        ->and($content)->toContain(pdfText('¥10,000'))
        // three rows on two lines: the page is printed twice
        ->and(preg_match_all('/\/Type \/Page\b/', $pdf))->toBe(2);
});

it('shows the 帳票 drop-down in the 上部スペース of the record and the list', function () {
    $output = documentOutput($this->documents, ['record_space', 'list_space']);
    $note = documentNote('株式会社得意先');
    $this->actingAs($this->admin);

    $this->get(DeliveryNoteResource::getUrl('view', ['record' => $note]))
        ->assertOk()
        ->assertSee('sfk-docs-bar', false)
        ->assertSee(route('core.documents.record', ['output' => $output, 'record' => $note->id]), false);
    $this->get(DeliveryNoteResource::getUrl('index'))
        ->assertOk()
        ->assertSee('sfk-docs-bar', false)
        ->assertSee('printDocuments', false);

    $component = Livewire::test(ListDeliveryNotes::class)
        ->callAction(TestAction::make('printDocuments')->arguments(['output' => $output->id]));
    $this->get(route('core.documents.print', ['token' => printToken($component)]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('puts out the listed records as one PDF, or a ZIP named by the file name', function () {
    $output = documentOutput($this->documents, ['list_menu']);
    $first = documentNote('株式会社一番');
    $second = documentNote('株式会社二番');
    $this->actingAs($this->admin);

    $component = Livewire::test(ListDeliveryNotes::class)
        ->mountAction('documentList'.$output->id)
        ->assertActionMounted('documentList'.$output->id)
        ->assertMountedActionModalSee('2件のレコードを出力しますか？')
        ->callMountedAction();
    $pdf = (string) $this->get(route('core.documents.print', ['token' => printToken($component)]))->assertOk()->getContent();
    $content = pdfStreams($pdf);
    expect($content)->toContain(pdfText('株式会社一番'))
        ->and($content)->toContain(pdfText('株式会社二番'))
        ->and(strpos($content, pdfText('株式会社二番')))->toBeLessThan(strpos($content, pdfText('株式会社一番')));

    // レコードごとのPDF（ZIP）, downloaded
    $output->forceFill(['mode' => 'zip', 'delivery' => 'download', 'file_name' => '{number}_{customer_id}'])->save();
    $component = Livewire::test(ListDeliveryNotes::class)->callAction('documentList'.$output->id);
    $response = $this->get(route('core.documents.print', ['token' => printToken($component)]))->assertOk()->assertHeader('Content-Type', 'application/zip');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');

    $file = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($file, (string) $response->getContent());
    $zip = new ZipArchive;
    $zip->open($file);
    $names = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $names[] = $zip->getNameIndex($index);
    }
    $zip->close();
    unlink($file);

    expect($names)->toEqualCanonicalizing([$first->number.'_株式会社一番.pdf', $second->number.'_株式会社二番.pdf']);

    // the token is the user's own
    $this->actingAs(User::factory()->superAdmin()->create());
    $this->get(route('core.documents.print', ['token' => printToken($component)]))->assertNotFound();
});

it('puts out the checked rows, and says so when nothing is listed', function () {
    $output = documentOutput($this->documents, ['list_checked']);
    $first = documentNote('株式会社一番');
    documentNote('株式会社二番');
    $this->actingAs($this->admin);

    $component = Livewire::test(ListDeliveryNotes::class)
        ->set('selectedTableRecords', [(string) $first->id])
        ->callAction('documentChecked'.$output->id);
    $content = pdfStreams((string) $this->get(route('core.documents.print', ['token' => printToken($component)]))->assertOk()->getContent());
    expect($content)->toContain(pdfText('株式会社一番'))->and($content)->not->toContain(pdfText('株式会社二番'));

    $listOutput = documentOutput($this->documents, ['list_menu'], ['name' => '一覧']);
    DeliveryNote::query()->get()->each->delete();
    Livewire::test(ListDeliveryNotes::class)
        ->mountAction('documentList'.$listOutput->id)
        ->assertNotified('出力するレコードがありません。');
});

it('keeps employees within their rights: 管理者のみ buttons, フィールドのアクセス権 and the records they may see', function () {
    $employee = documentsEmployee($this->documents);
    $output = documentOutput($this->documents, ['record_menu']);
    $adminOnly = documentOutput($this->documents, ['record_menu'], ['name' => '管理者用', 'admin_only' => true]);
    $note = documentNote('株式会社得意先');

    $this->actingAs($employee);
    Livewire::test(ViewDeliveryNote::class, ['record' => $note->getRouteKey()])
        ->assertActionVisible('document'.$output->id)
        ->assertActionDoesNotExist('document'.$adminOnly->id);
    $this->get(route('core.documents.record', ['output' => $adminOnly, 'record' => $note->id]))->assertForbidden();

    // 得意先名: なし for everyone
    app(FieldPermissionService::class)->save($this->documents, [
        ['field' => 'customer_id', 'entries' => [['subject_type' => 'everyone', 'access' => 'none']]],
    ], DeliveryNoteResource::permissionFields(), $this->admin);
    $content = pdfStreams((string) $this->get(route('core.documents.record', ['output' => $output, 'record' => $note->id]))->assertOk()->getContent());
    expect($content)->not->toContain(pdfText('株式会社得意先'))->and($content)->toContain(pdfText($note->number));

    // preview and background are for administrators
    $this->get(route('core.documents.preview', ['template' => $output->template]))->assertForbidden();

    // a record that does not exist (or cannot be seen) is not put out
    $this->get(route('core.documents.record', ['output' => $output, 'record' => 999999]))->assertNotFound();
});

it('previews the unsaved layout with a record or with the field labels', function () {
    $output = documentOutput($this->documents, ['record_menu']);
    $note = documentNote('株式会社得意先');
    $this->actingAs($this->admin);

    $layout = json_encode([['type' => 'field', 'field' => 'address', 'x' => 10, 'y' => 10, 'w' => 80, 'h' => 8]]);
    $content = pdfStreams((string) $this->post(route('core.documents.preview', ['template' => $output->template]), ['layout' => $layout, 'record' => $note->id])->assertOk()->getContent());
    expect($content)->toContain(pdfText('大阪市北区1-1'))->and($content)->not->toContain(pdfText('株式会社得意先'));

    $content = pdfStreams((string) $this->get(route('core.documents.preview', ['template' => $output->template]))->assertOk()->getContent());
    expect($content)->toContain(pdfText('得意先名 御中'))->and($content)->toContain(pdfText('品名'));
});

it('rewrites an uploaded PDF the reader cannot open (compressed cross-references) with qpdf', function () {
    if (! is_executable('/usr/bin/qpdf')) {
        $this->markTestSkipped('qpdf is not installed here.');
    }
    Storage::fake('local');

    $source = tempnam(sys_get_temp_dir(), 'pdf');
    $compressed = $source.'-compressed.pdf';
    file_put_contents($source, backgroundPdf());
    exec('qpdf --object-streams=generate '.escapeshellarg($source).' '.escapeshellarg($compressed));
    Storage::disk('local')->put('documents/uploads/compressed.pdf', (string) file_get_contents($compressed));
    unlink($source);
    unlink($compressed);

    $template = app(DocumentSettings::class)->createTemplate($this->documents, ['name' => '圧縮PDF', 'background' => 'documents/uploads/compressed.pdf', 'background_name' => 'compressed.pdf'], null);

    expect($template->pages)->toBe([['width' => 210, 'height' => 297]])
        ->and(OutputPlacement::ListChecked->isList())->toBeTrue();
});

it('lists another app\'s records on a page only as far as the viewer may see them (related lists)', function () {
    documentNote('株式会社得意先');
    $outsider = grantDashboards(User::factory()->create(), 'erp');

    $this->actingAs($outsider);
    expect(DeliveryNoteResource::onlyVisible(DeliveryNote::query())->count())->toBe(0);

    $this->actingAs(documentsEmployee($this->documents));
    expect(DeliveryNoteResource::onlyVisible(DeliveryNote::query())->count())->toBe(1);

    $this->actingAs($this->admin);
    expect(DeliveryNoteResource::onlyVisible(DeliveryNote::query())->count())->toBe(1);
});
