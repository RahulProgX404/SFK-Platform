<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organizations (部署, nestable), positions defined per organization (役職) and user memberships.
 * A user may belong to several organizations, at most once each, optionally with a position.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('code', 32)->nullable()->unique();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('code', 32)->nullable();
            $table->unsignedSmallInteger('rank')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('organization_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('organizations');
    }
};
