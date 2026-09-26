<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\InsurancePolicy;
use App\Models\InventoryItem;
use App\Models\MaintenanceRequest;
use App\Models\Pet;
use App\Models\Plant;
use App\Models\Property;
use App\Models\ServiceProvider;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HomeErpSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedPermissions();
        $household = Household::updateOrCreate(['slug' => 'aurora-family-home'], ['name' => 'Aurora Family Home', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR', 'address' => '24 Green Park, Bengaluru']);
        $admin = $this->user('admin@homeerp.test', 'Aarav Sharma', $household);
        $family = $this->user('family@homeerp.test', 'Diya Sharma', $household);
        $staff = $this->user('staff@homeerp.test', 'Ravi Kumar', $household);
        $admin->syncRoles(['admin']); $family->syncRoles(['family-member']); $staff->syncRoles(['staff']);
        $property = Property::create(['household_id' => $household->id, 'name' => 'Aurora Home', 'address' => '24 Green Park, Bengaluru', 'property_type' => 'villa', 'ownership_status' => 'owned', 'purchase_date' => '2021-06-12', 'purchase_value' => 18500000, 'current_value' => 24500000]);
        $categories = $this->categories($household);
        $providers = $this->providers($household);
        FamilyMember::create(['household_id' => $household->id, 'user_id' => $family->id, 'name' => 'Diya Sharma', 'relationship' => 'Spouse', 'email' => $family->email, 'mobile' => '+91 98765 43210', 'role_label' => 'Family member']);
        $this->seedCoreRecords($household, $property, $categories, $providers, $admin, $family);
    }

    private function seedPermissions(): void
    {
        $permissions = ['dashboard.view', 'records.create', 'settings.view', 'settings.manage', 'users.view', 'reports.view', 'reports.export', 'ai.use'];
        foreach (config('home_modules', []) as $module) foreach (['view', 'create', 'edit', 'delete', 'export', 'approve'] as $ability) $permissions[] = $module['prefix'].'.'.$ability;
        foreach (array_unique($permissions) as $permission) Permission::findOrCreate($permission, 'web');
        $super = Role::findOrCreate('super-admin', 'web'); $admin = Role::findOrCreate('admin', 'web'); $member = Role::findOrCreate('family-member', 'web'); $staff = Role::findOrCreate('staff', 'web');
        $all = Permission::query()->get(); $super->syncPermissions($all); $admin->syncPermissions($all);
        $member->syncPermissions(Permission::query()->whereIn('name', ['dashboard.view', 'expenses.view', 'expenses.create', 'expenses.edit', 'bills.view', 'budgets.view', 'budgets.create', 'family.view', 'properties.view', 'maintenance.view', 'maintenance.create', 'assets.view', 'tasks.view', 'tasks.create', 'tasks.edit', 'inventory.view', 'calendar.view', 'subscriptions.view', 'insurance.view', 'vehicles.view', 'documents.view', 'providers.view', 'reports.view', 'ai.use'])->get());
        $staff->syncPermissions(Permission::query()->whereIn('name', ['dashboard.view', 'maintenance.view', 'maintenance.create', 'maintenance.edit', 'assets.view', 'tasks.view', 'tasks.edit', 'inventory.view', 'providers.view', 'vehicles.view', 'documents.view', 'ai.use'])->get());
    }

    private function user(string $email, string $name, Household $household): User
    {
        return User::updateOrCreate(['email' => $email], ['household_id' => $household->id, 'name' => $name, 'phone' => '+91 98765 00000', 'password' => Hash::make('password'), 'is_active' => true]);
    }

    private function categories(Household $household): array
    {
        $items = ['Groceries' => '#75e6bd', 'Electricity' => '#f5bd69', 'Water' => '#5eb9e8', 'Gas' => '#ef8395', 'Internet' => '#a98be8', 'Mobile' => '#5fd1c1', 'Rent' => '#d1e267', 'Maintenance' => '#f59e9e', 'Vehicle' => '#8bb8ff', 'Transport' => '#8bb8ff', 'Entertainment' => '#a98be8', 'Other' => '#94a3b8'];
        $result = [];
        foreach ($items as $name => $color) $result[$name] = Category::create(['household_id' => $household->id, 'type' => 'expense', 'name' => $name, 'color' => $color]);
        foreach (['Electricity', 'Water', 'Gas', 'Internet', 'Mobile', 'Rent', 'Maintenance', 'Other'] as $name) $result['bill-'.$name] = Category::create(['household_id' => $household->id, 'type' => 'bill', 'name' => $name, 'color' => '#75e6bd']);
        return $result;
    }

    private function providers(Household $household): array
    {
        return collect([
            ['name' => 'CoolAir Service', 'service_type' => 'AC technician', 'mobile' => '+91 99000 11223', 'rating' => 4.8],
            ['name' => 'BrightFix Electricians', 'service_type' => 'Electrician', 'mobile' => '+91 99000 22334', 'rating' => 4.6],
            ['name' => 'GreenLeaf Gardens', 'service_type' => 'Gardener', 'mobile' => '+91 99000 33445', 'rating' => 4.9],
            ['name' => 'AutoCare Motors', 'service_type' => 'Mechanic', 'mobile' => '+91 99000 44556', 'rating' => 4.7],
        ])->mapWithKeys(fn (array $data) => [$data['name'] => ServiceProvider::create(['household_id' => $household->id] + $data)])->all();
    }


    private function seedCoreRecords(Household $household, Property $property, array $categories, array $providers, User $admin, User $family): void
    {
        $today = now();
        $expenseRows = [
            ['Groceries', 4250, 'Fresh market vegetables and staples', 'card'], ['Electricity', 6840, 'BESCOM monthly bill', 'bank'], ['Internet', 1299, 'Fiber broadband', 'upi'], ['Water', 980, 'Water tanker and maintenance', 'cash'], ['Transport', 2180, 'Fuel and commute', 'card'], ['Maintenance', 1850, 'Kitchen tap service', 'upi'], ['Groceries', 3160, 'Weekly essentials', 'cash'], ['Entertainment', 1299, 'Streaming and cinema', 'card'],
        ];
        foreach ($expenseRows as $index => [$category, $amount, $description, $method]) Expense::create(['household_id' => $household->id, 'property_id' => $property->id, 'category_id' => $categories[$category]->id, 'user_id' => $admin->id, 'description' => $description, 'amount' => $amount, 'expense_date' => $today->copy()->subDays($index * 4)->toDateString(), 'payment_method' => $method, 'paid_by' => $admin->name]);
        Bill::create(['household_id' => $household->id, 'property_id' => $property->id, 'category_id' => $categories['bill-Electricity']->id, 'service_provider_id' => $providers['BrightFix Electricians']->id, 'name' => 'BESCOM Electricity', 'account_number' => 'BES-8842', 'amount' => 6840, 'due_date' => $today->copy()->addDays(4), 'status' => 'due', 'recurrence' => 'monthly', 'reminder_days' => 3]);
        Bill::create(['household_id' => $household->id, 'property_id' => $property->id, 'category_id' => $categories['bill-Internet']->id, 'name' => 'Airtel Fiber', 'account_number' => 'AIR-2098', 'amount' => 1299, 'due_date' => $today->copy()->addDays(11), 'status' => 'upcoming', 'recurrence' => 'monthly', 'reminder_days' => 3]);
        Bill::create(['household_id' => $household->id, 'property_id' => $property->id, 'category_id' => $categories['bill-Gas']->id, 'name' => 'Indane Gas', 'account_number' => 'IND-7710', 'amount' => 1450, 'due_date' => $today->copy()->subDays(2), 'payment_date' => $today->copy()->subDays(1), 'status' => 'paid', 'recurrence' => 'monthly']);
        Budget::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Household monthly budget', 'period_type' => 'monthly', 'period_start' => $today->copy()->startOfMonth(), 'period_end' => $today->copy()->endOfMonth(), 'amount' => 65000, 'alert_percent' => 80]);
        Task::create(['household_id' => $household->id, 'property_id' => $property->id, 'assigned_user_id' => $family->id, 'created_by' => $admin->id, 'title' => 'Water the balcony garden', 'description' => 'Check soil moisture before watering.', 'due_at' => $today->copy()->addDay()->setTime(8, 0), 'priority' => 'medium', 'status' => 'pending', 'recurrence' => 'daily']);
        Task::create(['household_id' => $household->id, 'property_id' => $property->id, 'assigned_user_id' => $family->id, 'created_by' => $admin->id, 'title' => 'Pay electricity bill', 'description' => 'Verify the BESCOM account before payment.', 'due_at' => $today->copy()->addDays(2)->setTime(18, 0), 'priority' => 'high', 'status' => 'in-progress']);
        $ac = Asset::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Living Room AC', 'category' => 'ac', 'brand' => 'Voltas', 'model' => '123LCE', 'serial_number' => 'VOL-ACL-8821', 'purchase_date' => '2023-05-18', 'purchase_price' => 52900, 'warranty_months' => 24, 'warranty_expiry' => $today->copy()->addDays(41), 'vendor' => 'CoolAir Service', 'location' => 'Living room', 'condition' => 'good']);
        Asset::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Family Refrigerator', 'category' => 'refrigerator', 'brand' => 'Samsung', 'model' => 'RT34', 'purchase_date' => '2022-11-04', 'purchase_price' => 38900, 'warranty_expiry' => $today->copy()->addDays(130), 'location' => 'Kitchen', 'condition' => 'excellent']);

        MaintenanceRequest::create(['household_id' => $household->id, 'property_id' => $property->id, 'asset_id' => $ac->id, 'service_provider_id' => $providers['CoolAir Service']->id, 'assigned_user_id' => $staffId = $admin->id, 'title' => 'AC not cooling in bedroom', 'description' => 'Airflow is weak and room temperature is rising.', 'category' => 'ac', 'priority' => 'high', 'estimated_cost' => 1800, 'actual_cost' => 1450, 'scheduled_date' => $today->copy()->subDays(8), 'completion_date' => $today->copy()->subDays(6), 'status' => 'completed', 'notes' => 'Gas refilled and filters cleaned.']);
        MaintenanceRequest::create(['household_id' => $household->id, 'property_id' => $property->id, 'service_provider_id' => $providers['BrightFix Electricians']->id, 'title' => 'Replace hallway light switch', 'description' => 'Switch is sparking intermittently.', 'category' => 'electrical', 'priority' => 'medium', 'estimated_cost' => 900, 'scheduled_date' => $today->copy()->addDays(2), 'status' => 'scheduled']);
        Subscription::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Netflix Premium', 'amount' => 649, 'billing_cycle' => 'monthly', 'start_date' => $today->copy()->subMonths(6), 'renewal_date' => $today->copy()->addDays(9), 'payment_method' => 'card', 'status' => 'active']);
        Subscription::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Amazon Prime', 'amount' => 1499, 'billing_cycle' => 'monthly', 'start_date' => $today->copy()->subYear(), 'renewal_date' => $today->copy()->addDays(24), 'payment_method' => 'card', 'status' => 'active']);
        InsurancePolicy::create(['household_id' => $household->id, 'property_id' => $property->id, 'policy_type' => 'home', 'provider_name' => 'HDFC Ergo', 'policy_number' => 'HDFC-HOME-4402', 'premium' => 18600, 'start_date' => $today->copy()->subYear(), 'expiry_date' => $today->copy()->addDays(44), 'renewal_date' => $today->copy()->addDays(44), 'coverage_amount' => 2500000, 'status' => 'active']);
        $vehicle = Vehicle::create(['household_id' => $household->id, 'property_id' => $property->id, 'vehicle_number' => 'KA 01 MN 4821', 'vehicle_type' => 'car', 'brand' => 'Toyota', 'model' => 'Innova Crysta', 'purchase_date' => '2022-02-18', 'purchase_price' => 2450000, 'fuel_type' => 'Diesel', 'odometer_km' => 68400, 'next_service_date' => $today->copy()->addDays(12), 'puc_expiry' => $today->copy()->addDays(90), 'rc_expiry' => $today->copy()->addMonths(8), 'status' => 'active']);
        Vehicle::class;
        InventoryItem::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Basmati Rice', 'category' => 'groceries', 'sku' => 'GRO-001', 'quantity' => 2, 'unit' => 'kg', 'minimum_quantity' => 3, 'purchase_price' => 420, 'vendor' => 'Daily Fresh', 'expiry_date' => $today->copy()->addMonths(8), 'location' => 'Kitchen']);
        InventoryItem::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Dishwasher Tablets', 'category' => 'cleaning', 'sku' => 'CLN-014', 'quantity' => 8, 'unit' => 'pcs', 'minimum_quantity' => 4, 'purchase_price' => 95, 'vendor' => 'UrbanCompany', 'location' => 'Utility']);
        Plant::create(['household_id' => $household->id, 'property_id' => $property->id, 'service_provider_id' => $providers['GreenLeaf Gardens']->id, 'name' => 'Peace Lily', 'plant_type' => 'Indoor', 'location' => 'Balcony', 'planted_at' => $today->copy()->subMonths(5), 'watering_frequency_days' => 3, 'last_watered_at' => $today->copy()->subDays(2), 'next_watering_at' => $today->copy()->addDay(), 'health_status' => 'healthy']);
        Pet::create(['household_id' => $household->id, 'name' => 'Milo', 'pet_type' => 'dog', 'breed' => 'Golden Retriever', 'date_of_birth' => '2022-08-10', 'gender' => 'male', 'veterinarian' => 'Green Paws Clinic', 'notes' => 'Annual vaccination due next month.']);
        CalendarEvent::create(['household_id' => $household->id, 'property_id' => $property->id, 'user_id' => $admin->id, 'title' => 'Family weekend outing', 'description' => 'Cubbon Park', 'starts_at' => $today->copy()->addDays(5)->setTime(10, 0), 'ends_at' => $today->copy()->addDays(5)->setTime(17, 0), 'is_all_day' => true, 'event_type' => 'family', 'reminder_minutes' => 1440, 'status' => 'scheduled']);
        unset($vehicle, $staffId);
    }
}

