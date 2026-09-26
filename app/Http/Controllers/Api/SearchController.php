<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SearchController extends Controller
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        abort_if(strlen($term) < 2, 422, 'Enter at least two characters to search.');
        $results = [];

        foreach ($this->registry->all() as $key => $module) {
            if (! $this->registry->can($request->user(), $module, 'view')) {
                continue;
            }
            $model = $module['model'];
            $table = (new $model)->getTable();
            $columns = array_filter($module['searchable'] ?? [], fn ($column) => Schema::hasColumn($table, $column));
            if (! $columns) {
                continue;
            }
            $rows = $model::query()->forUser($request->user())->where(function ($query) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%'.$term.'%');
                }
            })->limit(5)->get();
            if ($rows->isNotEmpty()) {
                $results[$key] = ['label' => $module['label'], 'items' => $rows->map(fn ($row) => [
                    'id' => $row->id,
                    'title' => $row->name ?? $row->title ?? $row->description ?? $row->vehicle_number ?? 'Record',
                    'subtitle' => $row->category ?? $row->status ?? $row->relationship ?? null,
                ])->values()];
            }
        }

        return response()->json(['query' => $term, 'results' => $results]);
    }
}
