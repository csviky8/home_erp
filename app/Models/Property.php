<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends HouseholdModel
{
    use SoftDeletes;

    protected $table = 'properties';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'purchase_date' => 'date', 'agreement_start' => 'date', 'agreement_end' => 'date',
            'purchase_value' => 'decimal:2', 'current_value' => 'decimal:2', 'monthly_rent' => 'decimal:2', 'deposit_amount' => 'decimal:2',
        ]);
    }

    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
    public function bills(): HasMany { return $this->hasMany(Bill::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
}
