<?php

namespace App\Http\Controllers;

use App\Http\Resources\ImageResource;
use App\Models\Image;
use App\Models\Office;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImageController extends Controller
{
    public function store(Office $office) {

        request()->validate([
            'image'=> ['file', 'max:5000', 'mimes:jpg,png']
        ]);

        $this->authorize('update', $office);

        $path = request()->file('image')->storePublicly('images');

        $image = $office->images()->create([
            'path' => $path
        ]);

        return ImageResource::make($image);
    }


    public function destroy(Office $office, Image $image){


        abort_unless(auth()->user()->tokenCan('office.delete'), Response::HTTP_FORBIDDEN);

        $this->authorize('update', $office);

        // throw_if($image->resource_type != 'office' || $image->resource_id != $office->id, ValidationException::withMessages(['image' => 'Cannot delete this image']));

        throw_if($office->images()->count() == 1, ValidationException::withMessages(['image' => 'Cannot delete the only image']));

        throw_if($office->featured_image_id == $image->id, ValidationException::withMessages(['image' => 'Cannot delete the featured image']));

        Storage::delete('/images/'.$image->path);

        $image->delete();

    }
}
