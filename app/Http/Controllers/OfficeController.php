<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Validators\OfficeValidator;
use App\Notifications\OfficeUpdated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OfficeController extends Controller
{
    public function index(Request $request)
    {

        $offices = Office::query()
            // ->where('approval_status', Office::APPROVAL_APPROVED)
            // ->where('hidden', false)
            ->when($request->filled('user_id') && $request->get('user_id') == auth()->id(), function ($q) {
                $q->where('approval_status', Office::APPROVAL_APPROVED)->where('hidden', false);
            })
            ->when($request->filled('user_id') && $request->get('user_id') == auth()->id(), function ($q) {
                $q->where('approval_status', Office::APPROVAL_APPROVED)->where('hidden', false);
            })
            ->when($request->filled('user_id'), fn($builder) => $builder->whereUserId($request->get('user_id')))
            ->when($request->filled('visitor_id'), fn(Builder $builder) => $builder->whereRelation('reservations', 'user_id', '=', $request->get('visitor_id')))
            ->when(
                $request->filled('lat') && $request->filled('lng'),
                fn($builder) => $builder->NearestTo(
                    $request->get('lat'),
                    $request->get('lng'),
                    fn($builder) => $builder->orderBy('id', 'ASC')
                )
            )
            ->when($request->filled('tags'), fn($builder) => $builder->whereHas(
                'tags',
                fn($builder) => $builder->whereIn('id', $request->get('tags')),
                '=',
                count($request->get('tags')),
            )
            )
            ->with(['tags', 'images', 'user'])
            ->withCount(['reservations' => fn(Builder $builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->latest('id')
            ->paginate(20);

        return OfficeResource::collection($offices);
    }


    public function show(Office $office): JsonResource
    {
        $office->loadCount(['reservations' => fn(Builder $builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->load(['tags', 'images', 'user']);

        return OfficeResource::make($office);
    }


    public function create(): JsonResource
    {
        abort_unless(auth()->user()->tokenCan('office.create'), Response::HTTP_FORBIDDEN);

        $data = (new OfficeValidator())->validate(request()->all(), $office = new Office());

        $data['user_id'] = auth()->id();

        $office = DB::transaction(function () use ($data, $office) {
            $office->fill(Arr::except($data, ['tags']))->save();
            if (isset($data['tags'])) {
                $office->tags()->attach($data['tags']);
            }
            return $office;
        });
        return OfficeResource::make($office);
    }


    public function update(Office $office): JsonResource
    {
        abort_unless(auth()->user()->tokenCan('office.update'), Response::HTTP_FORBIDDEN);

        $this->authorize('update', $office);

        $data = (new OfficeValidator())->validate(request()->all(), $office);

        $office->fill(Arr::except($data, ['tags']));
        if ($officeUpdated = $office->isDirty(['lat', 'lng', 'price_per_daya'])) {
            $office->fill(['approval_status' => Office::APPROVAL_PENDING]);
        }
        DB::transaction(function () use ($office, $data) {
            $office->save();
            if (isset($data['tags'])) {
                $office->tags()->sync($data['tags']);
            }
        });

        if ($officeUpdated) {
            Notification::send(User::where('is_admin', true)->get(), new OfficeUpdated);
        }

        return OfficeResource::make($office);
    }


    public function destroy(Office $office)
    {
        abort_unless(auth()->user()->tokenCan('office.delete'), Response::HTTP_FORBIDDEN);

        $this->authorize('delete', $office);

        throw_if($office->WhereHas('reservations', fn($builder) => $builder->where('status', '=', 1))->exists(), ValidationException::withMessages(['office' => 'Office can\'t be deleted']));

        $office->images()->each(function ($image) {
            Storage::delete('/images/' . $image->path);
            $image->delete();
        });

        $office->delete();
    }
}
