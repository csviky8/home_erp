<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->string('currency', 3)->default('INR');
            $table->text('address')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('name');
            $table->string('color', 7)->default('#6ee7b7');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['household_id', 'type', 'name']);
            $table->index(['type', 'is_active']);
        });

        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('property_type')->default('apartment');
            $table->string('ownership_status')->default('owned');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_value', 14, 2)->nullable();
            $table->decimal('current_value', 14, 2)->nullable();
            $table->string('tenant_name')->nullable();
            $table->string('tenant_email')->nullable();
            $table->string('tenant_mobile')->nullable();
            $table->decimal('monthly_rent', 14, 2)->nullable();
            $table->decimal('deposit_amount', 14, 2)->nullable();
            $table->date('agreement_start')->nullable();
            $table->date('agreement_end')->nullable();
            $table->unsignedTinyInteger('rent_due_day')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'ownership_status']);
        });

        Schema::create('family_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('role_label')->nullable();
            $table->json('permissions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'relationship']);
        });

        Schema::create('service_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('service_type');
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'service_type']);
        });

        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('period_type', 10)->default('monthly');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 14, 2);
            $table->unsignedTinyInteger('alert_percent')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['household_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('service_providers');
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('households');
    }
};
