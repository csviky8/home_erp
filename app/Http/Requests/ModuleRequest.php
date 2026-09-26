<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
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
                $parts[] = 'mimetypes:'.($name === 'file_path' ? 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx' : 'jpg,jpeg,png,webp,pdf');
            } elseif (($field['type'] ?? null) === 'select' && isset($field['options'])) {
                $parts[] = Rule::in($field['options']);
            } else {
                $parts[] = 'string';
                $parts[] = 'max:'.($field['type'] === 'textarea' ? 10000 : 255);
            }

            if (! $isCreate) {
                $parts[0] = 'sometimes';
            }

            $rules[$name] = array_values(array_unique($parts));
        }

        return $rules;
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
