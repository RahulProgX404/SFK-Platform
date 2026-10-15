<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01. 取引先マスタ（得意先・仕入先） (SYSTEM_BRAIN APP_01): one partner entity with role flags and both customer-side
 * and supplier-side terms. Option fields store the legacy label (enums in Domain/Partners and Core).
 *
 * Not carried over: the address split fields (都道府県/市区町村/町名 — plugin-filled, derivable), 前回支払残高 /
 * 前回買掛残高 (stored balances; AR/AP are derived from transactions) and フラグ (meaning unknown).
 * `legacy_id` keeps the 旧システム得意先ID (record number) for migration and traceability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();

            // Header (BR-MD-01: 取引先名 and 取引先コード are unique)
            $table->string('code', 64)->nullable()->unique();
            $table->string('parent_company', 128)->nullable();
            $table->string('name', 64)->unique();
            $table->string('short_name', 64);
            $table->string('registration_number', 14)->nullable();

            // 取引先カテゴリ (BR-MD-02)
            $table->boolean('is_customer')->default(false)->index();
            $table->boolean('is_supplier')->default(false)->index();
            $table->boolean('is_other')->default(false);

            // 住所/連絡先: parent company and customer
            $table->string('parent_postal_code', 8)->nullable();
            $table->string('parent_address')->nullable();
            $table->string('parent_phone', 32)->nullable();
            $table->string('parent_fax', 32)->nullable();
            $table->string('postal_code', 8)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('fax', 32)->nullable();
            $table->boolean('supplier_same_address')->default(false);
            $table->jsonb('contract_files')->nullable();
            $table->jsonb('supplier_contract_files')->nullable();

            // 基本情報 (customer side)
            $table->string('customer_status', 16)->nullable();
            $table->string('customer_classification', 16)->nullable();
            $table->string('legacy_customer_code', 64)->nullable();
            $table->string('partner_group', 32)->nullable();
            $table->string('segment', 16)->nullable();
            $table->string('invoice_recipient', 8)->default('得意先名');
            $table->string('delivery_note_recipient', 8)->default('得意先名');
            $table->string('contact_person', 64)->nullable();
            $table->text('contact_note')->nullable();
            $table->string('tax_mode', 4)->nullable();
            $table->string('rounding', 8)->nullable();
            $table->string('tax_calculation_unit', 4)->nullable();
            $table->string('closing_day', 8)->nullable();
            $table->string('invoice_issuance', 16)->nullable();
            $table->string('delivery_note_issuance', 16)->nullable();
            $table->boolean('electronic_billing')->default(false);
            $table->string('collection_month', 8)->nullable();
            $table->string('collection_day', 8)->nullable();
            $table->boolean('collects_by_bill')->default(false);
            $table->unsignedInteger('bill_threshold_man')->nullable();
            $table->unsignedSmallInteger('bill_site_days')->nullable();
            $table->text('notes')->nullable();

            // 仕入先情報 (supplier side)
            $table->string('purchase_category', 32)->nullable();
            $table->string('supplier_tax_mode', 4)->nullable();
            $table->string('supplier_rounding', 8)->nullable();
            $table->string('supplier_tax_calculation_unit', 4)->nullable();
            $table->string('supplier_contact_person', 64)->nullable();
            $table->string('supplier_postal_code', 8)->nullable();
            $table->string('supplier_address')->nullable();
            $table->string('supplier_phone', 32)->nullable();
            $table->string('supplier_fax', 32)->nullable();
            $table->text('supplier_notes')->nullable();

            // 開発者用フィールド
            $table->string('mf_partner_id', 64)->nullable();
            $table->string('legacy_supplier_code', 64)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 自社担当者 (legacy user selection, several allowed)
        Schema::create('partner_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['partner_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_staff');
        Schema::dropIfExists('partners');
    }
};
