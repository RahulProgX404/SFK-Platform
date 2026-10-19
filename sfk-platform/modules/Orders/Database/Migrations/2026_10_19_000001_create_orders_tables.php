<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 04. 受注入力 (SYSTEM_BRAIN APP_04): the job (注番) with its estimate, its actual costs and the three tables 用紙情報 /
 * 印刷 / トムソン・NO・シルク・箔 as child rows. 注番 is unique (BR-OR-01). Partners, the machine, the designer and the
 * 担当者 are real foreign keys instead of the legacy lookup copies (略名, 得意先ID, addresses and the string keys
 * 関連レコードキー / 加算更新用キー are derived from them, APP_04 "do NOT copy blindly"); only 締日 and 端数区分 of the
 * customer are kept as of the order, like the legacy lookup.
 *
 * Amounts are integer yen, unit prices decimals (scale 4, legacy precision), quantities decimals. The calculated
 * columns (小計 … 利益率, 製造残数量, 売上残数量) are written on every save from the record (Domain JobCosting) so
 * lists can sort and filter by them. The actual-cost inputs (仕入金額 …, 社内原価, 製造済数量, 売上済数量,
 * 現在売上済金額) were written by apps 05 / 06 / 08N in the legacy system; they are entered here until those apps
 * are rebuilt and derive them from their rows (BR-OR-07..10). 売上履歴 (purpose unclear) is not rebuilt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();

            $table->string('order_number', 64)->unique();
            $table->string('status', 32)->nullable()->index();
            $table->foreignId('customer_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->string('closing_day', 16)->nullable();
            $table->string('customer_rounding', 16)->nullable();
            $table->string('product_name')->nullable();
            $table->boolean('is_fsc')->default(false);
            $table->string('category1', 32)->nullable();
            $table->string('category2', 32)->nullable();

            $table->decimal('quantity', 16, 4)->nullable();
            $table->string('unit', 16)->nullable();
            $table->decimal('sample', 16, 4)->nullable();
            $table->decimal('spare', 16, 4)->nullable();
            $table->date('due_date')->nullable();
            $table->time('due_time')->nullable();
            $table->date('order_date')->nullable()->index();
            $table->decimal('expected_hours', 12, 4)->nullable();
            $table->decimal('manufactured_quantity', 16, 4)->default(0);
            $table->decimal('manufacturing_remaining', 16, 4)->default(0);
            $table->decimal('sold_quantity', 16, 4)->default(0);
            $table->decimal('sales_remaining', 16, 4)->default(0);

            // 用紙: 小計 = Σ用紙金額(小計用) + デザイン料 + Σ断裁金額(小計用)
            $table->bigInteger('paper_subtotal')->default(0);

            // 版下・製版・刷版
            $table->foreignId('plate_supplier_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->string('plate_version', 16)->nullable();
            $table->date('plate_last_used_on')->nullable();
            $table->decimal('plate_count', 12, 4)->nullable();
            $table->decimal('plate_unit_price', 14, 4)->nullable();
            $table->bigInteger('plate_amount')->default(0);
            $table->string('plate_storage', 8)->default('不要');
            $table->string('plate_size', 16)->nullable();
            $table->bigInteger('design_fee')->default(0);
            $table->text('plate_notes')->nullable();

            // 製袋・製本・他加工
            $table->foreignId('manufacturer_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->string('bag_schedule', 64)->nullable();
            foreach (['width', 'height', 'flap', 'bottom', 'unfolded_width', 'unfolded_height'] as $dimension) {
                $table->decimal($dimension, 10, 2)->nullable();
            }
            $table->string('envelope_shape', 8)->nullable();
            $table->string('envelope_other')->nullable();
            $table->string('size_no', 64)->nullable();
            $table->string('pasting', 32)->nullable();
            $table->boolean('as_per_artwork')->default(false);
            $table->string('flap_type', 32)->nullable();
            $table->string('die_cut', 32)->nullable();
            $table->string('flap_glue', 32)->nullable();
            $table->string('side_glue', 16)->nullable();
            $table->string('die')->nullable();
            $table->string('window', 32)->nullable();
            foreach (['window_width', 'window_height', 'window_from_left', 'window_from_bottom'] as $dimension) {
                $table->decimal($dimension, 10, 2)->nullable();
            }
            $table->string('carton_number', 64)->nullable();
            $table->decimal('per_case', 12, 4)->nullable();
            $table->boolean('per_case_specified')->default(false);
            $table->boolean('partition_case')->default(false);
            $table->boolean('band')->default(false);
            $table->decimal('band_sheets', 12, 4)->nullable();
            $table->boolean('has_label')->default(false);
            $table->decimal('bag_unit_price', 14, 4)->nullable();
            $table->boolean('bag_manual')->default(false);
            $table->bigInteger('bag_manual_amount')->nullable();
            $table->bigInteger('bag_amount')->default(0);
            $table->bigInteger('bag_subtotal')->default(0);
            $table->bigInteger('case_cost')->nullable();
            $table->bigInteger('shipping_cost')->nullable();

            $table->bigInteger('sales_amount')->default(0);
            $table->bigInteger('estimated_cost_total')->default(0);
            $table->bigInteger('estimated_profit')->default(0);
            $table->bigInteger('profit')->default(0);
            $table->decimal('profit_rate', 12, 2)->default(0);

            $table->date('printed_on')->nullable();
            $table->string('print_location')->nullable();
            $table->date('delivered_on')->nullable();
            $table->string('delivery_location')->nullable();
            $table->string('delivery_service')->nullable();
            $table->date('completed_on')->nullable();
            $table->string('remarks')->nullable();

            // その他
            $table->text('memo')->nullable();
            $table->text('worker_message')->nullable();
            $table->text('notes')->nullable();
            $table->bigInteger('internal_cost')->default(0);
            $table->json('work_sheet_order_files')->nullable();
            $table->json('work_sheet_sales_files')->nullable();
            $table->json('work_sheet_draft_files')->nullable();

            // 各種原価
            foreach (['purchase_amount', 'purchase_cutting_amount', 'purchase_plate_amount', 'purchase_stored_plate_amount', 'purchase_spot_color_amount'] as $cost) {
                $table->bigInteger($cost)->default(0);
            }
            $table->bigInteger('purchase_cost_total')->default(0);
            $table->bigInteger('actual_cost_total')->default(0);
            $table->bigInteger('actual_sales_amount')->default(0);
            $table->bigInteger('sold_amount')->default(0);

            // 開発者用フィールド
            $table->date('previous_order_date')->nullable();
            $table->string('previous_order_number', 64)->nullable();
            $table->decimal('previous_order_quantity', 16, 4)->nullable();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->bigInteger('manufacturing_sales')->nullable();
            $table->string('data_supply', 8)->nullable();
            $table->foreignId('data_supplier_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->foreignId('designer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('film_supply', 8)->nullable();
            $table->foreignId('film_supplier_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->decimal('legacy_size_no', 10, 2)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 担当者 (legacy user selection, several allowed)
        Schema::create('order_assignees', function (Blueprint $table): void {
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['order_id', 'user_id']);
        });

        // 用紙情報
        Schema::create('order_papers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->string('paper_name')->nullable();
            $table->string('size', 64)->nullable();
            $table->decimal('weight_kg', 12, 4)->nullable();
            $table->string('grain', 8)->nullable();
            $table->decimal('sheets', 16, 4)->nullable();
            $table->string('pricing', 8)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->boolean('manual')->default(false);
            $table->bigInteger('manual_amount')->nullable();
            $table->string('cut', 64)->nullable();
            $table->decimal('bundles', 12, 4)->nullable();
            $table->decimal('cutting_unit_price', 14, 4)->nullable();
            $table->boolean('cutting_manual')->default(false);
            $table->bigInteger('cutting_manual_amount')->nullable();
            $table->decimal('legacy_cut', 12, 4)->nullable();
            $table->timestamps();
        });

        // 印刷 and トムソン・NO・シルク・箔 (same columns, different 金額 formula)
        foreach (['order_printings', 'order_finishings'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('sort')->default(0);
                $table->foreignId('supplier_id')->nullable()->constrained('partners')->restrictOnDelete();
                $table->string('notes')->nullable();
                $table->decimal('imposition', 12, 4)->nullable();
                $table->decimal('passes', 16, 4)->nullable();
                $table->decimal('spare_passes', 16, 4)->nullable();
                $table->decimal('colors', 12, 4)->nullable();
                $table->string('ink_colors', 64)->nullable();
                $table->decimal('unit_price', 14, 4)->nullable();
                $table->boolean('manual')->default(false);
                $table->bigInteger('manual_amount')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_finishings');
        Schema::dropIfExists('order_printings');
        Schema::dropIfExists('order_papers');
        Schema::dropIfExists('order_assignees');
        Schema::dropIfExists('orders');
    }
};
