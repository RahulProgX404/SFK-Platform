<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 会社カレンダー: one row per marked day (祝日 / 会社休日 / 半日出勤). Unmarked days are working days; Sundays are
 * closed without a row (Domain\Calendar\CalendarDayType::effective).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_calendar_days', function (Blueprint $table): void {
            $table->id();
            $table->date('day')->unique();
            $table->string('type', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_calendar_days');
    }
};
