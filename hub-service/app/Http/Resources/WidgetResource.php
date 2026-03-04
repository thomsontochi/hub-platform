<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read string $id
 * @property-read string $type
 * @property-read string $label
 * @property-read string|null $icon
 * @property-read string $dataSource
 * @property-read array<int, string> $channels
 */
class WidgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'icon' => $this->icon,
            'data_source' => $this->dataSource,
            'channels' => $this->channels,
        ];
    }
}
