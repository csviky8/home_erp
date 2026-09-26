<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\Bill;
use App\Models\CalendarEvent;
use App\Models\Household;
use App\Models\InsurancePolicy;
use App\Models\InventoryItem;
use App\Models\MaintenanceRequest;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\ReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendReminderNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $today = now();
        Household::query()->with('users')->each(function (Household $household) use ($today): void {
            $users = $household->users()->where('is_active', true)->get();
            $this->sendBills($users, $today);
            $this->sendMaintenance($users, $today);
            $this->sendTasks($users, $today);
            $this->sendSubscriptions($users, $today);
            $this->sendInsurance($users, $today);
            $this->sendWarranties($users, $today);
            $this->sendInventory($users, $today);
            $this->sendVehicles($users, $today);
        });
    }

    private function sendBills($users, $today): void
    {
        Bill::query()->whereIn('status', ['upcoming', 'due', 'overdue'])->whereDate('due_date', '<=', $today->copy()->addDays(3))->each(function (Bill $bill) use ($users, $today): void {
            $this->notifyUsers($users, 'bills.view', 'bill_due', 'Bill due: '.$bill->name, $bill->name.' is due on '.$bill->due_date.'.', $bill->toArray(), $today);
        });
    }

    private function sendMaintenance($users, $today): void
    {
        MaintenanceRequest::query()->whereNotIn('status', ['completed', 'cancelled'])->whereDate('scheduled_date', '<=', $today->copy()->addDays(2))->each(fn (MaintenanceRequest $item) => $this->notifyUsers($users, 'maintenance.view', 'maintenance_due', 'Maintenance scheduled: '.$item->title, $item->title.' is scheduled for '.$item->scheduled_date.'.', $item->toArray(), $today));
    }

    private function sendTasks($users, $today): void
    {
        Task::query()->whereIn('status', ['pending', 'in-progress'])->whereDate('due_at', '<=', $today->copy()->addDays(2))->each(fn (Task $item) => $this->notifyUsers($users, 'tasks.view', 'task_due', 'Task due: '.$item->title, $item->title.' needs your attention.', $item->toArray(), $today));
    }

    private function sendSubscriptions($users, $today): void
    {
        Subscription::query()->where('status', 'active')->whereDate('renewal_date', '<=', $today->copy()->addDays(7))->each(fn (Subscription $item) => $this->notifyUsers($users, 'subscriptions.view', 'subscription_renewal', 'Subscription renewal: '.$item->name, $item->name.' renews on '.$item->renewal_date.'.', $item->toArray(), $today));
    }

    private function sendInsurance($users, $today): void
    {
        InsurancePolicy::query()->where('status', 'active')->whereDate('renewal_date', '<=', $today->copy()->addDays(30))->each(fn (InsurancePolicy $item) => $this->notifyUsers($users, 'insurance.view', 'insurance_renewal', 'Insurance renewal: '.$item->policy_type, 'Your '.$item->policy_type.' policy renews on '.$item->renewal_date.'.', $item->toArray(), $today));
    }

    private function sendWarranties($users, $today): void
    {
        Asset::query()->whereNotNull('warranty_expiry')->whereDate('warranty_expiry', '<=', $today->copy()->addDays(30))->each(fn (Asset $item) => $this->notifyUsers($users, 'assets.view', 'warranty_expiry', 'Warranty expiring: '.$item->name, $item->name.' warranty expires on '.$item->warranty_expiry.'.', $item->toArray(), $today));
    }

    private function sendInventory($users, $today): void
    {
        InventoryItem::query()->whereColumn('quantity', '<=', 'minimum_quantity')->each(fn (InventoryItem $item) => $this->notifyUsers($users, 'inventory.view', 'low_inventory', 'Low stock: '.$item->name, $item->name.' has only '.$item->quantity.' '.$item->unit.' remaining.', $item->toArray(), $today));
    }

    private function sendVehicles($users, $today): void
    {
        Vehicle::query()->whereDate('next_service_date', '<=', $today->copy()->addDays(14))->each(fn (Vehicle $item) => $this->notifyUsers($users, 'vehicles.view', 'vehicle_service', 'Vehicle service: '.$item->vehicle_number, 'Service is due on '.$item->next_service_date.'.', $item->toArray(), $today));
    }

    private function notifyUsers($users, string $permission, string $type, string $title, string $message, array $data, $today): void
    {
        foreach ($users as $user) {
            if (! $user->can($permission)) continue;
            $exists = DB::table('notifications')->where('user_id', $user->id)->where('type', $type)->where('message', $message)->where('created_at', '>=', $today->copy()->startOfDay())->exists();
            if (! $exists) $user->notify(new ReminderNotification($title, $message, $type, ['record_id' => $data['id'] ?? null, 'action_url' => '/modules/'.str_replace('_', '-', $type)]));
        }
    }
}
