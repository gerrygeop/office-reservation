<?php

use App\Http\Controllers\HostReservationController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserReservationController;
use Illuminate\Support\Facades\Route;


//Office
Route::get('/tags', TagController::class);
Route::get('/offices', [OfficeController::class, 'index']);
Route::get('/offices/{office}', [OfficeController::class, 'show']);
Route::post('/offices', [OfficeController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
Route::put('/offices/{office}', [OfficeController::class, 'update'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}', [OfficeController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);

//Office Image
Route::post('/offices/{office}/images', [ImageController::class, 'store'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}/images/{image:id}', [ImageController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);

//User Reservation
Route::get('/reservations', [UserReservationController::class, 'index'])->middleware(['auth:sanctum', 'verified']);
Route::post('/reservations', [UserReservationController::class, 'create'])->middleware(['auth:sanctum', 'verified']);

//Host Reservation
Route::get('/host/reservations', [HostReservationController::class, 'index'])->middleware(['auth:sanctum', 'verified']);
