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

        $this->seedHousehold([
            'slug' => 'aurora-family-home', 'name' => 'Aurora Family Home', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR',
            'address' => '24 Green Park, Bengaluru',
            'users' => [
                ['email' => 'admin@homeerp.test', 'name' => 'Aarav Sharma', 'role' => 'admin'],
                ['email' => 'family@homeerp.test', 'name' => 'Diya Sharma', 'role' => 'family-member'],
                ['email' => 'staff@homeerp.test', 'name' => 'Ravi Kumar', 'role' => 'staff'],
            ],
            'property' => ['name' => 'Aurora Home', 'address' => '24 Green Park, Bengaluru', 'property_type' => 'villa', 'purchase_date' => '2021-06-12', 'purchase_value' => 18500000, 'current_value' => 24500000],
            'scale' => 1, 'pet' => 'Milo', 'pet_type' => 'dog', 'breed' => 'Golden Retriever',
            'vehicle_number' => 'KA 01 MN 4821', 'vehicle_type' => 'car', 'vehicle_brand' => 'Toyota', 'vehicle_model' => 'Innova Crysta',
            'plant' => 'Peace Lily',
        ]);

        $this->seedHousehold([
            'slug' => 'sharma-villa', 'name' => 'Sharma Villa', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR',
            'address' => '7 Lakeview Road, Bengaluru',
            'users' => [
                ['email' => 'villa.admin@homeerp.test', 'name' => 'Meera Sharma', 'role' => 'admin'],
                ['email' => 'villa.family@homeerp.test', 'name' => 'Karan Sharma', 'role' => 'family-member'],
                ['email' => 'villa.staff@homeerp.test', 'name' => 'Suresh Kumar', 'role' => 'staff'],
            ],
            'property' => ['name' => 'Sharma Villa', 'address' => '7 Lakeview Road, Bengaluru', 'property_type' => 'house', 'purchase_date' => '2019-04-22', 'purchase_value' => 14200000, 'current_value' => 19800000],
            'scale' => 0.72, 'pet' => 'Coco', 'pet_type' => 'cat', 'breed' => 'Indian Shorthair',
            'vehicle_number' => 'KA 05 AB 9031', 'vehicle_type' => 'suv', 'vehicle_brand' => 'Mahindra', 'vehicle_model' => 'XUV700',
            'plant' => 'Tulsi',
        ]);

        $this->seedHousehold([
            'slug' => 'nair-cottage', 'name' => 'Nair Cottage', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR',
            'address' => '12 Panampilly Nagar, Kochi',
            'users' => [
                ['email' => 'cottage.admin@homeerp.test', 'name' => 'Lakshmi Nair', 'role' => 'admin'],
                ['email' => 'cottage.family@homeerp.test', 'name' => 'Anil Nair', 'role' => 'family-member'],
            ],
            'property' => ['name' => 'Nair Cottage', 'address' => '12 Panampilly Nagar, Kochi', 'property_type' => 'apartment', 'purchase_date' => '2020-09-15', 'purchase_value' => 9800000, 'current_value' => 13600000],
            'scale' => 0.54, 'pet' => 'Rex', 'pet_type' => 'dog', 'breed' => 'Labrador',
            'vehicle_number' => 'KL 07 CD 1180', 'vehicle_type' => 'hatchback', 'vehicle_brand' => 'Maruti Suzuki', 'vehicle_model' => 'Baleno',
            'plant' => 'Money Plant',
        ]);

        // A single super admin can drive every family workspace; they work in the first one.
        $root = $this->user('root@homeerp.test', 'System Owner', Household::where('slug', 'aurora-family-home')->firstOrFail());
        $root->syncRoles(['super-admin']);
    }

    /** Creates one family workspace with its own people, property, lookups and records. */
    private function seedHousehold(array $profile): void
    {
        $household = Household::updateOrCreate(
            ['slug' => $profile['slug']],
            ['name' => $profile['name'], 'timezone' => $profile['timezone'], 'currency' => $profile['currency'], 'address' => $profile['address']]
        );

        $users = [];
        foreach ($profile['users'] as $definition) {
            $user = $this->user($definition['email'], $definition['name'], $household, $profile['slug']);
            $user->syncRoles([$definition['role']]);
            $users[$definition['role']] ??= $user;
        }

        $property = Property::create(['household_id' => $household->id, 'ownership_status' => 'owned'] + $profile['property']);
        $categories = $this->categories($household);
        $providers = $this->providers($household);
        $member = $users['family-member'] ?? null;
        if ($member) {
            FamilyMember::firstOrCreate(
                ['user_id' => $member->id],
                ['household_id' => $household->id, 'name' => $member->name, 'email' => $member->email, 'mobile' => $member->phone, 'relationship' => 'Family member', 'role_label' => 'Family member']
            );
        }

        $this->seedCoreRecords($household, $property, $categories, $providers, $users['admin'], $member ?? $users['admin'], $profile);
    }

    private function seedPermissions(): void
    {
        $permissions = ['dashboard.view', 'records.create', 'settings.view', 'settings.manage', 'reports.view', 'reports.export', 'ai.use'];
        // Users and the role manager get the same full action set as every other module.
        foreach (['users', 'roles'] as $area) {
            $permissions[] = $area.'.view'; $permissions[] = $area.'.create'; $permissions[] = $area.'.edit'; $permissions[] = $area.'.delete';
        }
        foreach (config('home_modules', []) as $module) foreach (['view', 'create', 'edit', 'delete', 'export', 'approve'] as $ability) $permissions[] = $module['prefix'].'.'.$ability;
        foreach (array_unique($permissions) as $permission) Permission::findOrCreate($permission, 'web');
        $super = Role::findOrCreate('super-admin', 'web'); $admin = Role::findOrCreate('admin', 'web'); $member = Role::findOrCreate('family-member', 'web'); $staff = Role::findOrCreate('staff', 'web');
        $all = Permission::query()->get(); $super->syncPermissions($all); $admin->syncPermissions($all);
        $member->syncPermissions(Permission::query()->whereIn('name', ['dashboard.view', 'expenses.view', 'expenses.create', 'expenses.edit', 'bills.view', 'budgets.view', 'budgets.create', 'family.view', 'properties.view', 'maintenance.view', 'maintenance.create', 'assets.view', 'tasks.view', 'tasks.create', 'tasks.edit', 'inventory.view', 'calendar.view', 'subscriptions.view', 'insurance.view', 'vehicles.view', 'documents.view', 'providers.view', 'reports.view', 'ai.use'])->get());
        $staff->syncPermissions(Permission::query()->whereIn('name', ['dashboard.view', 'maintenance.view', 'maintenance.create', 'maintenance.edit', 'assets.view', 'tasks.view', 'tasks.edit', 'inventory.view', 'providers.view', 'vehicles.view', 'documents.view', 'ai.use'])->get());
    }

    private function user(string $email, string $name, Household $household, string $slug = ''): User
    {
        // Derive a stable mobile per family so the demo accounts do not all share one number.
        $phone = '+91 9' . str_pad((string) (90000 + (crc32($slug ?: $email) % 9999)), 4, '0', STR_PAD_LEFT);

        return User::updateOrCreate(['email' => $email], ['household_id' => $household->id, 'name' => $name, 'phone' => $phone, 'password' => Hash::make('password'), 'is_active' => true]);
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


    private function seedCoreRecords(Household $household, Property $property, array $categories, array $providers, User $admin, User $family, array $profile = []): void
    {
        $today = now();
        // `scale` keeps each family's numbers realistic without duplicating the whole record set.
        $money = fn (float $amount): float => round($amount * ($profile['scale'] ?? 1), 2);
        $expenseRows = [
            ['Groceries', 4250, 'Fresh market vegetables and staples', 'card'], ['Electricity', 6840, 'BESCOM monthly bill', 'bank'], ['Internet', 1299, 'Fiber broadband', 'upi'], ['Water', 980, 'Water tanker and maintenance', 'cash'], ['Transport', 2180, 'Fuel and commute', 'card'], ['Maintenance', 1850, 'Kitchen tap service', 'upi'], ['Groceries', 3160, 'Weekly essentials', 'cash'], ['Entertainment', 1299, 'Streaming and cinema', 'card'],
        ];
        foreach ($expenseRows as $index => [$category, $amount, $description, $method]) Expense::create(['household_id' => $household->id, 'property_id' => $property->id, 'category_id' => $categories[$category]->id, 'user_id' => $admin->id, 'description' => $description, 'amount' => $money($amount), 'expense_date' => $today->copy()->subDays($index * 4)->toDateString(), 'payment_method' => $method, 'paid_by' => $admin->name]);
        $billNumber = fn (string $prefix): string => $prefix.'-'.str_pad((string) (crc32($household->slug) % 9000 + 1000), 4, '0', STR_PAD_LEFT);
        Bill::create(['household_id' => $household->id, 'property_id' => $property->id, 'category_id' => $categories['bill-Electricity']->id, 'service_provider_id' => $providers['BrightFix Electricians']->id, 'name' => 'BESCOM Electricity', 'account_number' => $billNumber('BES', 4), 'amount' => $money(6840), 'due_date' => $today->copy()->addDays(4), 'status' => 'due', 'recurrence' => 'monthly', 'reminder_days' => 3]);
        Bill::create(['household_id' => $household->id, 'property_id' => $property->id, 'category_id' => $categories['bill-Internet']->id, 'name' => 'Airtel Fiber', 'account_number' => $billNumber('AIR', 4), 'amount' => $money(1299), 'due_date' => $today->copy()->addDays(11), 'status' => 'upcoming', 'recurrence' => 'monthly', 'reminder_days' => 3]);
        Bill::create(['household_id' => $household->id, 'property_id' => $property->id, 'category_id' => $categories['bill-Gas']->id, 'name' => 'Indane Gas', 'account_number' => $billNumber('IND', 4), 'amount' => $money(1450), 'due_date' => $today->copy()->subDays(2), 'payment_date' => $today->copy()->subDays(1), 'status' => 'paid', 'recurrence' => 'monthly']);
        Budget::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Household monthly budget', 'period_type' => 'monthly', 'period_start' => $today->copy()->startOfMonth(), 'period_end' => $today->copy()->endOfMonth(), 'amount' => $money(65000), 'alert_percent' => 80]);
        Task::create(['household_id' => $household->id, 'property_id' => $property->id, 'assigned_user_id' => $family->id, 'created_by' => $admin->id, 'title' => 'Water the balcony garden', 'description' => 'Check soil moisture before watering.', 'due_at' => $today->copy()->addDay()->setTime(8, 0), 'priority' => 'medium', 'status' => 'pending', 'recurrence' => 'daily']);
        Task::create(['household_id' => $household->id, 'property_id' => $property->id, 'assigned_user_id' => $family->id, 'created_by' => $admin->id, 'title' => 'Pay electricity bill', 'description' => 'Verify the BESCOM account before payment.', 'due_at' => $today->copy()->addDays(2)->setTime(18, 0), 'priority' => 'high', 'status' => 'in-progress']);
        $ac = Asset::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Living Room AC', 'category' => 'ac', 'brand' => 'Voltas', 'model' => '123LCE', 'serial_number' => 'VOL-ACL-8821', 'purchase_date' => '2023-05-18', 'purchase_price' => 52900, 'warranty_months' => 24, 'warranty_expiry' => $today->copy()->addDays(41), 'vendor' => 'CoolAir Service', 'location' => 'Living room', 'condition' => 'good']);
        Asset::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Family Refrigerator', 'category' => 'refrigerator', 'brand' => 'Samsung', 'model' => 'RT34', 'purchase_date' => '2022-11-04', 'purchase_price' => 38900, 'warranty_expiry' => $today->copy()->addDays(130), 'location' => 'Kitchen', 'condition' => 'excellent']);

        MaintenanceRequest::create(['household_id' => $household->id, 'property_id' => $property->id, 'asset_id' => $ac->id, 'service_provider_id' => $providers['CoolAir Service']->id, 'assigned_user_id' => $staffId = $admin->id, 'title' => 'AC not cooling in bedroom', 'description' => 'Airflow is weak and room temperature is rising.', 'category' => 'ac', 'priority' => 'high', 'estimated_cost' => 1800, 'actual_cost' => 1450, 'scheduled_date' => $today->copy()->subDays(8), 'completion_date' => $today->copy()->subDays(6), 'status' => 'completed', 'notes' => 'Gas refilled and filters cleaned.']);
        MaintenanceRequest::create(['household_id' => $household->id, 'property_id' => $property->id, 'service_provider_id' => $providers['BrightFix Electricians']->id, 'title' => 'Replace hallway light switch', 'description' => 'Switch is sparking intermittently.', 'category' => 'electrical', 'priority' => 'medium', 'estimated_cost' => 900, 'scheduled_date' => $today->copy()->addDays(2), 'status' => 'scheduled']);
        Subscription::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Netflix Premium', 'amount' => 649, 'billing_cycle' => 'monthly', 'start_date' => $today->copy()->subMonths(6), 'renewal_date' => $today->copy()->addDays(9), 'payment_method' => 'card', 'status' => 'active']);
        Subscription::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Amazon Prime', 'amount' => 1499, 'billing_cycle' => 'monthly', 'start_date' => $today->copy()->subYear(), 'renewal_date' => $today->copy()->addDays(24), 'payment_method' => 'card', 'status' => 'active']);
        InsurancePolicy::create(['household_id' => $household->id, 'property_id' => $property->id, 'policy_type' => 'home', 'provider_name' => 'HDFC Ergo', 'policy_number' => $billNumber('HDFC-HOME', 4), 'premium' => $money(18600), 'start_date' => $today->copy()->subYear(), 'expiry_date' => $today->copy()->addDays(44), 'renewal_date' => $today->copy()->addDays(44), 'coverage_amount' => $money(2500000), 'status' => 'active']);
        $vehicle = Vehicle::create(['household_id' => $household->id, 'property_id' => $property->id, 'vehicle_number' => $profile['vehicle_number'] ?? 'KA 01 MN 4821', 'vehicle_type' => $profile['vehicle_type'] ?? 'car', 'brand' => $profile['vehicle_brand'] ?? 'Toyota', 'model' => $profile['vehicle_model'] ?? 'Innova Crysta', 'purchase_date' => '2022-02-18', 'purchase_price' => $money(2450000), 'fuel_type' => 'Diesel', 'odometer_km' => 68400, 'next_service_date' => $today->copy()->addDays(12), 'puc_expiry' => $today->copy()->addDays(90), 'rc_expiry' => $today->copy()->addMonths(8), 'status' => 'active']);
        Vehicle::class;
        InventoryItem::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Basmati Rice', 'category' => 'groceries', 'sku' => 'GRO-001', 'quantity' => 2, 'unit' => 'kg', 'minimum_quantity' => 3, 'purchase_price' => 420, 'vendor' => 'Daily Fresh', 'expiry_date' => $today->copy()->addMonths(8), 'location' => 'Kitchen']);
        InventoryItem::create(['household_id' => $household->id, 'property_id' => $property->id, 'name' => 'Dishwasher Tablets', 'category' => 'cleaning', 'sku' => 'CLN-014', 'quantity' => 8, 'unit' => 'pcs', 'minimum_quantity' => 4, 'purchase_price' => 95, 'vendor' => 'UrbanCompany', 'location' => 'Utility']);
        Plant::create(['household_id' => $household->id, 'property_id' => $property->id, 'service_provider_id' => $providers['GreenLeaf Gardens']->id, 'name' => $profile['plant'] ?? 'Peace Lily', 'plant_type' => 'Indoor', 'location' => 'Balcony', 'planted_at' => $today->copy()->subMonths(5), 'watering_frequency_days' => 3, 'last_watered_at' => $today->copy()->subDays(2), 'next_watering_at' => $today->copy()->addDay(), 'health_status' => 'healthy']);
        Pet::create(['household_id' => $household->id, 'name' => $profile['pet'] ?? 'Milo', 'pet_type' => $profile['pet_type'] ?? 'dog', 'breed' => $profile['breed'] ?? 'Golden Retriever', 'date_of_birth' => '2022-08-10', 'gender' => 'male', 'veterinarian' => 'Green Paws Clinic', 'notes' => 'Annual vaccination due next month.']);
        CalendarEvent::create(['household_id' => $household->id, 'property_id' => $property->id, 'user_id' => $admin->id, 'title' => 'Family weekend outing', 'description' => 'Cubbon Park', 'starts_at' => $today->copy()->addDays(5)->setTime(10, 0), 'ends_at' => $today->copy()->addDays(5)->setTime(17, 0), 'is_all_day' => true, 'event_type' => 'family', 'reminder_minutes' => 1440, 'status' => 'scheduled']);
        unset($vehicle, $staffId);
    }
}

