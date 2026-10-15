<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The home page (ポータル) of each dashboard, like the legacy portal: the お知らせ (rich text, edited by
 * administrators) and the アプリショートカット tiles. Apps get an optional short icon text for their tiles
 * (e.g. 「受注」 for 04. 受注入力).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_portals', function (Blueprint $table): void {
            $table->id();
            $table->string('panel_id', 64)->unique();
            $table->text('notice')->nullable();
            $table->jsonb('shortcut_app_ids')->nullable();
            $table->timestamps();
        });

        Schema::table('business_apps', function (Blueprint $table): void {
            $table->string('icon_text', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('business_apps', function (Blueprint $table): void {
            $table->dropColumn('icon_text');
        });

        Schema::dropIfExists('dashboard_portals');
    }
};
