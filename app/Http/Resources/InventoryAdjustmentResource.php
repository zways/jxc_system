<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAdjustmentResource extends JsonResource
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
            'adjustment_number' => $this->adjustment_number,
            'product' => $this->whenLoaded('product', function () {
                return new ProductResource($this->product);
            }),
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return new WarehouseResource($this->warehouse);
            }),
            'adjustment_type' => $this->adjustment_type,
            'quantity' => $this->quantity,
            'adjustment_reason' => $this->adjustment_reason,
            'adjusted_by' => $this->adjusted_by,
            'adjustment_date' => $this->adjustment_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
