<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\SettingsController;

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

/*Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});*/


// Alamatnya nanti: http://ip-laptop-anda:8000/api/login
Route::post('/login', [AuthController::class, 'login']);

// Route Login Wajah (POST)
Route::post('/login-face', [AuthController::class, 'loginFace']);


Route::middleware('auth:sanctum')->group(function () {
    // Jalur Absensi
    Route::post('/attendance', [AttendanceController::class, 'store']);

    // --- TAMBAHKAN INI: Route Ambil History ---
    Route::get('/attendance/history', [AttendanceController::class, 'history']);
    
    // Route Khusus Admin
    Route::get('/admin/staff', [AdminController::class, 'getAllStaff']);
    Route::post('/admin/update-role', [AdminController::class, 'updateRole']);

    // --- ROUTE ADMIN SETTINGS (PASTIKAN INI ADA) ---
    // 1. Untuk mengambil data (GET)
    Route::get('/admin/settings', [SettingsController::class, 'index']);
    
    // 2. Untuk menyimpan data (POST)
    Route::post('/admin/settings', [SettingsController::class, 'update']);

    
});
