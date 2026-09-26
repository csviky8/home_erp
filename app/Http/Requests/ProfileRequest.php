<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:25'], 'avatar' => ['nullable', 'image', 'max:5120']];
    }
}
