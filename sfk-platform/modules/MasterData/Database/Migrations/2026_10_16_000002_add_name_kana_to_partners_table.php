<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 取引先名(ふりがな) (new, not in the legacy app): required on the form, nullable here so partners taken over from
 * the legacy data can be filled in later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table): void {
            $table->string('name_kana', 128)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table): void {
            $table->dropColumn('name_kana');
        });
    }
};
