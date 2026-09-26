<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        foreach (array_keys($data) as $key) {
            if (str_ends_with($key, '_path')) {
                unset($data[$key]);
            }
        }
        $data['has_file'] = collect($this->resource->getAttributes())->contains(fn ($value, $key) => str_ends_with($key, '_path') && filled($value));

        return $data;
    }
}
