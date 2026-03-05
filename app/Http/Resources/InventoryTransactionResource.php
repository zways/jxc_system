<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
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
            'transaction_number' => $this->transaction_number,
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_id' => $this->product_id,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'warehouse_id' => $this->warehouse_id,
            'transaction_type' => $this->transaction_type,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_document' => $this->notes,
            'batch_number' => $this->batch_number,
            'serial_number' => $this->serial_number,
            'production_date' => $this->production_date,
            'expiry_date' => $this->expiry_date,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'created_by' => $this->when($this->relationLoaded('createdBy') && $this->createdBy, function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }, $this->created_by),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
