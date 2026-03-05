<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'username' => $this->username,
            'real_name' => $this->real_name,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'employee_code' => $this->employee_code,
            'role' => $this->whenLoaded('role', function () {
                return new RoleResource($this->role);
            }),
            'department' => $this->whenLoaded('department', function () {
                return new DepartmentResource($this->department);
            }),
            'store' => $this->whenLoaded('store', function () {
                return new StoreResource($this->store);
            }),
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return new WarehouseResource($this->warehouse);
            }),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

