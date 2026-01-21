<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
// use Illuminate\Support\Facades\Hash; // Tidak dipakai karena plain text

class AuthController extends Controller
{
    // --- 1. FUNGSI LOGIN MANUAL (PLAIN TEXT PASSWORD) ---
    public function login(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'email'    => 'required', // NIK
            'password' => 'required',
            'is_admin' => 'nullable', 
        ]);

        $nikInput = $request->email;
        $passwordInput = $request->password;
        $isAdminLogin = $request->is_admin == '1'; 

        // 2. Cari Staff di Database (Masterstaff)
        $staff = DB::table('masterstaff')->where('staffcode', $nikInput)->first();

        if (!$staff) {
            return response()->json(['message' => 'NIK tidak ditemukan.'], 401);
        }

        // 3. Cari User (Tabel Users)
        $user = User::where('staffid', $staff->staffid)->first();

        if (!$user) {
            return response()->json(['message' => 'Akun User belum aktif.'], 401);
        }

        // 4. CEK PASSWORD (PLAIN TEXT)
        // Kita bandingkan langsung inputan dengan database
        if ($passwordInput != $user->password) {
            return response()->json(['message' => 'Password Salah.'], 401);
        }

        // --- 5. LOGIKA KHUSUS ADMINISTRATOR ---
        if ($isAdminLogin) {
            // ID Kategori yang boleh jadi Admin (0=Manager, 1=Supervisor)
            // Sesuaikan dengan data Bapak
            $allowedAdminCategories = [0, 1]; 

            if (!in_array($staff->staffcategoryid, $allowedAdminCategories)) {
                return response()->json([
                    'message' => 'Akses Ditolak. Anda bukan Administrator.'
                ], 403);
            }
        }

        // 6. Buat Token
        $token = $user->createToken('auth_token')->plainTextToken;
        
        // 7. Ambil Nama Jabatan
        $posisiDeskripsi = 'Staff';
        if (isset($staff->staffpositionid)) {
             $position = DB::table('masterstaffposition')
                    ->where('staffpositionid', $staff->staffpositionid)
                    ->first();
             if ($position) {
                 $posisiDeskripsi = $position->description;
             }
        }

        // 8. Kirim Response
        return response()->json([
            'message' => 'Login Berhasil',
            'access_token' => $token,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'staffid' => $staff->staffid,
                'name' => isset($staff->name) ? $staff->name : $staff->staffname,
                'staffcode' => $staff->staffcode,
                'staffcategoryid' => $staff->staffcategoryid, 
                'position' => ['description' => $posisiDeskripsi]
            ]
        ], 200);
    }

    // --- 2. FUNGSI LOGIN WAJAH (TETAP SAMA) ---
    public function loginFace(Request $request)
    {
        try {
            $request->validate(['image' => 'required|image|max:10240']);

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('public/temp_faces');
            }

            // SIMULASI: NIK User Test (5001)
            $simulasiStaffCode = '5001'; 

            $staff = DB::table('masterstaff')->where('staffcode', $simulasiStaffCode)->first();

            if (!$staff) {
                return response()->json(['message' => "Staff Code '$simulasiStaffCode' tidak ditemukan."], 401);
            }

            $user = User::where('staffid', $staff->staffid)->first();

            if (!$user) {
                return response()->json(['message' => 'User belum aktif.'], 401);
            }

            $token = $user->createToken('face_login')->plainTextToken;

            $posisiDeskripsi = 'Staff';
            if (isset($staff->staffpositionid)) {
                 $position = DB::table('masterstaffposition')
                        ->where('staffpositionid', $staff->staffpositionid)
                        ->first();
                 if ($position) $posisiDeskripsi = $position->description;
            }

            return response()->json([
                'message' => 'Login Wajah Berhasil',
                'access_token' => $token,
                'token' => $token,
                'user'    => [
                    'id'        => $user->id,
                    'staffid'   => $staff->staffid,
                    'name'      => isset($staff->name) ? $staff->name : $staff->staffname,
                    'email'     => $staff->staffcode,
                    'staffcode' => $staff->staffcode,
                    'staffcategoryid' => $staff->staffcategoryid,
                    'position'  => [
                        'description' => $posisiDeskripsi
                    ],
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server Error: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}