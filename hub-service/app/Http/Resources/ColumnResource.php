<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read string $field
 * @property-read string $key
 * @property-read string $label
 * @property-read string $type
 * @property-read string|null $mask
 */
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
