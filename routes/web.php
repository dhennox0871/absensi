<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use Illuminate\Support\Facades\DB;

Route::get('/test-db', function () {
    try {
        // Coba query sederhana ke SQL Server
        $data = DB::table('masterstaff')->first();
        return response()->json([
            'status' => 'BERHASIL KONEK!',
            'database' => DB::connection()->getDatabaseName(),
            'data_sample' => $data
        ]);
    } catch (\Exception $e) {
        // Tampilkan Error Asli
        return response()->json([
            'status' => 'GAGAL KONEK',
            'error_message' => $e->getMessage() // <--- INI KUNCI MASALAHNYA
        ], 500);
    }
});
