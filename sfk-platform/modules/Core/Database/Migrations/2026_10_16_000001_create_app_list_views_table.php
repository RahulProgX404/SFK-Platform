<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * List views of an app saved from the 絞り込む dialog (保存, administrators): 一覧名, the 条件 / ソート
 * (Modules\Core\Domain\Filters\RecordFilter) and the code-defined view whose columns they show.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_list_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_app_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('base_view', 64)->nullable();
            $table->jsonb('filter');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_app_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_list_views');
    }
};
