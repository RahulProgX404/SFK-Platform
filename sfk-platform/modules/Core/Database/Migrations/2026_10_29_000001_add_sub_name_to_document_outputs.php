<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * サブボタン名 of a 出力設定: 出力設定 sharing a ボタン名 become one button whose menu lists them by サブボタン名 (e.g. the
 * legacy 作業明細表 of 04. 受注入力 as 注文書 / 営業控 / 原稿添付用, SYSTEM_BRAIN 08_PRINTCREATOR). '' = none; a ボタン名
 * is unique together with its サブボタン名.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_outputs', function (Blueprint $table): void {
            $table->string('sub_name', 64)->default('')->after('name');
        });

        Schema::table('document_outputs', function (Blueprint $table): void {
            $table->dropUnique(['business_app_id', 'name']);
            $table->unique(['business_app_id', 'name', 'sub_name']);
        });
    }

    public function down(): void
    {
        Schema::table('document_outputs', function (Blueprint $table): void {
            $table->dropUnique(['business_app_id', 'name', 'sub_name']);
            $table->unique(['business_app_id', 'name']);
        });

        Schema::table('document_outputs', function (Blueprint $table): void {
            $table->dropColumn('sub_name');
        });
    }
};
