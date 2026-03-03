<?php

namespace App\Http\Resources;

use App\Domain\Checklists\DTOs\ChecklistProjection;
use App\Domain\Checklists\DTOs\EmployeeChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ChecklistProjection $projection */
        $projection = $this->resource;

        return [
            'summary' => $projection->summary->toArray(),
            'employees' => array_map(
                fn (EmployeeChecklist $employee) => EmployeeChecklistResource::make($employee)->toArray($request),
                $projection->employees
            ),
            'target' => $projection->target
                ? EmployeeChecklistResource::make($projection->target)->toArray($request)
                : null,
        ];
    }
}
