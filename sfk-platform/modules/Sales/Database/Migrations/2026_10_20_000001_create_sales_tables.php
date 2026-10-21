<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales & receivables (SYSTEM_BRAIN 06 売上入力, 10 納品書管理(売上), 11 請求書管理(売上), 21 売入金管理（売上）,
 * 23 売掛管理, 23-2 売掛管理(締日別); BP-06/07/08).
 *
 * - `sales`: one sale line of a job (注番) for a customer. 売上No = the record number, 7 digits (legacy delete flow of
 *   06_SalesEntry.bundle.js looks sales up by 売上入力ID padded to 7). 売上金額 is whole yen; 消費税 is the line tax
 *   (BR-TX-05). 締日 / 端数区分 / 納品書・請求書送付先 of the customer are copied when the customer is chosen (legacy
 *   lookup). `billing_month` is the 月度 (BR-AR-01/02), the key of the cycle receivables.
 * - `delivery_notes` / `invoices`: documents with their rows copied from the sales (snapshots, BR-SA-15); the totals
 *   are recalculated from the rows on save (tax per rate on the document, BR-TX-04).
 * - `receipts`: 入金 per invoice with rows (入金 + 手数料 + 歩引き settle, BR-AR-04).
 * - `receivable_months`: 売掛 per customer × month, `basis` calendar (23) or cycle (23-2). Recalculated from the
 *   sales and receipts (never incremented); only 消費税端数調整 is entered by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('number', 64)->unique();
            $table->string('send_status', 8)->default('未');
            $table->string('registration_number', 32)->nullable();
            $table->date('sold_on')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->date('billing_due_on')->nullable();
            $table->string('postal_code', 8)->nullable();
            $table->string('address')->nullable();
            $table->string('closing_day', 8)->nullable();
            $table->string('customer_rounding', 8)->nullable();
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->text('notes')->nullable();
            $table->jsonb('pdf_files')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('delivery_note_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['delivery_note_id', 'user_id']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('number', 64)->unique();
            $table->string('send_status', 8)->default('未');
            $table->string('kind', 8)->default('通常請求');
            $table->string('registration_number', 32)->nullable();
            $table->date('invoiced_on')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('closing_day', 8)->nullable();
            $table->string('billing_month', 7)->nullable()->index();
            $table->string('postal_code', 8)->nullable();
            $table->string('address')->nullable();
            $table->string('customer_rounding', 8)->nullable();
            $table->bigInteger('previous_amount')->default(0);
            $table->bigInteger('received_amount')->default(0);
            $table->bigInteger('carried_amount')->default(0);
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('billed_total')->default(0);
            $table->text('notes')->nullable();
            $table->jsonb('pdf_files')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['invoice_id', 'user_id']);
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('sales_number', 64)->nullable()->unique();
            $table->string('kind', 16)->default('通常売上');
            $table->date('sold_on');
            $table->foreignId('customer_id')->constrained('partners')->restrictOnDelete();
            $table->string('closing_day', 8)->nullable();
            $table->string('closing_day_override', 8)->nullable();
            $table->date('billing_date')->nullable();
            $table->date('delivery_due_on');
            $table->string('billing_month', 7)->index();
            $table->string('customer_rounding', 8)->nullable();
            $table->string('delivery_note_recipient', 8)->nullable();
            $table->string('invoice_recipient', 8)->nullable();
            $table->boolean('other_address')->default(false);
            $table->string('manual_postal_code', 8)->nullable();
            $table->string('manual_address')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->decimal('quantity', 16, 4)->nullable();
            $table->string('unit', 16)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->bigInteger('amount');
            $table->bigInteger('estimated_cost')->nullable();
            $table->bigInteger('estimated_expense')->nullable();
            $table->string('tax_rate', 8)->default('10%');
            $table->bigInteger('tax')->default(0);
            $table->string('summary')->nullable();
            $table->jsonb('needs')->nullable();
            $table->jsonb('handover')->nullable();
            $table->boolean('locked')->default(false);
            $table->foreignId('delivery_note_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'sold_on']);
        });

        Schema::create('sale_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['sale_id', 'user_id']);
        });

        Schema::create('sale_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('ratio', 7, 4)->nullable();
            $table->timestamps();
        });

        foreach (['delivery_note_lines' => 'delivery_notes', 'invoice_lines' => 'invoices'] as $lines => $documents) {
            Schema::create($lines, function (Blueprint $table) use ($documents, $lines): void {
                $table->id();
                $table->foreignId(str($documents)->singular()->append('_id')->toString())->constrained($documents)->cascadeOnDelete();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
                $table->date('line_date')->nullable();
                if ($lines === 'invoice_lines') {
                    $table->string('slip_number', 64)->nullable();
                }
                $table->string('sales_number', 64)->nullable();
                $table->boolean('is_fsc')->default(false);
                $table->string('product_name')->nullable();
                $table->string('summary')->nullable();
                $table->decimal('quantity', 16, 4)->nullable();
                $table->string('unit', 16)->nullable();
                $table->decimal('unit_price', 14, 4)->nullable();
                $table->bigInteger('amount')->default(0);
                $table->string('tax_rate', 8)->default('10%');
                $table->string('order_number', 64)->nullable();
                $table->timestamps();
            });
        }

        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('invoice_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained('partners')->restrictOnDelete();
            $table->date('billed_on')->nullable();
            $table->bigInteger('billed_amount')->default(0);
            $table->string('closing_day', 8)->nullable();
            $table->bigInteger('settled_amount')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('receipt_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['receipt_id', 'user_id']);
        });

        Schema::create('receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->date('received_on')->nullable();
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('fee')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->string('method', 8)->nullable();
            $table->string('bank', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('receivable_months', function (Blueprint $table): void {
            $table->id();
            $table->string('basis', 8);
            $table->foreignId('customer_id')->constrained('partners')->cascadeOnDelete();
            $table->string('year_month', 7);
            $table->date('month_start');
            $table->string('closing_day', 8)->nullable();
            $table->bigInteger('previous_balance')->default(0);
            $table->bigInteger('received')->default(0);
            $table->bigInteger('net_balance')->default(0);
            $table->bigInteger('sales_amount')->default(0);
            $table->bigInteger('sales_standard')->default(0);
            $table->bigInteger('sales_reduced')->default(0);
            $table->bigInteger('sales_exempt')->default(0);
            $table->bigInteger('sales_out_of_scope')->default(0);
            $table->bigInteger('tax_standard')->default(0);
            $table->bigInteger('tax_reduced')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('tax_adjustment')->default(0);
            $table->bigInteger('month_total')->default(0);
            $table->bigInteger('closing_balance')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['basis', 'customer_id', 'year_month']);
            $table->index(['basis', 'year_month']);
        });
    }

    public function down(): void
    {
        foreach (['receivable_months', 'receipt_lines', 'receipt_user', 'receipts', 'invoice_lines', 'delivery_note_lines', 'sale_allocations',
            'sale_user', 'sales', 'invoice_user', 'invoices', 'delivery_note_user', 'delivery_notes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
