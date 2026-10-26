<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes on the foreign keys: PostgreSQL (and SQLite) do not index the referencing column of a foreign key, so
 * reading a record's rows, the related lists and every delete of a parent (the database checks the children) read
 * the whole child table. Tables of modules that are switched off are skipped. New foreign keys get their index in
 * their own migration (`->index()`), checked by Modules\Core\Tests\Feature\DatabaseIndexTest.
 */
return new class extends Migration
{
    /** table => foreign key columns without an index (2026-10-26) */
    private const FOREIGN_KEYS = [
        'users' => ['position_id'],
        'role_has_permissions' => ['role_id'],
        'space_user' => ['user_id'],
        'app_permissions' => ['employee_category_id', 'user_id'],
        'employee_category_user' => ['user_id'],
        'partners' => ['created_by', 'updated_by'],
        'partner_staff' => ['user_id'],
        'app_list_views' => ['created_by'],
        'record_versions' => ['user_id'],
        'record_comments' => ['user_id'],
        'machines' => ['created_by', 'updated_by'],
        'orders' => ['created_by', 'customer_id', 'data_supplier_id', 'designer_id', 'film_supplier_id', 'machine_id', 'manufacturer_id', 'plate_supplier_id', 'updated_by'],
        'order_assignees' => ['user_id'],
        'order_papers' => ['order_id', 'supplier_id'],
        'order_printings' => ['order_id', 'supplier_id'],
        'order_finishings' => ['order_id', 'supplier_id'],
        'consumables' => ['created_by', 'supplier_id', 'updated_by'],
        'consumable_stock_movements' => ['created_by'],
        'delivery_notes' => ['created_by', 'customer_id', 'updated_by'],
        'delivery_note_user' => ['user_id'],
        'invoices' => ['created_by', 'customer_id', 'updated_by'],
        'invoice_user' => ['user_id'],
        'sales' => ['created_by', 'delivery_note_id', 'invoice_id', 'order_id', 'updated_by'],
        'sale_user' => ['user_id'],
        'sale_allocations' => ['sale_id', 'user_id'],
        'delivery_note_lines' => ['delivery_note_id', 'sale_id'],
        'invoice_lines' => ['invoice_id', 'sale_id'],
        'receipts' => ['created_by', 'customer_id', 'updated_by'],
        'receipt_user' => ['user_id'],
        'receipt_lines' => ['receipt_id'],
        'receivable_months' => ['created_by', 'customer_id', 'updated_by'],
        'purchases' => ['created_by', 'order_id', 'updated_by'],
        'purchase_user' => ['user_id'],
        'payments' => ['created_by', 'updated_by'],
        'payment_lines' => ['payment_id'],
        'freight_charges' => ['created_by', 'order_id', 'purchase_id', 'updated_by'],
        'consumable_orders' => ['created_by', 'supplier_id', 'updated_by'],
        'consumable_order_user' => ['user_id'],
        'consumable_order_lines' => ['consumable_id', 'consumable_order_id'],
        'payable_months' => ['created_by', 'updated_by'],
        'leave_requests' => ['created_by', 'updated_by', 'worker_id'],
        'safety_checks' => ['created_by', 'employee_id', 'updated_by', 'user_id'],
        'safety_check_items' => ['safety_check_id'],
        'alcohol_checks' => ['after_checker_id', 'before_checker_id', 'created_by', 'employee_id', 'updated_by'],
        'quotations' => ['created_by', 'customer_id', 'updated_by'],
        'quotation_user' => ['user_id'],
        'quotation_lines' => ['quotation_id'],
        'past_sales' => ['created_by', 'updated_by'],
        'sales_plans' => ['created_by', 'updated_by'],
        'daily_reports' => ['cardboard_id', 'created_by', 'machine_id', 'partition_id', 'updated_by'],
        'bag_schedules' => ['created_by', 'order_id', 'updated_by'],
        'accident_reports' => ['created_by', 'machine_id', 'maker1_id', 'maker2_id', 'order_id', 'reporter_id', 'updated_by'],
        'accident_damages' => ['accident_report_id'],
        'accident_report_user' => ['user_id'],
        'business_app_space' => ['space_id'],
        'app_record_rule_entries' => ['app_record_rule_id', 'employee_category_id', 'user_id'],
        'app_field_permissions' => ['employee_category_id', 'user_id'],
        'document_templates' => ['created_by', 'updated_by'],
        'document_outputs' => ['document_template_id'],
    ];

    public function up(): void
    {
        foreach (self::FOREIGN_KEYS as $table => $columns) {
            $columns = array_values(array_filter($columns, fn (string $column): bool => Schema::hasTable($table) && Schema::hasColumn($table, $column)));
            if ($columns === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::FOREIGN_KEYS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_column(Schema::getIndexes($table), 'name');
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns, $existing): void {
                foreach ($columns as $column) {
                    if (in_array($table.'_'.$column.'_index', $existing, true)) {
                        $blueprint->dropIndex([$column]);
                    }
                }
            });
        }
    }
};
