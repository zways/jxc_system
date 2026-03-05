<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryCountResource extends JsonResource
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
            'count_number' => $this->count_number,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return new WarehouseResource($this->warehouse);
            }),
            'type' => $this->type,
            'count_date' => $this->count_date,
            'counted_by' => $this->counted_by,
            'counted_by_user' => $this->whenLoaded('countedBy', function () {
                return new UserResource($this->countedBy);
            }),
            'status' => $this->status,
            'variance_amount' => $this->variance_amount,
            'notes' => $this->notes,
            'items_count' => $this->when(isset($this->items_count), fn () => (int) $this->items_count),
            'items' => $this->whenLoaded('items', function () {
                return InventoryCountItemResource::collection($this->items);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
