<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('INR');
            $table->date('expense_date');
            $table->string('payment_method')->default('cash');
            $table->string('paid_by')->nullable();
            $table->string('recurrence')->nullable();
            $table->date('recurrence_until')->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'expense_date']);
            $table->index(['category_id', 'expense_date']);
            $table->index(['user_id', 'expense_date']);
        });

        Schema::create('bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('account_number')->nullable();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('INR');
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->string('status', 20)->default('upcoming');
            $table->string('recurrence')->nullable();
            $table->unsignedSmallInteger('reminder_days')->default(3);
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'due_date', 'status']);
            $table->index(['service_provider_id', 'due_date']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_at');
            $table->string('payment_method')->default('bank');
            $table->string('reference_number')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();
            $table->index(['bill_id', 'paid_at']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('INR');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->date('start_date');
            $table->date('renewal_date');
            $table->string('payment_method')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'renewal_date', 'status']);
        });

        Schema::create('insurance_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('asset_id')->nullable()->index();
            $table->unsignedBigInteger('vehicle_id')->nullable()->index();
            $table->string('policy_number')->nullable();
            $table->string('policy_type');
            $table->string('provider_name')->nullable();
            $table->decimal('premium', 14, 2);
            $table->char('currency', 3)->default('INR');
            $table->date('start_date');
            $table->date('expiry_date');
            $table->date('renewal_date');
            $table->decimal('coverage_amount', 16, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'renewal_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_policies');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('expenses');
    }
};
