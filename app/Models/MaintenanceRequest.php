<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRequest extends HouseholdModel
{
    use SoftDeletes;

    public function permissionPrefix(): string { return 'maintenance'; }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['scheduled_date' => 'date', 'completion_date' => 'date', 'estimated_cost' => 'decimal:2', 'actual_cost' => 'decimal:2', 'attachments' => 'array']);
    }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
}
