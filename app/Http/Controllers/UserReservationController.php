<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Notifications\NewHostReservation;
use App\Notifications\NewReservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserReservationController extends Controller
{
    public function index()
    {
        abort_unless(
            auth()->user()->tokenCan('reservation.show'),
            Response::HTTP_FORBIDDEN
        );

        validator(request()->all(), [
            'status' => [Rule::in(Reservation::STATUS_ACTIVE, Reservation::STATUS_CANCEL)],
            'office_id' => ['integer'],
            'from_date' => ['date', 'required_with:to_date'],
            'to_date' => ['date', 'required_with:from_date', 'after:from_date'],
        ])->validate();

        $reservation = Reservation::query()
            ->where('user_id', auth()->id())
            ->when(
                request('office_id'),
                fn ($query) => $query->where('office_id', request('office_id'))
            )
            ->when(
                request('status'),
                fn ($query) => $query->where('status', request('status'))
            )
            ->when(
                request('from_date') && request('to_date'),
                fn ($query) => $query->betweenDates(request('from_date'), request('to_date'))
            )
            ->with(['office.featuredImage'])
            ->paginate(15);

        return ReservationResource::collection($reservation);
    }

    public function create()
    {
        abort_unless(
            auth()->user()->tokenCan('reservation.make'),
            Response::HTTP_FORBIDDEN
        );

        $data = validator(request()->all(), [
            'office_id' => ['required', 'integer'],
            'start_date' => ['required', 'date:Y-m-d', 'after:today'],
            'end_date' => ['required', 'date:Y-m-d', 'after:start_date'],
        ])->validate();

        try {
            $office = Office::findOrFail($data['office_id']);
        } catch (ModelNotFoundException $e) {
            throw ValidationException::withMessages([
                'office_id' => 'Invalid Office ID'
            ]);
        }

        if ($office->user_id == auth()->id()) {
            throw ValidationException::withMessages([
                'office_id' => 'You cannot make a reservation in your own office!'
            ]);
        }

        if ($office->hidden || $office->approval_status == Office::APPROVAL_PENDING) {
            throw ValidationException::withMessages([
                'office_id' => 'You cannot make a reservation on a hidden office!'
            ]);
        }

        $reservation = Cache::lock('reservations_office_' . $office->id, 10)->block(3, function () use ($data, $office) {
            $numberOfDays = Carbon::parse($data['end_date'])->endOfDay()->diffInDays(
                Carbon::parse($data['start_date'])->startOfDay()
            ) + 1;

            if ($office->reservations()->activeBetween($data['start_date'], $data['end_date'])->exists()) {
                throw ValidationException::withMessages([
                    'office_id' => 'You cannot make a reservation during this time!'
                ]);
            }

            $price = $numberOfDays * $office->price_per_day;

            if ($numberOfDays >= 28 && $office->monthly_discount) {
                $price = $price - ($price * $office->monthly_discount / 100);
            }

            return Reservation::create([
                'user_id' => auth()->id(),
                'office_id' => $office->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => Reservation::STATUS_ACTIVE,
                'price' => $price,
            ]);
        });

        Notification::send(
            auth()->user(),
            new NewReservation($reservation)
        );

        Notification::send(
            $office->user,
            new NewHostReservation($reservation)
        );

        return ReservationResource::make(
            $reservation->load('office')
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Reservation  $reservation
     * @return \Illuminate\Http\Response
     */
    public function show(Reservation $reservation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Reservation  $reservation
     * @return \Illuminate\Http\Response
     */
    public function edit(Reservation $reservation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Reservation  $reservation
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Reservation $reservation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Reservation  $reservation
     * @return \Illuminate\Http\Response
     */
    public function destroy(Reservation $reservation)
    {
        //
    }
}
