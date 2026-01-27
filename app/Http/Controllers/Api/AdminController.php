<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// --- IMPORT WAJIB (JANGAN DIHAPUS) ---
use App\Models\User;                    // Untuk ambil list staff (versi Bapak)
use Illuminate\Support\Facades\DB;      // Untuk cek database
use Illuminate\Support\Facades\Storage; // Untuk upload foto
// -------------------------------------

class AdminController extends Controller
{
    // =======================================================================
    // 1. BAGIAN LIST STAFF (SOLUSI ANTI-BLANK)
    // =======================================================================
    
    // Kita buat 2 fungsi dengan nama berbeda tapi isinya sama.
    // Tujuannya: Agar route 'getAllStaff' ATAU 'getStaffList' sama-sama jalan.
    
    public function getAllStaff() {
        return $this->internalGetStaffLogic();
    }

    public function getStaffList() {
        return $this->internalGetStaffLogic();
    }

    // --- LOGIKA ASLI (MODEL USER) ---
    private function internalGetStaffLogic()
    {
        try {
            // Menggunakan kode asli Bapak (Model User)
            $users = User::orderBy('staffcategoryid', 'desc') 
                         ->orderBy('name', 'asc')
                         ->get();

            return response()->json([
                'message' => 'Data staff berhasil diambil',
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            // Jika error, kirim pesan jelas biar ketahuan salahnya dimana
            return response()->json([
                'message' => 'Gagal Load Data: ' . $e->getMessage()
            ], 500);
        }
    }

    // =======================================================================
    // 2. BAGIAN UPDATE ROLE (MODEL USER)
    // =======================================================================
    public function updateRole(Request $request)
    {
        $request->validate(['id' => 'required']); // staffid

        // Cari di tabel User sesuai permintaan
        $user = User::where('staffid', $request->id)->first();

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        $input = $request->input('is_admin');
        
        // Logika 1 = Admin, 0 = User
        if ($input == 1 || $input == '1' || $input === true || $input === 'true') {
            $user->staffcategoryid = 1; 
        } else {
            $user->staffcategoryid = 0; 
        }

        $user->save(); 

        return response()->json([
            'message' => 'Hak akses berhasil diperbarui',
            'data' => $user 
        ]);
    }

    // =======================================================================
    // 3. BAGIAN UPLOAD WAJAH (FITUR BARU 3 SLOT)
    // =======================================================================
    public function registerFace(Request $request)
    {
        try {
            $request->validate([
                'staff_id' => 'required',           
                'image'    => 'required|image|max:10240', 
            ]);

            // Cari NIK di Masterstaff (Lebih aman untuk urusan file)
            $staff = DB::table('masterstaff')->where('staffid', $request->staff_id)->first();
            
            if (!$staff) {
                return response()->json(['message' => 'Staff ID tidak ditemukan.'], 404);
            }

            $nik = $staff->staffcode;
            $targetSlot = 0;

            // A. Cek Slot Kosong (1, 2, 3)
            for ($i = 1; $i <= 3; $i++) {
                $cekName = "{$nik}_{$i}.jpg";
                if (!Storage::exists("public/faces_db/" . $cekName)) {
                    $targetSlot = $i;
                    break;
                }
            }

            // B. Jika Penuh, Timpa File Terlama
            if ($targetSlot == 0) {
                $oldestTime = time() + 999999999;
                $oldestSlot = 1;
                for ($i = 1; $i <= 3; $i++) {
                    $cekName = "{$nik}_{$i}.jpg";
                    $fileTime = Storage::exists("public/faces_db/" . $cekName) 
                                ? Storage::lastModified("public/faces_db/" . $cekName) 
                                : 0;
                    
                    if ($fileTime < $oldestTime) {
                        $oldestTime = $fileTime;
                        $oldestSlot = $i;
                    }
                }
                $targetSlot = $oldestSlot;
            }

            // C. Simpan File
            $finalName = "{$nik}_{$targetSlot}.jpg";
            $path = $request->file('image')->storeAs('public/faces_db', $finalName);

            return response()->json([
                'message' => "Wajah didaftarkan di Slot $targetSlot (Total 3).",
                'path' => $path,
                'staff_code' => $nik
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal upload: ' . $e->getMessage()], 500);
        }
    }

    // --- FUNGSI UPDATE DATABASE WAJAH (DIPANGGIL ADMIN) ---
    public function syncFaceData()
    {
        // Pastikan hanya Admin yang boleh akses (Opsional: tambahkan middleware di route)
        try {
            // Path Python
            $pythonExe = "C:/Users/dhenn/AppData/Local/Programs/Python/Python311/python.exe"; 
            
            // Path Script Training
            $scriptPath = base_path('latih_wajah.py');
            
            if (!file_exists($scriptPath)) {
                return response()->json(['message' => 'Script latih_wajah.py tidak ditemukan di server.'], 500);
            }

            // Eksekusi
            $command = '"' . $pythonExe . '" "' . $scriptPath . '" 2>&1';
            $output = shell_exec($command);
            
            // Cek apakah output mengandung kata "SUKSES"
            if (strpos($output, 'SUKSES') !== false) {
                 return response()->json([
                    'status' => 'success',
                    'message' => 'Database Wajah Berhasil Diperbarui!',
                    'detail' => $output
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal Update Database.',
                    'detail' => $output
                ], 500);
            }
            
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }
}