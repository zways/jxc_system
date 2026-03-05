<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
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
            'return_number' => $this->return_number,
            'sale' => $this->whenLoaded('sale', function () {
                // 退货页只需要订单号等基础信息
                return [
                    'id' => $this->sale?->id,
                    'order_number' => $this->sale?->order_number,
                ];
            }),
            'sale_id' => $this->sale_id,
            'customer' => $this->whenLoaded('customer', function () {
                return new CustomerResource($this->customer);
            }),
            'customer_id' => $this->customer_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return new WarehouseResource($this->warehouse);
            }),
            'warehouse_id' => $this->warehouse_id,
            'returned_by' => $this->returned_by,
            'returned_by_user' => $this->whenLoaded('returnedBy', function () {
                return [
                    'id' => $this->returnedBy?->id,
                    'name' => $this->returnedBy?->name,
                ];
            }),
            'return_date' => $this->return_date,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
