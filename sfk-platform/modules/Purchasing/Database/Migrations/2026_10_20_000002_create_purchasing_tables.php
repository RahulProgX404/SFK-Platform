<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchasing & payables (SYSTEM_BRAIN 05 納品(仕入)入力, 07 支払入力, 15 運送請求データ取込, 20 消耗品発注, 23 買掛管理;
 * BP-05, BR-PU-*).
 *
 * - `purchases`: one supplier delivery (納品(仕入)) line, 7-digit 納品(仕入)入力No (BR-PU-01), optionally of a job
 *   (注番, the job's 仕入 costs are recalculated from its purchases). 仕入金額合計 = 金額 + 断裁 + 版代 + 置き版代 + 特色
 *   (BR-PU-03), kept for lists. `category` is the per-line 仕入カテゴリ override of the supplier's (BR-PU-04).
 *   `purchase_user` = 通知先 of the 仕入原価超過 alert (BR-PU-05). The legacy 受注連携 / 買掛連携 flags are not kept:
 *   the job costs and payables are recalculated from the purchases.
 * - `payments` / `payment_lines`: a payment run with one row per supplier (BR-PU-08).
 * - `freight_charges`: carrier billing data (15), per job.
 * - `consumable_orders` / `consumable_order_lines`: purchase orders of consumables with approval (BR-PU-09).
 * - `payable_months`: 買掛 per supplier × calendar month, recalculated from purchases and payments (BR-PU-06/07,
 *   BR-TX-07); only 消費税端数調整 is entered by hand. `cat_*` = 仕入金額合計 per breakdown column (PayableCategories).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('purchase_number', 64)->nullable()->unique();
            $table->date('purchased_on');
            $table->foreignId('supplier_id')->constrained('partners')->restrictOnDelete();
            $table->string('category', 32)->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->decimal('quantity', 16, 4)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->bigInteger('amount');
            $table->bigInteger('cutting_amount')->default(0);
            $table->bigInteger('plate_amount')->default(0);
            $table->bigInteger('stored_plate_amount')->default(0);
            $table->bigInteger('spot_color_amount')->default(0);
            $table->bigInteger('total')->default(0);
            $table->boolean('is_fsc')->default(false);
            $table->boolean('period_end')->default(false);
            $table->jsonb('overruns')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'purchased_on']);
        });

        Schema::create('purchase_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['purchase_id', 'user_id']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->date('paid_on');
            $table->bigInteger('total')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->string('method', 8)->default('7.振込');
            $table->bigInteger('amount')->default(0);
            $table->date('withdrawn_on')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('supplier_id');
        });

        Schema::create('freight_charges', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->date('charged_on')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->bigInteger('amount')->nullable();
            $table->string('carrier_number', 64)->nullable();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('quantity', 16, 4)->nullable();
            $table->bigInteger('cutting_amount')->nullable();
            $table->bigInteger('plate_amount')->nullable();
            $table->bigInteger('stored_plate_amount')->nullable();
            $table->bigInteger('spot_color_amount')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('consumable_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->date('ordered_on');
            $table->foreignId('supplier_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('status', 8)->default('未処理');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('consumable_order_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consumable_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['consumable_order_id', 'user_id']);
        });

        Schema::create('consumable_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consumable_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('consumable_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('quantity', 16, 4)->nullable();
            $table->string('unit', 16)->nullable();
            $table->date('requested_on')->nullable();
            $table->string('orderer')->nullable();
            $table->date('delivered_on')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payable_months', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('partners')->cascadeOnDelete();
            $table->string('year_month', 7);
            $table->date('month_start');
            $table->bigInteger('previous_balance')->default(0);
            $table->bigInteger('paid')->default(0);
            $table->bigInteger('net_balance')->default(0);
            $table->bigInteger('purchase_amount')->default(0);
            $table->bigInteger('cutting_amount')->default(0);
            $table->bigInteger('plate_amount')->default(0);
            $table->bigInteger('stored_plate_amount')->default(0);
            $table->bigInteger('spot_color_amount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('tax_adjustment')->default(0);
            $table->bigInteger('month_total')->default(0);
            $table->bigInteger('closing_balance')->default(0);
            foreach (['materials', 'goods', 'outsourcing', 'cost_supplies', 'cost_repairs', 'cost_delivery', 'cost_misc', 'cost_packaging', 'supplies', 'repairs', 'delivery', 'misc', 'packaging', 'office_supplies', 'expenses', 'misc_misc', 'misc_cost_repairs', 'misc_repairs', 'misc_cost_supplies', 'misc_supplies', 'misc_materials', 'misc_goods', 'misc_freight', 'misc_outsourcing', 'misc_expenses', 'misc_packaging'] as $column) {
                $table->bigInteger('cat_'.$column)->default(0);
            }
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'year_month']);
            $table->index('year_month');
        });
    }

    public function down(): void
    {
        foreach (['payable_months', 'consumable_order_lines', 'consumable_order_user', 'consumable_orders', 'freight_charges', 'payment_lines', 'payments',
            'purchase_user', 'purchases'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
