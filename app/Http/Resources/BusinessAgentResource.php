<?php

namespace App\Http\Resources;

use App\Http\Resources\StoreResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessAgentResource extends JsonResource
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
            'agent_code' => $this->agent_code,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'commission_rate' => $this->commission_rate,
            'territory' => $this->territory,
            'status' => $this->status,
            'notes' => $this->notes,
            'store' => $this->whenLoaded('store', function () {
                return new StoreResource($this->store);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
