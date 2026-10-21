<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 09. 見積書管理 (SYSTEM_BRAIN APP_09): quotations with the customer, 担当者 (`quotation_user`, copied from the customer's
 * 自社担当者), the envelope's specification and the quantity tiers (`quotation_lines`: 枚数, 単価; 金額 = 単価 × 枚数).
 * 見積金額(税抜) = Σ 金額 is kept for lists. 見積書番号 is unique (its legacy numbering is unknown, it is entered).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->date('quoted_on');
            $table->foreignId('customer_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->string('quote_number', 64)->nullable()->unique();
            $table->string('product_name')->nullable();
            $table->string('size')->nullable();
            $table->string('paper')->nullable();
            $table->string('printing')->nullable();
            $table->string('window')->nullable();
            $table->string('flap_glue')->nullable();
            $table->string('free_text_1')->nullable();
            $table->string('free_text_2')->nullable();
            $table->string('free_text_3')->nullable();
            $table->bigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->json('pdf_files')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quotation_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['quotation_id', 'user_id']);
        });

        Schema::create('quotation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->decimal('sheets', 16, 4)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['quotation_lines', 'quotation_user', 'quotations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
