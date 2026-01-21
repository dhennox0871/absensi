<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class AdminController extends Controller
{
    // 1. Ambil Semua Data Staff
    public function getAllStaff()
    {
        // Ambil data user, urutkan dari admin dulu, lalu nama
        $users = User::orderBy('staffcategoryid', 'desc') // Admin (1) diatas
                     ->orderBy('name', 'asc')
                     ->get();

        return response()->json([
            'message' => 'Data staff berhasil diambil',
            'data' => $users
        ]);
    }

    public function updateRole(Request $request)
    {
        // 1. Validasi
        $request->validate([
            'id' => 'required', // Ini akan menerima angka staffid dari Flutter
        ]);

        // 2. KOREKSI: Cari berdasarkan 'staffid'
        // Dulu: User::where('id', ... -> SALAH
        // Sekarang: User::where('staffid', ... -> BENAR
        $user = User::where('staffid', $request->id)->first();

        if (!$user) {
            // Debugging: Beritahu ID mana yang dicari tapi ga ketemu
            return response()->json(['message' => 'User dengan staffid ' . $request->id . ' tidak ditemukan.'], 404);
        }

        // 3. Update Role
        $input = $request->input('is_admin');
        
        // Cek input (1, "1", true, "true")
        if ($input == 1 || $input == '1' || $input === true || $input === 'true') {
            $user->staffcategoryid = 1; 
        } else {
            $user->staffcategoryid = 0; 
        }

        $user->save(); // Simpan ke database

        return response()->json([
            'message' => 'Hak akses berhasil diperbarui',
            'data' => $user // Kembalikan data user biar bisa dicek
        ]);
    }
}