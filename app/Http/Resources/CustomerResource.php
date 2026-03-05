<?php

namespace App\Http\Resources;

use App\Http\Resources\StoreResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'customer_code' => $this->customer_code,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'tax_number' => $this->tax_number,
            'credit_limit' => $this->credit_limit,
            'outstanding_amount' => $this->outstanding_amount,
            'customer_level' => $this->customer_level,
            'payment_terms' => $this->payment_terms,
            'rating' => $this->rating,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'store' => $this->whenLoaded('store', function () {
                return new StoreResource($this->store);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
