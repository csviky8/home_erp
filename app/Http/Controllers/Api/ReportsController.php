<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleResource;
use App\Support\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportsController extends Controller
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function index(Request $request, string $type): JsonResponse
    {
        $module = config('home_modules.'.$type);
        abort_if($module === null, 404, 'Report type not found.');
        abort_unless($this->registry->can($request->user(), $module, 'view'), 403, 'You do not have permission to view this report.');
        [$query, $config] = $this->query($request, $type);
        $rows = $query->latest($config['date_field'])->limit(500)->get();
        $amount = Schema::hasColumn($query->getModel()->getTable(), $config['amount_field']) ? (float) (clone $query)->sum($config['amount_field']) : null;

        return response()->json(['type' => $type, 'summary' => ['count' => $rows->count(), 'total' => $amount], 'columns' => $config['columns'], 'data' => ModuleResource::collection($rows)->resolve($request)]);
    }

    public function export(Request $request, string $type, string $format)
    {
        $module = config('home_modules.'.$type);
        abort_if($module === null, 404, 'Report type not found.');
        abort_unless($this->registry->can($request->user(), $module, 'export'), 403, 'You do not have permission to export this report.');
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);
        [$query, $config] = $this->query($request, $type);
        $rows = $query->latest($config['date_field'])->limit(5000)->get()->map(fn ($row) => collect($config['columns'])->mapWithKeys(fn ($column) => [$column => data_get($row, $column)])->all());
        $filename = 'home-erp-'.$type.'-'.now()->format('Y-m-d');
        $headings = array_map(fn ($column) => ucwords(str_replace('_', ' ', $column)), $config['columns']);

        if ($format === 'excel') {
            return Excel::download(new class($rows, $headings, $type) implements FromCollection, WithHeadings, WithTitle {
                public function __construct(private Collection $rows, private array $headings, private string $title) {}
                public function collection(): Collection { return $this->rows; }
                public function headings(): array { return $this->headings; }
                public function title(): string { return $this->title; }
            }, $filename.'.xlsx');
        }

        return Pdf::loadView('reports.pdf', ['title' => strtoupper($type).' REPORT', 'headings' => $headings, 'rows' => $rows])->download($filename.'.pdf');
    }

    private function query(Request $request, string $type): array
    {
        $config = match ($type) {
            'maintenance' => ['date_field' => 'created_at', 'amount_field' => 'actual_cost', 'columns' => ['title', 'category', 'priority', 'status', 'estimated_cost', 'actual_cost', 'scheduled_date', 'completion_date']],
            'assets' => ['date_field' => 'purchase_date', 'amount_field' => 'purchase_price', 'columns' => ['name', 'category', 'brand', 'model', 'purchase_date', 'purchase_price', 'warranty_expiry', 'condition']],
            'vehicles' => ['date_field' => 'created_at', 'amount_field' => 'purchase_price', 'columns' => ['vehicle_number', 'vehicle_type', 'brand', 'model', 'purchase_date', 'purchase_price', 'next_service_date', 'status']],
            'insurance' => ['date_field' => 'start_date', 'amount_field' => 'premium', 'columns' => ['policy_type', 'provider_name', 'policy_number', 'premium', 'start_date', 'expiry_date', 'renewal_date', 'status']],
            'subscriptions' => ['date_field' => 'renewal_date', 'amount_field' => 'amount', 'columns' => ['name', 'amount', 'billing_cycle', 'renewal_date', 'status']],
            'inventory' => ['date_field' => 'created_at', 'amount_field' => 'purchase_price', 'columns' => ['name', 'category', 'quantity', 'unit', 'minimum_quantity', 'purchase_price', 'expiry_date']],
            'budgets' => ['date_field' => 'period_start', 'amount_field' => 'amount', 'columns' => ['name', 'period_type', 'period_start', 'period_end', 'amount', 'is_active']],
            default => ['date_field' => 'expense_date', 'amount_field' => 'amount', 'columns' => ['description', 'amount', 'expense_date', 'category', 'payment_method', 'paid_by']],
        };
        $module = config('home_modules.'.$type);
        abort_if($module === null, 404, 'Report type not found.');
        $query = $module['model']::query()->forUser($request->user())->with($this->registry->relations($type));
        $table = $query->getModel()->getTable();
        if ($request->filled('from') && Schema::hasColumn($table, $config['date_field'])) $query->whereDate($config['date_field'], '>=', $request->date('from'));
        if ($request->filled('to') && Schema::hasColumn($table, $config['date_field'])) $query->whereDate($config['date_field'], '<=', $request->date('to'));
        foreach (['category_id', 'property_id', 'user_id', 'status'] as $filter) if ($request->filled($filter) && Schema::hasColumn($table, $filter)) $query->where($filter, $request->input($filter));

        return [$query, $config];
    }
}
