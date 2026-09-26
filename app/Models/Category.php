<?php

namespace App\Models;

class Category extends HouseholdModel
{
    protected $table = 'categories';

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }
}
