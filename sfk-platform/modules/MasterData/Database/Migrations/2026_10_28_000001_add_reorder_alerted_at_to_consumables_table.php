<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the 要発注 通知 of a consumable was sent (Modules\MasterData\Application\Consumables\ReorderAlert, BR-MD-05):
 * set while 発注判定 stays 要発注, cleared when the stock is above the 発注点 again — so the 通知 comes once each time
 * the stock falls to the 発注点.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumables', function (Blueprint $table): void {
            $table->timestamp('reorder_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consumables', function (Blueprint $table): void {
            $table->dropColumn('reorder_alerted_at');
        });
    }
};
