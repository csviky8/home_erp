<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ModuleRequest;
use App\Http\Resources\ModuleResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ModuleController extends Controller
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function index(Request $request, string $module): JsonResponse
    {
        $config = $this->module($module);
        $this->authorizeModule($request->user(), $config, 'view');

        $model = $config['model'];
        $query = $model::query()->forUser($request->user())->with($this->registry->relations($module));
        $query = $this->filter($query, $request, $config);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $sort = $request->string('sort')->toString();
        $allowedSorts = array_merge($config['columns'] ?? [], ['created_at', 'updated_at']);
        $sort = in_array(ltrim($sort, '-'), $allowedSorts, true) ? ($sort ?: ($config['default_sort'] ?? '-created_at')) : ($config['default_sort'] ?? '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $paginator = $query->orderBy(ltrim($sort, '-'), $direction)->paginate($perPage);
        $schema = $config;
        unset($schema['model']);

        return response()->json([
            'schema' => $schema,
            'data' => ModuleResource::collection($paginator->items())->resolve($request),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
            'links' => $paginator->links(),
        ]);
    }

    public function schema(Request $request, string $module): JsonResponse
    {
        $config = $this->module($module);
        $this->authorizeModule($request->user(), $config, 'view');
        unset($config['model']);

        return response()->json($config);
    }

    public function store(ModuleRequest $request, string $module): JsonResponse
    {
        $config = $this->module($module);
        $this->authorizeModule($request->user(), $config, 'create');
        $data = $request->validated();
        $householdId = $this->householdId($request->user(), $request);
        $this->validateRelations($data, $config, $request->user());
        $data = $this->storeFiles($request, $module, $config, $data);
        $record = new $config['model'];
        $record->fillFromValidated($data);
        $record->household_id = $householdId;
        if ($module === 'tasks' && empty($record->created_by)) {
            $record->created_by = $request->user()->id;
        }
        $record->save();
        $this->log($request, $module, 'created', $record);

        return (new ModuleResource($record->load($this->registry->relations($module))))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $module, int|string $record): ModuleResource
    {
        $model = $this->registry->findVisible($module, $record, $request->user());
        Gate::authorize('view', $model);

        return new ModuleResource($model);
    }

    public function update(ModuleRequest $request, string $module, int|string $record): ModuleResource
    {
        $model = $this->registry->findVisible($module, $record, $request->user());
        Gate::authorize('update', $model);
        $config = $this->module($module);
        $data = $request->validated();
        $this->validateRelations($data, $config, $request->user());
        $data = $this->storeFiles($request, $module, $config, $data);
        $model->fillFromValidated($data)->save();
        $this->log($request, $module, 'updated', $model);

        return new ModuleResource($model->fresh($this->registry->relations($module)));
    }

    public function destroy(Request $request, string $module, int|string $record): JsonResponse
    {
        $model = $this->registry->findVisible($module, $record, $request->user());
        Gate::authorize('delete', $model);
        $this->deleteFiles($module, $model);
        $model->delete();
        $this->log($request, $module, 'deleted', $model);

        return response()->json(['message' => 'Record deleted successfully.']);
    }
    private function module(string $key): array
    {
        return $this->registry->get($key) ?? abort(404, 'Module not found.');
    }

    private function authorizeModule(User $user, array $module, string $ability): void
    {
        abort_unless($this->registry->can($user, $module, $ability), 403, 'You do not have permission for this module.');
    }

    private function householdId(User $user, Request $request): int
    {
        if ($user->hasRole('super-admin')) {
            $householdId = (int) $request->input('household_id');
            abort_if($householdId < 1, 422, 'household_id is required for a super admin.');
            return $householdId;
        }

        abort_if(! $user->household_id, 403, 'Your account is not assigned to a household.');
        return (int) $user->household_id;
    }

    private function filter($query, Request $request, array $config)
    {
        $table = $query->getModel()->getTable();
        $columns = Schema::getColumnListing($table);
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $searchable = array_filter($config['searchable'] ?? [], fn ($column) => in_array($column, $columns, true));
            $query->where(function ($q) use ($search, $searchable): void {
                foreach ($searchable as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        foreach (['status', 'category_id', 'property_id', 'vehicle_id', 'pet_id', 'type'] as $filter) {
            if ($request->filled($filter) && in_array($filter, $columns, true)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $dateField = $config['date_field'] ?? collect($config['fields'] ?? [])->first(fn ($field) => in_array($field['type'] ?? '', ['date', 'datetime-local'], true))['name'] ?? null;
        if ($dateField && in_array($dateField, $columns, true)) {
            if ($request->filled('from')) {
                $query->whereDate($dateField, '>=', $request->date('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate($dateField, '<=', $request->date('to'));
            }
        }

        return $query;
    }

    private function validateRelations(array $data, array $config, User $user): void
    {
        foreach ($config['fields'] ?? [] as $field) {
            $name = $field['name'];
            $source = $field['source'] ?? null;
            if (! $source || blank($data[$name] ?? null)) {
                continue;
            }

            [$resource, $type] = array_pad(explode('/', $source, 2), 2, null);
            $table = match ($resource) {
                'categories' => 'categories', 'properties' => 'properties', 'providers' => 'service_providers',
                'users' => 'users', 'assets' => 'assets', 'vehicles' => 'vehicles', 'pets' => 'pets', 'plants' => 'plants', default => null,
            };
            if (! $table || ! Schema::hasTable($table)) {
                continue;
            }

            $exists = DB::table($table)->whereKey($data[$name])->when($type && $table === 'categories', fn ($q) => $q->where('type', $type))->exists();
            abort_unless($exists, 422, "The selected {$field['label']} is invalid.");
            if (! $user->hasRole('super-admin') && Schema::hasColumn($table, 'household_id')) {
                abort_unless(DB::table($table)->whereKey($data[$name])->where('household_id', $user->household_id)->exists(), 403, 'The selected relation is outside your household.');
            }
        }
    }

    private function storeFiles(ModuleRequest $request, string $module, array $config, array $data): array
    {
        foreach ($config['fields'] ?? [] as $field) {
            if (($field['type'] ?? null) !== 'file') {
                continue;
            }
            $name = $field['name'];
            $file = $request->file($name);
            unset($data[$name]);
            if ($file) {
                $data[$name] = $file->store('home-erp/'.$module, 'local');
                if ($module === 'documents') {
                    $data['original_name'] = $file->getClientOriginalName();
                    $data['mime_type'] = $file->getClientMimeType();
                    $data['size'] = $file->getSize();
                }
            }
        }

        return $data;
    }

    private function deleteFiles(string $module, $record): void
    {
        if ($module === 'documents' && filled($record->file_path)) {
            Storage::disk('local')->delete($record->file_path);
        }
    }

    private function log(Request $request, string $module, string $action, $record): void
    {
        ActivityLog::create([
            'household_id' => $record->household_id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'module' => $module,
            'record_type' => $record::class,
            'record_id' => $record->getKey(),
            'metadata' => ['ip' => $request->ip()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}

