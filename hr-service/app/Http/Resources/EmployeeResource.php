<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = $this->resource instanceof Employee
            ? $this->resource->getAttribute('attributes') ?? []
            : ($this->resource['attributes'] ?? []);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'salary' => (float) $this->salary,
            'country' => $this->country,
            'attributes' => $attributes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
