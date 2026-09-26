<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Bill;
use App\Models\GardenExpense;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\PetHealthRecord;
use App\Models\Plant;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HouseholdWorkflowController extends Controller
{
    public function payBill(Request $request, Bill $bill): JsonResponse
    {
        $this->authorizeRecord($request->user(), $bill, 'update');
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'paid_at' => ['required', 'date'], 'payment_method' => ['required', 'string', 'max:40'], 'reference_number' => ['nullable', 'string', 'max:120']]);
        $payment = DB::transaction(function () use ($bill, $data, $request): Payment {
            $payment = Payment::create(['household_id' => $bill->household_id, 'bill_id' => $bill->id, 'user_id' => $request->user()->id] + $data);
            $bill->update(['status' => 'paid', 'payment_date' => $data['paid_at']]);
            return $payment;
        });
        $this->log($request, $bill->household_id, 'bill.paid', 'bills', $bill->id, ['payment_id' => $payment->id]);
        return response()->json(['message' => 'Payment recorded.', 'payment' => $payment, 'bill' => $bill->fresh()], 201);
    }

    public function moveInventory(Request $request, InventoryItem $item): JsonResponse
    {
        $this->authorizeRecord($request->user(), $item, 'update');
        $data = $request->validate(['movement_type' => ['required', 'in:stock_in,stock_out'], 'quantity' => ['required', 'numeric', 'min:0.01'], 'movement_date' => ['required', 'date'], 'unit_price' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $movement = DB::transaction(function () use ($item, $data, $request): InventoryMovement {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $quantity = (float) $locked->quantity + ($data['movement_type'] === 'stock_in' ? (float) $data['quantity'] : -(float) $data['quantity']);
            if ($quantity < 0) throw ValidationException::withMessages(['quantity' => 'Stock cannot go below zero.']);
            $locked->update(['quantity' => $quantity]);
            return InventoryMovement::create(['household_id' => $locked->household_id, 'inventory_item_id' => $locked->id, 'user_id' => $request->user()->id] + $data);
        });
        $this->log($request, $item->household_id, 'inventory.moved', 'inventory', $item->id, ['movement_type' => $data['movement_type']]);
        return response()->json(['message' => 'Inventory updated.', 'movement' => $movement, 'item' => $item->fresh()], 201);
    }

    public function taskDetails(Request $request, Task $task): JsonResponse
    {
        $this->authorizeRecord($request->user(), $task, 'view');
        return response()->json(['data' => $task->load(['assignee:id,name', 'creator:id,name', 'checklist', 'comments.user:id,name', 'attachments'])]);
    }

    public function addChecklistItem(Request $request, Task $task): JsonResponse
    {
        $this->authorizeRecord($request->user(), $task, 'update');
        $data = $request->validate(['label' => ['required', 'string', 'max:255']]);
        $item = TaskChecklistItem::create(['household_id' => $task->household_id, 'task_id' => $task->id] + $data);
        return response()->json(['item' => $item], 201);
    }

    public function addTaskComment(Request $request, Task $task): JsonResponse
    {
        $this->authorizeRecord($request->user(), $task, 'update');
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $comment = TaskComment::create(['household_id' => $task->household_id, 'task_id' => $task->id, 'user_id' => $request->user()->id] + $data);
        return response()->json(['comment' => $comment->load('user:id,name')], 201);
    }

    public function assetHistory(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeRecord($request->user(), $asset, 'view');
        $maintenance = MaintenanceRequest::query()->forUser($request->user())->where('asset_id', $asset->id)->latest()->get(['id', 'title', 'status', 'scheduled_date', 'completion_date', 'actual_cost']);
        return response()->json(['asset' => $asset, 'maintenance_history' => $maintenance]);
    }

    public function vehicleHistory(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeRecord($request->user(), $vehicle, 'view');
        return response()->json(['vehicle' => $vehicle, 'services' => $vehicle->services()->latest('service_date')->get(), 'expenses' => $vehicle->expenses()->latest('expense_date')->get(), 'insurance' => $vehicle->insurancePolicies()->get()]);
    }

    public function addVehicleExpense(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeRecord($request->user(), $vehicle, 'edit');
        $data = $request->validate(['expense_date' => ['required', 'date'], 'expense_type' => ['required', 'string', 'max:40'], 'amount' => ['required', 'numeric', 'min:0.01'], 'odometer_km' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $expense = VehicleExpense::create(['household_id' => $vehicle->household_id, 'vehicle_id' => $vehicle->id, 'user_id' => $request->user()->id] + $data);
        return response()->json(['expense' => $expense], 201);
    }

    public function listVisitors(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('security.view'), 403);
        return response()->json(['data' => Visitor::query()->forUser($request->user())->latest('visited_at')->limit(100)->get()]);
    }

    public function storeVisitor(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('security.create'), 403);
        $data = $request->validate(['visitor_name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:25'], 'purpose' => ['nullable', 'string', 'max:255'], 'visited_at' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $visitor = Visitor::create(['household_id' => $request->user()->household_id, 'status' => 'checked_in'] + $data);
        return response()->json(['visitor' => $visitor], 201);
    }

    public function petHealth(Request $request, Pet $pet): JsonResponse
    {
        $this->authorizeRecord($request->user(), $pet, 'view');
        return response()->json(['records' => $pet->healthRecords()->latest('record_date')->get()]);
    }

    public function addPetHealth(Request $request, Pet $pet): JsonResponse
    {
        $this->authorizeRecord($request->user(), $pet, 'edit');
        $data = $request->validate(['record_type' => ['required', 'string', 'max:30'], 'title' => ['required', 'string', 'max:120'], 'record_date' => ['required', 'date'], 'next_due_date' => ['nullable', 'date'], 'cost' => ['nullable', 'numeric', 'min:0'], 'provider_name' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $record = PetHealthRecord::create(['household_id' => $pet->household_id, 'pet_id' => $pet->id] + $data);
        return response()->json(['record' => $record], 201);
    }

    public function addGardenExpense(Request $request, Plant $plant): JsonResponse
    {
        $this->authorizeRecord($request->user(), $plant, 'edit');
        $data = $request->validate(['expense_date' => ['required', 'date'], 'expense_type' => ['required', 'string', 'max:40'], 'amount' => ['required', 'numeric', 'min:0.01'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $expense = GardenExpense::create(['household_id' => $plant->household_id, 'plant_id' => $plant->id] + $data);
        return response()->json(['expense' => $expense], 201);
    }

    private function authorizeRecord(User $user, $record, string $ability): void
    {
        abort_unless($record->canAccess($user), 403);
        Gate::authorize($ability, $record);
    }

    private function log(Request $request, int $householdId, string $action, string $module, int $recordId, array $metadata = []): void
    {
        ActivityLog::create(['household_id' => $householdId, 'user_id' => $request->user()->id, 'action' => $action, 'module' => $module, 'record_type' => null, 'record_id' => $recordId, 'metadata' => $metadata, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}

