<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ColumnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'field' => $this->field,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'mask' => $this->mask,
        ];
    }
}
