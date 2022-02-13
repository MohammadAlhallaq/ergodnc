<?php

use App\Http\Controllers\HostReservationController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserReservationController;
use App\Models\Office;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('tags', TagController::class);
Route::get('offices', [OfficeController::class, 'index']);
route::get('offices/{office}', [OfficeController::class, 'show']);
Route::post('offices', [OfficeController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
Route::put('offices/{office}', [OfficeController::class, 'update'])->middleware(['auth:sanctum', 'verified']);
Route::delete('offices/{office}', [OfficeController::class, 'destroy'])->middleware(['auth:sanctum', 'verified']);

Route::post('offices/{office}/images', [ImageController::class, 'store'])->middleware(['auth:sanctum', 'verified']);
Route::delete('offices/{office}/images/{image:id}', [ImageController::class, 'destroy'])->middleware(['auth:sanctum', 'verified']);

Route::get('reservations', [UserReservationController::class, 'index'])->middleware(['auth:sanctum', 'verified']);
Route::post('reservations', [UserReservationController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
Route::post('reservations/{reservation}', [UserReservationController::class, 'cancel'])->middleware(['auth:sanctum', 'verified']);

Route::get('host/reservations', [HostReservationController::class, 'index'])->middleware(['auth:sanctum', 'verified']);






