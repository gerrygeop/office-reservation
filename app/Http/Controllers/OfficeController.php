<?php

namespace App\Http\Controllers;

use App\Http\Requests\OfficeRequest;
use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', Office::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(request('user_id'), fn ($builder) => $builder->whereUserId(request('user_id')))
            ->when(
                request('visitor_id'),
                fn (Builder $builder)
                => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id'))
            )
            ->when(
                request('lat') && request('lng'),
                fn ($builder) => $builder->nearestTo(request('lat'), request('lng')),
                fn ($builder) => $builder->orderBy('id', 'ASC')
            )
            ->with(['images', 'tags', 'user'])
            ->withCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->paginate(10);

        return OfficeResource::collection(
            $offices
        );
    }

    public function show(Office $office)
    {
        $office->loadCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])->load(['images', 'tags', 'user']);

        return OfficeResource::make($office);
    }

    public function create(OfficeRequest $request): JsonResource
    {
        abort_unless(
            auth()->user()->tokenCan('office.create'),
            Response::HTTP_FORBIDDEN
        );

        $attributes = $request->validated();

        $attributes['approval_status'] = Office::APPROVAL_PENDING;

        $office = DB::transaction(function () use ($attributes) {
            $office = auth()
                ->user()
                ->offices()
                ->create(Arr::except($attributes, ['tags']));

            if (isset($attributes['tags'])) {
                $office->tags()->attach($attributes['tags']);
            }

            return $office;
        });

        return OfficeResource::make(
            $office->load(['images', 'tags', 'user'])
        );
    }

    public function update(OfficeRequest $request, Office $office): JsonResource
    {
        abort_unless(
            auth()->user()->tokenCan('office.update'),
            Response::HTTP_FORBIDDEN
        );

        $this->authorize('update', $office);

        $attributes = $request->validated();

        DB::transaction(function () use ($office, $attributes) {
            $office->update(
                Arr::except($attributes, ['tags'])
            );

            if (isset($attributes['tags'])) {
                $office->tags()->sync($attributes['tags']);
            }
        });

        return OfficeResource::make(
            $office->load(['images', 'tags', 'user'])
        );
    }
}
