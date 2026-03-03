<?php

namespace App\Http\Resources;

use App\Domain\Checklists\DTOs\EmployeeChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeChecklistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EmployeeChecklist $checklist */
        $checklist = $this->resource;

        return [
            'employee' => $checklist->employee->toArray(),
            'fields' => array_map(
                fn ($field) => FieldStatusResource::make($field)->toArray($request),
                $checklist->fields
            ),
            'completion_rate' => $checklist->completionRate,
            'status' => $checklist->status,
            'evaluated_at' => $checklist->evaluatedAt?->toAtomString(),
        ];
    }
}
