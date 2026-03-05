<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_code' => $this->store_code,
            'name' => $this->name,
            'manager' => $this->manager,
            'phone' => $this->phone,
            'address' => $this->address,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'parent_store_id' => $this->parent_store_id,
            'parent' => $this->whenLoaded('parent', function () {
                return [
                    'id' => $this->parent?->id,
                    'store_code' => $this->parent?->store_code,
                    'name' => $this->parent?->name,
                ];
            }),
            'children' => $this->whenLoaded('children', function () {
                return $this->children?->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'store_code' => $c->store_code,
                        'name' => $c->name,
                    ];
                });
            }),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
