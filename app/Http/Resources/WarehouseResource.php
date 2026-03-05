<?php

namespace App\Http\Resources;

use App\Http\Resources\StoreResource;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'location' => $this->location,
            'manager' => $this->manager,
            'description' => $this->description,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'store' => $this->whenLoaded('store', function () {
                return new StoreResource($this->store);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
