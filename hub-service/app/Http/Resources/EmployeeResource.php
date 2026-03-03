<?php

namespace App\Http\Resources;

use App\Domain\Employees\DTOs\EmployeeSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeSnapshot
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EmployeeSnapshot $snapshot */
        $snapshot = $this->resource;

        return [
            'id' => $snapshot->id,
            'name' => $snapshot->name,
            'last_name' => $snapshot->lastName,
            'salary' => $snapshot->salary,
            'country' => $snapshot->country,
            'attributes' => $snapshot->attributes,
            'meta' => $snapshot->meta,
        ];
    }
}
