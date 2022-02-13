<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ImageResource extends JsonResource
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

            'path' =>  Storage::url($this->path),
            $this->merge(Arr::except(parent::toArray($request), [
                'updated_at', 'created_at',  'resource_type', 'resource_id'
            ]))
        ];
    }
}
