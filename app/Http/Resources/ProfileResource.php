<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->telepon,
            'image_url' => $this->image_url ? url('storage/'. $this->image_url) : url('default/images/default_images.jpg'),
            'fcm_token' => $this->fcm_token,
            'role' => $this->role,
            'branch' => $this->role === 'User' ? $this->branch->name : 'Central',
            'code_branch' => $this->role === 'User' ? $this->branch->code : 'CEN',
        ];
    }
}
