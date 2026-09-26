<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('household_id')->nullable()->index()->after('id');
            $table->string('phone', 25)->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('avatar_path');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('module', 60);
            $table->string('record_type')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['household_id', 'created_at']);
            $table->index(['module', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('notifications');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['household_id', 'phone', 'avatar_path', 'is_active', 'last_login_at']);
        });
    }
};
