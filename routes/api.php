<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\KioskController; // Saya rapikan import Kiosk kesini

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- 1. ROUTE PUBLIC (TANPA LOGIN) ---

// Login Staff / Admin
Route::post('/login', [AuthController::class, 'login']);

// Login Wajah
Route::post('/login-face', [AuthController::class, 'loginFace']);

// Kiosk Mode (Tanpa Auth Sanctum karena device kantor)
Route::post('/kiosk/scan', [KioskController::class, 'scanWajah']);
Route::post('/kiosk/submit', [KioskController::class, 'submitAbsensi']);
Route::get('/kiosk/today', [KioskController::class, 'getTodayList']);


// --- 2. ROUTE PROTECTED (WAJIB LOGIN / ADA TOKEN) ---
Route::middleware('auth:sanctum')->group(function () {
    
    // A. Jalur Absensi Staff (HP Pribadi)
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::get('/attendance/history', [AttendanceController::class, 'history']);
    
    // B. Route Khusus Admin
    Route::get('/admin/staff', [AdminController::class, 'getAllStaff']);
    Route::post('/admin/update-role', [AdminController::class, 'updateRole']);
    Route::get('/admin/sync-faces', [AdminController::class, 'syncFaceData']);
    Route::post('/admin/register-face', [AdminController::class, 'registerFace']);

    // Admin Settings
    Route::get('/admin/settings', [SettingsController::class, 'index']);
    Route::post('/admin/settings', [SettingsController::class, 'update']);

    // --- PERBAIKAN DISINI (MASTER AREA) ---
    // Harus ada '/admin/' di depannya agar sesuai dengan Flutter
    // --- MASTER AREA (MODE BODY PARAMETER) ---
    
    // 1. Get List
    Route::get('/admin/areas', [AreaController::class, 'index']);
    
    // 2. Simpan (Insert & Update jadi satu pintu)
    Route::post('/admin/areas/save', [AreaController::class, 'save']); 
    
    // 3. Delete (ID dikirim di body)
    Route::post('/admin/areas/delete', [AreaController::class, 'delete']);
});
