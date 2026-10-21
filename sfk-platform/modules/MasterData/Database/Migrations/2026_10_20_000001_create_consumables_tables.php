<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 19. 消耗品マスタ (SYSTEM_BRAIN APP_19): consumables and materials incl. cardboard boxes, looked up by daily reports
 * (08N), consumables orders (20) and the legacy inventory (13). 名称 is required and unique. The supplier is a real
 * foreign key (the legacy lookup copied its 取引先コード / 仕入カテゴリ / 税区分 …; they are read from the partner).
 *
 * 在庫数 is not a column: it is the sum of `consumable_stock_movements` (APP_19 "do NOT copy blindly": stock as a
 * mutable field → a stock-movement ledger), written by 入出庫 on the record and later by the daily reports (使用数).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumables', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();

            $table->string('name', 64)->unique();
            $table->string('box_type', 16)->nullable();
            $table->string('kind', 16)->nullable();
            $table->decimal('reorder_point', 14, 4)->nullable();
            $table->decimal('amount', 14, 4)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->string('unit', 64)->nullable();
            $table->decimal('lot_size', 14, 4)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('consumable_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consumable_id')->constrained()->cascadeOnDelete();
            $table->date('moved_on');
            $table->string('reason', 16);
            $table->decimal('quantity', 14, 4);
            $table->nullableMorphs('source');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['consumable_id', 'moved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumable_stock_movements');
        Schema::dropIfExists('consumables');
    }
};
