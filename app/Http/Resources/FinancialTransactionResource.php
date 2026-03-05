<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialTransactionResource extends JsonResource
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
            'type' => $this->type,
            'category' => $this->category,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_date' => $this->transaction_date,
            'description' => $this->description,
            'related_model_id' => $this->related_model_id,
            'related_model_type' => $this->related_model_type,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
