<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// --- IMPORT WAJIB ---
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon; // PENTING: Untuk catat waktu update
// --------------------

class AdminController extends Controller
{
    // =======================================================================
    // 1. LIST STAFF (GET)
    // =======================================================================
    public function getAllStaff() {
        return $this->internalGetStaffLogic();
    }

    public function getStaffList() {
        return $this->internalGetStaffLogic();
    }

    private function internalGetStaffLogic()
    {
        try {
            // Ambil data user, urutkan Admin (1) diatas User (0), lalu nama A-Z
            $users = User::orderBy('staffcategoryid', 'desc') 
                         ->orderBy('name', 'asc')
                         ->get();

            return response()->json([
                'message' => 'Data staff berhasil diambil',
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal Load Data: ' . $e->getMessage()], 500);
        }
    }

    // =======================================================================
    // 2. UPDATE ROLE ADMIN (POST)
    // =======================================================================
    public function updateRole(Request $request)
    {
        $request->validate(['id' => 'required']); // staffid

        $user = User::where('staffid', $request->id)->first();
        if (!$user) return response()->json(['message' => 'User tidak ditemukan.'], 404);

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
    // 3. UPLOAD WAJAH (POST)
    // =======================================================================
    public function registerFace(Request $request)
    {
        try {
            $request->validate([
                'staff_id' => 'required',           
                'image'    => 'required|image|max:10240', 
            ]);

            $staff = DB::table('masterstaff')->where('staffid', $request->staff_id)->first();
            if (!$staff) return response()->json(['message' => 'Staff ID tidak ditemukan.'], 404);

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
                'message' => "Wajah didaftarkan di Slot $targetSlot.",
                'path' => $path,
                'staff_code' => $nik
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal upload: ' . $e->getMessage()], 500);
        }
    }

    // =======================================================================
    // 4. UPDATE DATABASE WAJAH AI (GET)
    // =======================================================================
    public function syncFaceData()
    {
        try {
            // Path Python (Sesuaikan dengan server Bapak)
            $pythonExe = "C:/Users/dhenn/AppData/Local/Programs/Python/Python311/python.exe"; 
            $scriptPath = base_path('latih_wajah.py');
            
            if (!file_exists($scriptPath)) return response()->json(['message' => 'Script latih_wajah.py hilang.'], 500);

            $command = '"' . $pythonExe . '" "' . $scriptPath . '" 2>&1';
            $output = shell_exec($command);
            
            if (strpos($output, 'SUKSES') !== false) {
                 return response()->json(['status' => 'success', 'message' => 'AI Updated!', 'detail' => $output], 200);
            } else {
                return response()->json(['status' => 'error', 'message' => 'Gagal Update AI.', 'detail' => $output], 500);
            }
            
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    // =======================================================================
    // 5. UPDATE PENEMPATAN AREA KERJA (POST) - [PINDAHAN DARI STAFF CONTROLLER]
    // =======================================================================
    public function updatePlacement(Request $request)
    {
        $staffId = $request->input('staffid');
        $areaId = $request->input('areaid');

        if (!$staffId) return response()->json(['message' => 'Staff ID Required'], 400);

        // Normalisasi null (String "null" -> NULL Database)
        if ($areaId === 'null' || $areaId === 0 || $areaId === '0' || empty($areaId)) {
            $areaId = null;
        }

        try {
            DB::table('masterstaff')->where('staffid', $staffId)->update([
                'freeinteger1' => $areaId, // freeinteger1 = Lokasi Kerja
                'modifydate'   => Carbon::now(),
                'modifyby'     => 'ADMIN'
            ]);

            return response()->json([
                'status' => 'success', 
                'message' => 'Penempatan Berhasil Diupdate'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}