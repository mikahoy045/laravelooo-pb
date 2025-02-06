<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiTestController;
use App\Http\Controllers\API\PageController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\MediaController;
use App\Http\Controllers\API\TeamController;
use App\Http\Controllers\API\RoleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/**
 * @OA\Info(
 *     title="Laravelooo API",
 *     version="1.0.0"
 * )
 */

// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');


// Page routes
Route::get('/pages', [PageController::class, 'index']);
Route::get('/pages/{page}', [PageController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('pages', PageController::class)->except(['index', 'show']);
});

// Media routes
Route::get('/media', [MediaController::class, 'index']);
Route::get('/media/{media}', [MediaController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('media', MediaController::class)->except(['index', 'show']);
});

// Team routes
Route::get('/teams', [TeamController::class, 'index']);
Route::get('/teams/{team}', [TeamController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('teams', TeamController::class)->except(['index', 'show']);
});

// Role routes
Route::get('/roles', [RoleController::class, 'index']);
Route::get('/roles/{role}', [RoleController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('roles', RoleController::class)->except(['index', 'show']);
});
