<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'supplier' => $this->whenLoaded('supplier', function () {
                return new SupplierResource($this->supplier);
            }),
            'items' => $this->whenLoaded('items', function () {
                return PurchaseOrderItemResource::collection($this->items);
            }),
            'order_date' => $this->order_date,
            'expected_delivery_date' => $this->expected_delivery_date,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax_amount' => $this->tax_amount,
            'shipping_cost' => $this->shipping_cost,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'delivery_status' => $this->delivery_status,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return new WarehouseResource($this->warehouse);
            }),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
