<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\StoreResource;

class ProductResource extends JsonResource
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
            'description' => $this->description,
            'category' => $this->whenLoaded('category', function () {
                return new ProductCategoryResource($this->category);
            }),
            'barcode' => $this->barcode,
            'specification' => $this->specification,
            'unit' => $this->unit,
            'second_unit' => $this->second_unit,
            'conversion_rate' => $this->conversion_rate,
            'purchase_price' => $this->purchase_price,
            'retail_price' => $this->retail_price,
            'wholesale_price' => $this->wholesale_price,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'track_serial' => $this->track_serial,
            'track_batch' => $this->track_batch,
            'is_active' => $this->is_active,
            'store' => $this->whenLoaded('store', function () {
                return new StoreResource($this->store);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
