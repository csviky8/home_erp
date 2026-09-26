<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category')->default('electronics');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->string('vendor')->nullable();
            $table->string('location')->nullable();
            $table->string('condition')->default('good');
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'category']);
            $table->index(['warranty_expiry', 'condition']);
        });

        Schema::create('maintenance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category')->default('general');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->decimal('actual_cost', 14, 2)->nullable();
            $table->date('scheduled_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'status', 'priority']);
            $table->index(['scheduled_date', 'status']);
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('pending');
            $table->string('recurrence')->nullable();
            $table->json('recurrence_rules')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'status', 'due_at']);
            $table->index(['assigned_user_id', 'status']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('task_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('task_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category')->default('groceries');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->string('unit', 20)->default('pcs');
            $table->decimal('minimum_quantity', 12, 2)->default(0);
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->string('vendor')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'category']);
            $table->index(['expiry_date', 'quantity']);
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type', 20);
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->date('movement_date');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['inventory_item_id', 'movement_date']);
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category')->default('other');
            $table->string('description')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->date('expiry_date')->nullable();
            $table->date('reminder_date')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'category']);
            $table->index(['expiry_date', 'reminder_date']);
        });

        Schema::create('documentables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->timestamps();
            $table->unique(['document_id', 'documentable_type', 'documentable_id'], 'documentables_unique');
            $table->index(['documentable_type', 'documentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentables');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('maintenance_requests');
        Schema::dropIfExists('assets');
    }
};
