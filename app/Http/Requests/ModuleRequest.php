<?php

namespace App\Http\Requests;

use App\Support\ModuleRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModuleRequest extends FormRequest
{
    /**
     * Check the permission here rather than only in the controller, so a denied request is a
     * clean 403 instead of a 422 that leaks the field rules of the module.
     *
     * Module abilities are named view/create/edit/delete (see HomeErpSeeder), so PUT maps to `edit`.
     */
    public function authorize(): bool
    {
        $module = config('home_modules.'.$this->route('module'));
        abort_if($module === null, 404, 'Module not found.');

        $ability = match ($this->getMethod()) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'edit',
            'DELETE' => 'delete',
            default => 'view',
        };

        $user = $this->user();
        abort_if(! app(ModuleRegistry::class)->can($user, $module, $ability), 403, 'You do not have permission for this module.');

        // Record level operations must also belong to a household the user can reach.
        $record = $this->route('record');
        if ($record !== null && in_array($this->getMethod(), ['PUT', 'PATCH', 'DELETE'], true)) {
            $model = ($module['model'])::query()->forUser($user)->findOrFail($record);
            abort_unless($model->canAccess($user), 403, 'This record belongs to another household.');
        }

        return true;
    }

    public function rules(): array
    {
        $module = config('home_modules.'.$this->route('module'), []);
        $isCreate = $this->isMethod('post');
        $rules = [];

        foreach ($module['fields'] ?? [] as $field) {
            $name = $field['name'];
            $required = $isCreate && ($field['required'] ?? false);
            $parts = [$required ? 'required' : 'nullable'];

            if (($field['type'] ?? null) === 'number') {
                $parts[] = 'numeric';
                $parts[] = 'min:0';
            } elseif (($field['type'] ?? null) === 'checkbox') {
                $parts[] = 'boolean';
            } elseif (in_array($field['type'] ?? null, ['date', 'datetime-local'], true)) {
                $parts[] = 'date';
            } elseif (($field['type'] ?? null) === 'file') {
                $parts = [$required ? 'required' : 'nullable', 'file', 'max:10240'];
                // Use `extensions` rather than `mimetypes`: it validates the real detected type
                // against the allowed list, while `mimetypes` fails on perfectly valid uploads.
                $parts[] = 'extensions:'.($name === 'file_path' ? 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx' : 'jpg,jpeg,png,webp,pdf');
            } elseif (($field['type'] ?? null) === 'select' && isset($field['options'])) {
                $parts[] = Rule::in($field['options']);
            } elseif ($this->isRelationField($field)) {
                // Relation dropdowns carry a numeric id, not free text. Existence and household
                // scope are enforced by ModuleController::validateRelations(), so only the type
                // is checked here.
                $parts[] = 'integer';
            } else {
                $parts[] = 'string';
                $parts[] = 'max:'.($field['type'] === 'textarea' ? 10000 : 255);
            }

            if (! $isCreate) {
                $parts[0] = 'sometimes';
                // `sometimes` still runs the other rules for a key that is present, so an
                // explicitly cleared date would fail the `date` rule on null. Allow it.
                if (in_array($field['type'] ?? null, ['date', 'datetime-local'], true)) {
                    $parts[] = 'nullable';
                }
            }

            $rules[$name] = array_values(array_unique($parts));
        }

        return $rules;
    }

    /**
     * A relation field is a select backed by another table (it declares a `source`), so its
     * value is a numeric id. Plain selects without a source are free text columns and are
     * left to the string rule.
     */
    private function isRelationField(array $field): bool
    {
        return ($field['type'] ?? null) === 'relation'
            || (($field['type'] ?? null) === 'select' && ! empty($field['source']));
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach (config('home_modules.'.$this->route('module').'.fields', []) as $field) {
            $attributes[$field['name']] = strtolower($field['label'] ?? $field['name']);
        }

        return $attributes;
    }
}
