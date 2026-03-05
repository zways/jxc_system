<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountsReceivableResource extends JsonResource
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
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'customer_id' => $this->customer_id,
            'document_type' => $this->document_type,
            'document_id' => $this->document_id,
            'document_date' => $this->document_date,
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
