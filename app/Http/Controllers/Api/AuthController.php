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

    // --- FUNGSI LOGIN WAJAH (VERSI FINAL - FIX PATH DATABASE) ---
    public function loginFace(Request $request)
    {
        try {
            // 1. Validasi
            $request->validate(['image' => 'required|image|max:10240']);

            // 2. Simpan Foto Sementara
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('public/temp_faces');
                $fullPathLogin = storage_path('app/' . $path);
            } else {
                return response()->json(['message' => 'File foto wajib diupload.'], 400);
            }

            // 3. SETTING PATH (SESUAIKAN DENGAN PATH LAPTOP BAPAK)
            // Ini path yang Bapak kirim tadi (Sudah Benar)
            $pythonExe = "C:/Users/dhenn/AppData/Local/Programs/Python/Python311/python.exe"; 
            
            // Lokasi Script Python
            $pythonScript = base_path('cek_wajah.py'); 
            
            // --- BAGIAN PENTING YANG KURANG DI KODE BAPAK TADI ---
            // Kita kirim alamat lengkap folder database agar Python tidak tersesat
            $databaseFolder = storage_path('app/public/faces_db'); 
            // -----------------------------------------------------

            // Cek apakah python ada
            if (!file_exists($pythonExe)) {
                // Fallback darurat
                $pythonExe = "python"; 
            }

            // 4. EKSEKUSI SCRIPT
            // Perhatikan di ujung ada tambahan: ... . '" "' . $databaseFolder . '"';
            // Ini mengirim 3 data: Script, Foto Selfie, dan Lokasi Database
            $command = '"' . $pythonExe . '" -W ignore "' . $pythonScript . '" "' . $fullPathLogin . '" "' . $databaseFolder . '"';
            
            $output = shell_exec($command);
            
            // Hapus foto temp (Opsional)
            // @unlink($fullPathLogin);

            // 5. DIAGNOSA HASIL
            if (empty($output)) {
                return response()->json([
                    'message' => 'Gagal Login: Python tidak merespon.', 
                    'debug_cmd' => $command // Biar ketahuan kalau command salah
                ], 500);
            }

            $result = json_decode($output, true);

            // Cek jika output bukan JSON valid
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'message' => 'Output Python rusak.',
                    'raw_output' => $output 
                ], 500);
            }

            // 6. CEK STATUS LOGIN
            if (!isset($result['status']) || $result['status'] !== 'success') {
                $pesan = $result['message'] ?? 'Wajah tidak dikenali.';
                return response()->json(['message' => $pesan], 401);
            }

            // 7. LOGIN SUKSES
            $detectedNIK = $result['nik'];
            
            $staff = DB::table('masterstaff')->where('staffcode', $detectedNIK)->first();
            if (!$staff) return response()->json(['message' => "NIK $detectedNIK terdeteksi, tapi tidak ada di database."], 401);

            $user = User::where('staffid', $staff->staffid)->first();
            if (!$user) return response()->json(['message' => 'User belum aktif.'], 401);

            $token = $user->createToken('face_login')->plainTextToken;
            
            $posisiDeskripsi = 'Staff';
            if (isset($staff->staffpositionid)) {
                 $pos = DB::table('masterstaffposition')->where('staffpositionid', $staff->staffpositionid)->first();
                 if ($pos) $posisiDeskripsi = $pos->description;
            }

            return response()->json([
                'message' => 'Login Wajah Berhasil',
                'access_token' => $token,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'staffid' => $staff->staffid,
                    'name' => isset($staff->name) ? $staff->name : $staff->staffname,
                    'email' => $staff->staffcode,
                    'staffcode' => $staff->staffcode,
                    'staffcategoryid' => $staff->staffcategoryid,
                    'position' => ['description' => $posisiDeskripsi],
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }
}