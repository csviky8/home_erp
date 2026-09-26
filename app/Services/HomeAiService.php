<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\InsurancePolicy;
use App\Models\InventoryItem;
use App\Models\MaintenanceRequest;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class HomeAiService
{
    public function answer(User $user, string $question): array
    {
        $q = mb_strtolower(trim($question));
        $context = $this->localContext($user, $q);

        if (config('ai.openai_key') && ! str_contains($q, 'local only')) {
            $response = Http::withToken(config('ai.openai_key'))->timeout(20)->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('ai.model'), 'temperature' => 0.2, 'max_tokens' => 350,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are the Home ERP assistant. Answer only from the supplied household context. Never invent data, never request secrets, and keep permission-scoped data private.'],
                    ['role' => 'user', 'content' => "Question: {$question}\nAuthorized context: ".json_encode($context)],
                ],
            ]);
            if ($response->successful()) {
                $text = data_get($response->json(), 'choices.0.message.content');
                if (filled($text)) return ['message' => trim($text), 'context' => $context];
            }
        }

        return ['message' => $context['message'], 'context' => $context];
    }

    private function localContext(User $user, string $q): array
    {
        $today = now();
        if (str_contains($q, 'ac') && (str_contains($q, 'service') || str_contains($q, 'serviced')) && $user->can('maintenance.view')) {
            $record = MaintenanceRequest::query()->forUser($user)->where(function ($query): void {
                $query->where('title', 'like', '%AC%')->orWhere('description', 'like', '%AC%')->orWhereHas('asset', fn ($asset) => $asset->where('name', 'like', '%AC%'));
            })->orderByRaw('CASE WHEN status = "completed" THEN 0 ELSE 1 END')->orderByDesc('completion_date')->orderByDesc('updated_at')->first();
            return ['intent' => 'asset_service', 'message' => $record?->completion_date ? 'Your AC was last serviced on '.$record->completion_date.'.' : 'I could not find a completed AC service record yet.', 'record' => $record];
        }

        if (str_contains($q, 'bill') && (str_contains($q, 'due') || str_contains($q, 'week'))) {
            if (! $user->can('bills.view')) return $this->denied('bills');
            $bills = Bill::query()->forUser($user)->whereIn('status', ['upcoming', 'due', 'overdue'])->whereBetween('due_date', [$today->copy()->startOfDay(), $today->copy()->addWeek()->endOfDay()])->orderBy('due_date')->get(['name', 'amount', 'due_date', 'status']);
            $lines = $bills->map(fn ($bill) => "• {$bill->name}: {$bill->amount} due {$bill->due_date}")->implode("\n");
            return ['intent' => 'bills_due', 'message' => $lines ? "Bills due this week:\n{$lines}" : 'No bills are due this week.', 'bills' => $bills];
        }

        if (str_contains($q, 'spend') || str_contains($q, 'expense')) {
            if (! $user->can('expenses.view')) return $this->denied('expenses');
            $start = str_contains($q, 'year') || str_contains($q, 'yearly') ? $today->copy()->startOfYear() : $today->copy()->startOfMonth();
            $rows = Expense::query()->forUser($user)->where('expense_date', '>=', $start)->selectRaw('category_id, SUM(amount) as total')->groupBy('category_id')->orderByDesc('total')->get();
            $categories = \App\Models\Category::query()->forUser($user)->where('type', 'expense')->pluck('name', 'id');
            $total = $rows->sum('total');
            $top = $rows->take(3)->map(fn ($row) => ($categories[$row->category_id] ?? 'Other').' ('.number_format((float) $row->total, 2).')')->implode(', ');
            return ['intent' => 'expense_summary', 'message' => 'You spent '.number_format((float) $total, 2).' since '.$start->format('j M Y').'. Biggest categories: '.($top ?: 'none').'.', 'total' => $total, 'categories' => $rows];
        }

        if (str_contains($q, 'task') && $user->can('tasks.view')) {
            $tasks = Task::query()->forUser($user)->whereIn('status', ['pending', 'in-progress'])->orderBy('due_at')->limit(8)->get(['title', 'due_at', 'status']);
            return ['intent' => 'tasks', 'message' => $tasks->isEmpty() ? 'You have no pending household tasks.' : 'Pending tasks: '.$tasks->pluck('title')->implode(', '), 'tasks' => $tasks];
        }

        if (str_contains($q, 'stock') || str_contains($q, 'inventory')) {
            if (! $user->can('inventory.view')) return $this->denied('inventory');
            $items = InventoryItem::query()->forUser($user)->whereColumn('quantity', '<=', 'minimum_quantity')->limit(8)->get(['name', 'quantity', 'unit', 'minimum_quantity']);
            return ['intent' => 'inventory', 'message' => $items->isEmpty() ? 'Inventory looks healthy.' : 'Low stock: '.$items->map(fn ($item) => "{$item->name} ({$item->quantity} {$item->unit})")->implode(', '), 'items' => $items];
        }

        $renewals = [];
        if ($user->can('insurance.view')) $renewals = InsurancePolicy::query()->forUser($user)->whereDate('renewal_date', '<=', $today->copy()->addDays(30))->count();
        if ($user->can('subscriptions.view')) $renewals += Subscription::query()->forUser($user)->whereDate('renewal_date', '<=', $today->copy()->addDays(30))->count();
        return ['intent' => 'overview', 'message' => 'I can help with expenses, bills, maintenance, tasks, inventory, assets, renewals, and household planning. You have '.$renewals.' upcoming policy or subscription renewals in the next 30 days.'];
    }

    private function denied(string $module): array
    {
        return ['intent' => 'permission_denied', 'message' => 'You do not have permission to access '.$module.' data.'];
    }
}
