<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 分析 → 売上分析 (legacy SalesAnalysis.bundle.js, 担当者別 売上ダッシュボード): the figures typed in per 担当者 and business
 * year (May–April): 予想 (`forecast_m05` …) and 仕掛 (`wip_m05` …). The actual sales are not stored — they are summed
 * from 06. 売上入力 when the screen is drawn.
 */
return new class extends Migration
{
    private const MONTHS = ['m05', 'm06', 'm07', 'm08', 'm09', 'm10', 'm11', 'm12', 'm01', 'm02', 'm03', 'm04'];

    public function up(): void
    {
        Schema::create('sales_forecasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            foreach (['forecast', 'wip'] as $kind) {
                foreach (self::MONTHS as $month) {
                    $table->bigInteger($kind.'_'.$month)->default(0);
                }
            }
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_forecasts');
    }
};
