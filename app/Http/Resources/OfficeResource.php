<?php

namespace App\Http\Resources;

use App\Models\Image;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class OfficeResource extends JsonResource
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

            'user' => UserResource::make($this->user),
            'tags' => TagResource::collection($this->tags),
            'images' => ImageResource::collection($this->images),

            $this->merge(Arr::except(parent::toArray($request), [
                'user_id', 'created_at', 'updated_at', 'deleted_at'
            ]))
        ];
    }


}
