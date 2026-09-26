<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vehicle_number')->unique();
            $table->string('vehicle_type')->default('car');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->string('fuel_type')->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->date('next_service_date')->nullable();
            $table->date('puc_expiry')->nullable();
            $table->date('rc_expiry')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'status']);
        });

        Schema::create('vehicle_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date');
            $table->unsignedInteger('odometer_km')->nullable();
            $table->string('service_type')->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->date('next_service_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'service_date']);
        });

        Schema::create('vehicle_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('expense_date');
            $table->string('expense_type')->default('fuel');
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('odometer_km')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'expense_date']);
        });

        Schema::create('security_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('device_type');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->date('installed_on')->nullable();
            $table->date('next_maintenance_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'status']);
        });

        Schema::create('security_maintenances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('security_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date');
            $table->decimal('cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('visitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_name');
            $table->string('phone')->nullable();
            $table->string('purpose')->nullable();
            $table->dateTime('visited_at');
            $table->dateTime('checked_out_at')->nullable();
            $table->string('status', 20)->default('checked_in');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'visited_at']);
        });

        Schema::create('plants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('plant_type')->nullable();
            $table->string('location')->nullable();
            $table->date('planted_at')->nullable();
            $table->unsignedSmallInteger('watering_frequency_days')->default(3);
            $table->date('last_watered_at')->nullable();
            $table->date('next_watering_at')->nullable();
            $table->string('health_status')->default('healthy');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'health_status']);
            $table->index(['next_watering_at']);
        });

        Schema::create('garden_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plant_id')->nullable()->constrained()->nullOnDelete();
            $table->date('expense_date');
            $table->string('expense_type')->default('supplies');
            $table->decimal('amount', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['plant_id', 'expense_date']);
        });

        Schema::create('pets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('pet_type')->default('dog');
            $table->string('breed')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('microchip_number')->nullable();
            $table->string('veterinarian')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'pet_type']);
        });

        Schema::create('pet_health_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('record_type')->default('medical');
            $table->string('title');
            $table->date('record_date');
            $table->date('next_due_date')->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->string('provider_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['pet_id', 'record_date']);
        });

        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('event_type')->default('family');
            $table->string('source_module')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('recurrence')->nullable();
            $table->json('recurrence_rules')->nullable();
            $table->unsignedInteger('reminder_minutes')->default(1440);
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'starts_at']);
            $table->index(['source_module', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('pet_health_records');
        Schema::dropIfExists('pets');
        Schema::dropIfExists('garden_expenses');
        Schema::dropIfExists('plants');
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('security_maintenances');
        Schema::dropIfExists('security_devices');
        Schema::dropIfExists('vehicle_expenses');
        Schema::dropIfExists('vehicle_services');
        Schema::dropIfExists('vehicles');
    }
};
