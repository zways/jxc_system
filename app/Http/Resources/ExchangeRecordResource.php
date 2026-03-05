<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRecordResource extends JsonResource
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
            'exchange_number' => $this->exchange_number,
            'sale' => $this->whenLoaded('sale', function () {
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
            'exchange_date' => $this->exchange_date,
            'status' => $this->status,
            'reason' => $this->reason,
            'exchanged_by' => $this->exchanged_by,
            'exchanged_by_user' => $this->whenLoaded('exchangedBy', function () {
                return [
                    'id' => $this->exchangedBy?->id,
                    'name' => $this->exchangedBy?->name,
                ];
            }),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
