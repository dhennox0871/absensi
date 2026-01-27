<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Str;

class KioskController extends Controller
{
    // --- 1. API SCAN WAJAH ---
    public function scanWajah(Request $request)
    {
        try {
            // A. VALIDASI LOKASI
            $setting = DB::table('flexnotesetting')->where('settingtypecode', 'customerinfo2')->first();
            
            $officeLat = $setting ? (float)$setting->datadecimal1 : 0;
            $officeLng = $setting ? (float)$setting->datadecimal2 : 0;
            $maxRadiusMeter = $setting ? ((float)$setting->datadecimal3 * 1000) : 100000; 

            $userLat = $request->input('lat');
            $userLng = $request->input('lng');

            if ($userLat && $userLng) {
                $jarakMeter = $this->hitungJarak($userLat, $userLng, $officeLat, $officeLng);
                if ($jarakMeter > $maxRadiusMeter) {
                    return response()->json([
                        'status' => 'error', 
                        'message' => "Jarak Terlalu Jauh: " . round($jarakMeter) . "m"
                    ], 403);
                }
            }

            // B. PROSES PYTHON
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('public/temp_kiosk');
                $fullPathLogin = storage_path('app/' . $path);
            } else {
                return response()->json(['message' => 'Foto wajib ada'], 400);
            }

            // PATH PYTHON (Sesuaikan Laptop Bapak)
            $pythonExe = "C:/Users/dhenn/AppData/Local/Programs/Python/Python311/python.exe"; 
            $scriptPath = base_path('cek_wajah.py');
            
            $command = '"' . $pythonExe . '" "' . $scriptPath . '" "' . $fullPathLogin . '"';
            $output = shell_exec($command);
            @unlink($fullPathLogin); 
            
            $result = json_decode($output, true);

            if (!isset($result['status']) || $result['status'] !== 'success') {
                return response()->json(['status' => 'error', 'message' => 'Wajah tidak dikenali.'], 401);
            }

            // C. AMBIL DATA STAFF
            $staff = DB::table('masterstaff')->where('staffcode', $result['nik'])->first();
            if (!$staff) return response()->json(['status' => 'error', 'message' => "NIK tidak ditemukan."], 404);

            $jabatan = '-';
            if (isset($staff->staffpositionid)) {
                $pos = DB::table('masterstaffposition')->where('staffpositionid', $staff->staffpositionid)->first();
                if ($pos) $jabatan = $pos->description;
            }

            // Estimasi Jam (WIB)
            $now = Carbon::now('Asia/Jakarta');
            $jamSekarang = $now->hour;
            $shiftTampil = ($jamSekarang >= 14) ? 2 : 1;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'staff_id'   => $staff->staffid,
                    // PERBAIKAN: Prioritaskan kolom 'name'
                    'staff_name' => $staff->name ?? $staff->staffname, 
                    'staff_code' => $staff->staffcode,
                    'jabatan'    => $jabatan,
                    'jam'        => $now->format('H:i'), 
                    'shift_est'  => $shiftTampil,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    // --- 2. API SUBMIT ABSENSI ---
    public function submitAbsensi(Request $request)
    {
        $request->validate([
            'image'     => 'required|image|max:5120',
            'latitude'  => 'required',
            'longitude' => 'required',
            'staff_id'  => 'required',
        ]);

        $kioskAdminId = '0'; // System ID

        $targetStaffId = $request->staff_id;
        $now = Carbon::now('Asia/Jakarta');
        $nowDate = $now->toDateString();
        $nowMinutes = ($now->hour * 60) + $now->minute;

        // Cek Spam (1 Menit)
        $lastLog = Attendance::where('staffid', $targetStaffId)
                             ->where('entrydate', $nowDate)
                             ->orderBy('createdate', 'desc')
                             ->first();
                             
        if ($lastLog && $now->diffInMinutes(Carbon::parse($lastLog->createdate)) < 1) {
            return response()->json(['message' => 'Baru saja absen. Tunggu sebentar.'], 400); 
        }

        // Hitung Shift
        $workHour = DB::table('masterworkinghour')->where('workinghourcode', 'HL')->first();
        $jamMasukDB = $workHour->starthour ?? 480; 
        $jamPulangDB = $workHour->endhour ?? 1020; 
        
        $shiftDetermined = 1;
        if ($nowMinutes < ($jamMasukDB + 180)) $shiftDetermined = 1; 
        elseif ($nowMinutes > ($jamPulangDB - 120)) $shiftDetermined = 4;
        else {
            if ($lastLog) {
                if ($lastLog->shift == 1) $shiftDetermined = 2;
                elseif ($lastLog->shift == 2) $shiftDetermined = 3;
                else $shiftDetermined = 2;
            } else $shiftDetermined = 1; 
        }

        // Simpan Foto
        $filename = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('public/attendance');
            $filename = basename($path);
        }

        try {
            Attendance::create([
                'staffid'           => $targetStaffId,
                'entrydate'         => $nowDate,
                'shour'             => $now->hour,
                'sminute'           => $now->minute,
                'ssec'              => $now->second,
                'status'            => 'F',
                'shift'             => $shiftDetermined,
                'freedescription1'  => $filename,
                'freedescription2'  => $request->longitude,
                'freedescription3'  => $request->latitude,
                'absentransentryno' => (string) Str::uuid(),
                'createby'          => $kioskAdminId, 
                'createdate'        => $now,
                'modifyby'          => $kioskAdminId,
                'modifydate'        => $now,
            ]);

            // === PERBAIKAN UTAMA DISINI ===
            // Ganti 'staffname' menjadi 'name'
            $staffName = DB::table('masterstaff')
                            ->where('staffid', $targetStaffId)
                            ->value('name'); 
            // ==============================

            return response()->json([
                'status'  => 'success',
                'message' => "Absensi Berhasil Disimpan!",
                'detail' => ['nama' => $staffName, 'shift' => $shiftDetermined, 'jam' => $now->format('H:i')]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // --- 3. LIST HARI INI ---
    public function getTodayList()
    {
        $list = DB::table('absentrans')
            ->join('masterstaff', 'absentrans.staffid', '=', 'masterstaff.staffid')
            ->whereDate('absentrans.entrydate', Carbon::now('Asia/Jakarta')->toDateString())
            // Pastikan select 'name', bukan 'staffname'
            ->select('masterstaff.name as nama', 'absentrans.createdate', 'absentrans.freedescription1 as foto')
            ->orderBy('absentrans.createdate', 'desc')
            ->take(20)
            ->get()
            ->map(function($item) {
                return [
                    'nama' => $item->nama,
                    'jam' => Carbon::parse($item->createdate)->format('H:i'),
                    'foto_url' => url('storage/attendance/'.$item->foto)
                ];
            });

        return response()->json(['data' => $list]);
    }

    private function hitungJarak($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
}