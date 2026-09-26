<?php

return [
    'vehicles' => [
        'model' => App\Models\Vehicle::class, 'label' => 'Vehicles', 'icon' => 'car', 'prefix' => 'vehicles',
        'searchable' => ['vehicle_number', 'brand', 'model'], 'default_sort' => 'vehicle_number', 'columns' => ['vehicle_number', 'vehicle_type', 'brand', 'model', 'next_service_date', 'status'],
        'fields' => [
            ['name' => 'vehicle_number', 'label' => 'Vehicle number', 'type' => 'text', 'required' => true], ['name' => 'vehicle_type', 'label' => 'Vehicle type', 'type' => 'select', 'options' => ['car', 'bike', 'suv', 'truck', 'other']],
            ['name' => 'brand', 'label' => 'Brand', 'type' => 'text'], ['name' => 'model', 'label' => 'Model', 'type' => 'text'], ['name' => 'purchase_date', 'label' => 'Purchase date', 'type' => 'date'],
            ['name' => 'purchase_price', 'label' => 'Purchase price', 'type' => 'number', 'step' => '0.01'], ['name' => 'fuel_type', 'label' => 'Fuel type', 'type' => 'text'], ['name' => 'odometer_km', 'label' => 'Odometer (km)', 'type' => 'number'],
            ['name' => 'next_service_date', 'label' => 'Next service', 'type' => 'date'], ['name' => 'puc_expiry', 'label' => 'PUC expiry', 'type' => 'date'], ['name' => 'rc_expiry', 'label' => 'RC expiry', 'type' => 'date'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'maintenance', 'inactive']], ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ],
    ],
    'providers' => [
        'model' => App\Models\ServiceProvider::class, 'label' => 'Service providers', 'icon' => 'briefcase-business', 'prefix' => 'providers',
        'searchable' => ['name', 'service_type', 'mobile', 'email'], 'default_sort' => 'name', 'columns' => ['name', 'service_type', 'mobile', 'email', 'rating'],
        'fields' => [
            ['name' => 'name', 'label' => 'Provider name', 'type' => 'text', 'required' => true], ['name' => 'service_type', 'label' => 'Service type', 'type' => 'text', 'required' => true],
            ['name' => 'mobile', 'label' => 'Mobile', 'type' => 'text'], ['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'],
            ['name' => 'rating', 'label' => 'Rating', 'type' => 'number', 'step' => '0.1'], ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ],
    ],
    'documents' => [
        'model' => App\Models\Document::class, 'label' => 'Documents', 'icon' => 'folder-lock', 'prefix' => 'documents',
        'searchable' => ['title', 'description', 'original_name'], 'default_sort' => '-created_at', 'columns' => ['title', 'category', 'original_name', 'expiry_date', 'size'],
        'fields' => [
            ['name' => 'title', 'label' => 'Document title', 'type' => 'text', 'required' => true], ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => ['property', 'rental-agreement', 'insurance', 'vehicle', 'warranty', 'bill', 'receipt', 'identification', 'other']],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], ['name' => 'file_path', 'label' => 'File', 'type' => 'file', 'required' => true, 'accept' => 'image/*,application/pdf,.doc,.docx,.xls,.xlsx'],
            ['name' => 'property_id', 'label' => 'Property', 'type' => 'select', 'source' => 'properties'], ['name' => 'expiry_date', 'label' => 'Expiry date', 'type' => 'date'], ['name' => 'reminder_date', 'label' => 'Reminder date', 'type' => 'date'],
            ['name' => 'is_confidential', 'label' => 'Confidential', 'type' => 'checkbox'],
        ],
    ],
    'properties' => [
        'model' => App\Models\Property::class, 'label' => 'Properties', 'icon' => 'home', 'prefix' => 'properties',
        'searchable' => ['name', 'address', 'tenant_name', 'tenant_email'], 'default_sort' => 'name', 'columns' => ['name', 'property_type', 'ownership_status', 'current_value', 'monthly_rent', 'rent_due_day'],
        'fields' => [
            ['name' => 'name', 'label' => 'Property name', 'type' => 'text', 'required' => true], ['name' => 'property_type', 'label' => 'Property type', 'type' => 'select', 'options' => ['apartment', 'house', 'villa', 'plot', 'commercial', 'other']],
            ['name' => 'ownership_status', 'label' => 'Ownership', 'type' => 'select', 'options' => ['owned', 'rented', 'shared']], ['name' => 'household_id', 'label' => 'Family', 'type' => 'select', 'source' => 'households'], ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'],
            ['name' => 'purchase_date', 'label' => 'Purchase date', 'type' => 'date'], ['name' => 'purchase_value', 'label' => 'Purchase value', 'type' => 'number', 'step' => '0.01'], ['name' => 'current_value', 'label' => 'Current value', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'tenant_name', 'label' => 'Tenant name', 'type' => 'text'], ['name' => 'tenant_email', 'label' => 'Tenant email', 'type' => 'email'], ['name' => 'tenant_mobile', 'label' => 'Tenant mobile', 'type' => 'text'],
            ['name' => 'monthly_rent', 'label' => 'Monthly rent', 'type' => 'number', 'step' => '0.01'], ['name' => 'deposit_amount', 'label' => 'Deposit', 'type' => 'number', 'step' => '0.01'], ['name' => 'rent_due_day', 'label' => 'Rent due day', 'type' => 'number'], ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ],
    ],
    'security' => [
        'model' => App\Models\SecurityDevice::class, 'label' => 'Home security', 'icon' => 'shield', 'prefix' => 'security',
        'searchable' => ['name', 'device_type', 'brand', 'model', 'serial_number'], 'default_sort' => 'name', 'columns' => ['name', 'device_type', 'location', 'installed_on', 'next_maintenance_on', 'status'],
        'fields' => [
            ['name' => 'name', 'label' => 'Device name', 'type' => 'text', 'required' => true], ['name' => 'device_type', 'label' => 'Device type', 'type' => 'select', 'options' => ['cctv', 'smart-lock', 'alarm', 'sensor', 'intercom', 'other']],
            ['name' => 'brand', 'label' => 'Brand', 'type' => 'text'], ['name' => 'model', 'label' => 'Model', 'type' => 'text'], ['name' => 'serial_number', 'label' => 'Serial number', 'type' => 'text'], ['name' => 'location', 'label' => 'Location', 'type' => 'text'],
            ['name' => 'installed_on', 'label' => 'Installation date', 'type' => 'date'], ['name' => 'next_maintenance_on', 'label' => 'Next maintenance', 'type' => 'date'], ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'maintenance', 'inactive']], ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ],
    ],
    'garden' => [
        'model' => App\Models\Plant::class, 'label' => 'Garden & outdoor', 'icon' => 'sprout', 'prefix' => 'garden',
        'searchable' => ['name', 'plant_type', 'location'], 'default_sort' => 'name', 'columns' => ['name', 'plant_type', 'location', 'next_watering_at', 'health_status'],
        'fields' => [
            ['name' => 'name', 'label' => 'Plant name', 'type' => 'text', 'required' => true], ['name' => 'plant_type', 'label' => 'Plant type', 'type' => 'text'], ['name' => 'location', 'label' => 'Location', 'type' => 'text'], ['name' => 'planted_at', 'label' => 'Planted at', 'type' => 'date'],
            ['name' => 'watering_frequency_days', 'label' => 'Water every (days)', 'type' => 'number'], ['name' => 'last_watered_at', 'label' => 'Last watered', 'type' => 'date'], ['name' => 'next_watering_at', 'label' => 'Next watering', 'type' => 'date'],
            ['name' => 'health_status', 'label' => 'Health', 'type' => 'select', 'options' => ['healthy', 'needs-attention', 'sick']], ['name' => 'service_provider_id', 'label' => 'Gardener', 'type' => 'select', 'source' => 'providers'], ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ],
    ],

    'pets' => [
        'model' => App\Models\Pet::class, 'label' => 'Pets', 'icon' => 'paw-print', 'prefix' => 'pets',
        'searchable' => ['name', 'pet_type', 'breed', 'microchip_number'], 'default_sort' => 'name', 'columns' => ['name', 'pet_type', 'breed', 'date_of_birth', 'veterinarian'],
        'fields' => [
            ['name' => 'name', 'label' => 'Pet name', 'type' => 'text', 'required' => true], ['name' => 'pet_type', 'label' => 'Pet type', 'type' => 'select', 'options' => ['dog', 'cat', 'bird', 'rabbit', 'fish', 'other']],
            ['name' => 'breed', 'label' => 'Breed', 'type' => 'text'], ['name' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date'], ['name' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => ['male', 'female', 'unknown']],
            ['name' => 'microchip_number', 'label' => 'Microchip number', 'type' => 'text'], ['name' => 'veterinarian', 'label' => 'Veterinarian', 'type' => 'text'], ['name' => 'photo_path', 'label' => 'Photo', 'type' => 'file', 'accept' => 'image/*'], ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ],
    ],
    'calendar' => [
        'model' => App\Models\CalendarEvent::class, 'label' => 'Calendar & reminders', 'icon' => 'calendar-days', 'prefix' => 'calendar',
        'searchable' => ['title', 'description'], 'default_sort' => 'starts_at', 'columns' => ['title', 'event_type', 'starts_at', 'ends_at', 'status'],
        'fields' => [
            ['name' => 'title', 'label' => 'Event title', 'type' => 'text', 'required' => true], ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'starts_at', 'label' => 'Starts at', 'type' => 'datetime-local', 'required' => true], ['name' => 'ends_at', 'label' => 'Ends at', 'type' => 'datetime-local'],
            ['name' => 'is_all_day', 'label' => 'All day', 'type' => 'checkbox'], ['name' => 'event_type', 'label' => 'Event type', 'type' => 'select', 'options' => ['bill', 'maintenance', 'task', 'insurance', 'vehicle', 'subscription', 'family', 'property', 'warranty', 'other']],
            ['name' => 'recurrence', 'label' => 'Repeats', 'type' => 'select', 'options' => ['', 'daily', 'weekly', 'monthly', 'yearly']], ['name' => 'reminder_minutes', 'label' => 'Reminder minutes', 'type' => 'number'], ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['scheduled', 'completed', 'cancelled']],
        ],
    ],
];

