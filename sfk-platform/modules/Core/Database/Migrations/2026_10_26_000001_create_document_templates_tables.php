<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 帳票出力 of an app (replaces the legacy PrintCreator service, SYSTEM_BRAIN 08_PRINTCREATOR / I-01): its 帳票テンプレート
 * (an uploaded background PDF or a blank sheet, with the fields placed on its pages) and its 出力設定 (which template a
 * button prints, where the button is shown, how several records are put out).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_app_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // background PDF on the local disk (null: blank sheet of the page size)
            $table->string('background_path')->nullable();
            $table->string('background_name')->nullable();
            // sizes of the pages in mm: [{"width": 210, "height": 297}, …]
            $table->jsonb('pages');
            // the placed elements (Modules\Core\Domain\Documents\TemplateLayout)
            $table->jsonb('layout');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_app_id', 'name']);
        });

        Schema::create('document_outputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_app_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->constrained()->restrictOnDelete();
            $table->string('name', 64);
            // Modules\Core\Domain\Documents\OutputPlacement values
            $table->jsonb('placements');
            $table->string('mode', 16)->default('merged');
            $table->string('delivery', 16)->default('open');
            $table->string('file_name', 200)->nullable();
            $table->boolean('admin_only')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_app_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_outputs');
        Schema::dropIfExists('document_templates');
    }
};
