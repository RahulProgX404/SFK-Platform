<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics (SYSTEM_BRAIN 22 過去の売上データ, 25 販売先別売上計画). Business years run May–April (`m05` … `m04`).
 *
 * - `past_sales`: historical sales per 担当者 (a name; the people of the history are not all users) and business year
 *   (決算最終月初), with the anomaly flag; 年間合計 is kept for lists and charts.
 * - `sales_plans`: plan and actual per customer and business year (日付): 計画値, the monthly plans (`plan_m05` …) and
 *   actuals (`actual_m05` …); 年間合計 of the actuals is kept for lists.
 */
return new class extends Migration
{
    private const MONTHS = ['m05', 'm06', 'm07', 'm08', 'm09', 'm10', 'm11', 'm12', 'm01', 'm02', 'm03', 'm04'];

    public function up(): void
    {
        Schema::create('past_sales', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('salesperson')->nullable();
            $table->string('flag', 4)->default('正常');
            $table->date('fiscal_month_start')->nullable();
            foreach (self::MONTHS as $month) {
                $table->bigInteger($month)->nullable();
            }
            $table->bigInteger('total')->default(0);
            $table->string('record_key')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['salesperson', 'fiscal_month_start']);
        });

        Schema::create('sales_plans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->string('sub_category')->nullable();
            $table->date('planned_on')->nullable();
            $table->bigInteger('plan_total')->nullable();
            foreach (['plan', 'actual'] as $kind) {
                foreach (self::MONTHS as $month) {
                    $table->bigInteger($kind.'_'.$month)->nullable();
                }
            }
            $table->bigInteger('actual_total')->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'planned_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_plans');
        Schema::dropIfExists('past_sales');
    }
};
