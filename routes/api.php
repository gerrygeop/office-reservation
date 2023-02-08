<?php

use App\Http\Controllers\OfficeController;
use App\Http\Controllers\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/tags', TagController::class);
Route::get('/offices', [OfficeController::class, 'index']);
