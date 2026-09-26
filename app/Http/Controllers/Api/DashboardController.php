<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\CalendarEvent;
use App\Models\Expense;
use App\Models\InsurancePolicy;
use App\Models\InventoryItem;
use App\Models\MaintenanceRequest;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        date_default_timezone_set($user->household?->timezone ?: config('app.timezone'));
        $cacheKey = 'dashboard:v2:'.$user->id.':'.($user->household_id ?? 'all');
        if (! $request->boolean('refresh') && Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }
        $today = now();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();
        $expenseQuery = Expense::query()->forUser($user);
        $monthlyExpenses = (clone $expenseQuery)->whereBetween('expense_date', [$monthStart, $monthEnd])->sum('amount');
        $yearlyExpenses = (clone $expenseQuery)->whereBetween('expense_date', [$yearStart, $yearEnd])->sum('amount');
        $budget = Budget::query()->forUser($user)->where('is_active', true)->whereNull('category_id')->whereDate('period_start', '<=', $today)->whereDate('period_end', '>=', $today)->first();
        $budgetAmount = (float) ($budget->amount ?? 0);
        $upcomingBills = Bill::query()->forUser($user)->whereIn('status', ['upcoming', 'due', 'overdue'])->whereDate('due_date', '<=', $today->copy()->addDays(30))->orderBy('due_date')->limit(8)->get(['id', 'name', 'amount', 'due_date', 'status']);
        $subscriptions = Subscription::query()->forUser($user)->where('status', 'active')->whereDate('renewal_date', '<=', $today->copy()->addDays(30))->orderBy('renewal_date')->limit(6)->get(['id', 'name', 'amount', 'renewal_date']);
        $insurance = InsurancePolicy::query()->forUser($user)->where('status', 'active')->whereDate('renewal_date', '<=', $today->copy()->addDays(60))->orderBy('renewal_date')->limit(6)->get(['id', 'policy_type', 'provider_name', 'premium', 'renewal_date']);
        $vehicles = Vehicle::query()->forUser($user)->whereDate('next_service_date', '<=', $today->copy()->addDays(45))->orderBy('next_service_date')->limit(6)->get(['id', 'vehicle_number', 'brand', 'model', 'next_service_date']);
        $warranties = Asset::query()->forUser($user)->whereNotNull('warranty_expiry')->whereDate('warranty_expiry', '<=', $today->copy()->addDays(60))->orderBy('warranty_expiry')->limit(6)->get(['id', 'name', 'category', 'warranty_expiry']);
        $lowStock = InventoryItem::query()->forUser($user)->whereColumn('quantity', '<=', 'minimum_quantity')->orderBy('quantity')->limit(8)->get(['id', 'name', 'category', 'quantity', 'unit', 'minimum_quantity']);
        $events = CalendarEvent::query()->forUser($user)->where('status', 'scheduled')->whereBetween('starts_at', [$today->copy()->startOfDay(), $today->copy()->addDays(14)->endOfDay()])->orderBy('starts_at')->limit(8)->get(['id', 'title', 'starts_at', 'event_type']);
        $maintenance = MaintenanceRequest::query()->forUser($user)->whereNotIn('status', ['completed', 'cancelled'])->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")->limit(6)->get(['id', 'title', 'priority', 'status', 'scheduled_date']);
        $tasks = Task::query()->forUser($user)->whereIn('status', ['pending', 'in-progress'])->orderBy('due_at')->limit(6)->get(['id', 'title', 'priority', 'status', 'due_at']);

        $payload = [
            'currency' => $user->household?->currency ?? 'INR',
            'metrics' => [
                'monthly_expenses' => $monthlyExpenses, 'yearly_expenses' => $yearlyExpenses, 'monthly_budget' => $budgetAmount, 'budget_remaining' => max(0, $budgetAmount - (float) $monthlyExpenses),
                'upcoming_bills_count' => $upcomingBills->count(), 'upcoming_bills_amount' => $upcomingBills->sum('amount'), 'pending_payments' => Bill::query()->forUser($user)->whereIn('status', ['upcoming', 'due', 'overdue'])->sum('amount'),
                'open_maintenance' => MaintenanceRequest::query()->forUser($user)->whereNotIn('status', ['completed', 'cancelled'])->count(), 'pending_tasks' => Task::query()->forUser($user)->whereIn('status', ['pending', 'in-progress'])->count(),
                'upcoming_subscriptions' => Subscription::query()->forUser($user)->where('status', 'active')->whereDate('renewal_date', '<=', $today->copy()->addDays(30))->count(), 'insurance_renewals' => InsurancePolicy::query()->forUser($user)->whereDate('renewal_date', '<=', $today->copy()->addDays(60))->count(),
                'vehicle_services' => Vehicle::query()->forUser($user)->whereDate('next_service_date', '<=', $today->copy()->addDays(45))->count(), 'warranty_expiries' => Asset::query()->forUser($user)->whereNotNull('warranty_expiry')->whereDate('warranty_expiry', '<=', $today->copy()->addDays(60))->count(), 'low_inventory' => InventoryItem::query()->forUser($user)->whereColumn('quantity', '<=', 'minimum_quantity')->count(),
            ],
            'alerts' => array_merge(compact('upcomingBills', 'subscriptions', 'insurance', 'vehicles', 'warranties', 'lowStock', 'events', 'maintenance', 'tasks'), ['bills' => $upcomingBills, 'inventory' => $lowStock]),
            'charts' => $this->charts($user),
            'recent_activity' => ActivityLog::query()->forUser($user)->with('user:id,name')->latest('created_at')->limit(8)->get(['id', 'action', 'module', 'record_id', 'user_id', 'created_at']),
        ];
        Cache::put($cacheKey, $payload, now()->addSeconds(15));

        return response()->json($payload);
    }

    private function charts(User $user): array
    {
        $months = collect(range(11, 0))->map(function ($offset) {
            $date = now()->subMonthsNoOverflow($offset);
            return ['label' => $date->format('M y'), 'year' => $date->year, 'month' => $date->month];
        });
        $expenseRows = \App\Models\Expense::query()->forUser($user)->whereBetween('expense_date', [$months->first()['year'].'-'.$months->first()['month'].'-01', now()->endOfMonth()])->get(['expense_date', 'amount']);
        $monthly = $months->map(fn ($item) => ['label' => $item['label'], 'value' => (float) $expenseRows->filter(fn ($row) => $row->expense_date->year === $item['year'] && $row->expense_date->month === $item['month'])->sum('amount')]);
        $categoryRows = \App\Models\Expense::query()->forUser($user)->whereYear('expense_date', now()->year)->selectRaw('category_id, SUM(amount) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $categories = \App\Models\Category::query()->forUser($user)->where('type', 'expense')->pluck('name', 'id');
        $categoryBreakdown = $categoryRows->map(fn ($total, $id) => ['label' => $categories[$id] ?? 'Other', 'value' => (float) $total])->values();
        $maintenanceRows = \App\Models\MaintenanceRequest::query()->forUser($user)->whereNotNull('actual_cost')->whereYear('completion_date', now()->year)->get(['completion_date', 'actual_cost'])->groupBy(fn ($row) => (int) $row->completion_date->format('n'))->map(fn ($rows) => $rows->sum('actual_cost'));
        $maintenance = collect(range(1, 12))->map(fn ($month) => ['label' => now()->month($month)->format('M'), 'value' => (float) ($maintenanceRows[$month] ?? 0)]);

        return ['monthly_expenses' => $monthly, 'category_breakdown' => $categoryBreakdown, 'maintenance_expenses' => $maintenance];
    }
}

