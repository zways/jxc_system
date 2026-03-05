<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
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
            'customer' => $this->whenLoaded('customer', function () {
                return new CustomerResource($this->customer);
            }),
            'items' => $this->whenLoaded('items', function () {
                return SalesOrderItemResource::collection($this->items);
            }),
            'order_date' => $this->order_date,
            'delivery_date' => $this->delivery_date,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax_amount' => $this->tax_amount,
            'shipping_cost' => $this->shipping_cost,
            'total_amount' => $this->total_amount,
            'order_type' => $this->order_type,
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
