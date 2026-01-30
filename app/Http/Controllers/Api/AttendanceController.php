<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'image'     => 'required|image|max:5120', // Max 5MB
            'latitude'  => 'required',
            'longitude' => 'required',
        ]);

        try {
            $user = $request->user(); // Ambil user yang sedang login di HP
            
            // Ambil data detail staff
            $staff = DB::table('masterstaff')->where('staffid', $user->staffid)->first();
            if (!$staff) return response()->json(['message' => 'Data staff tidak ditemukan'], 404);

            // =================================================================
            // LOGIKA PENGECEKAN LOKASI (UPDATED: GUNAKAN freeinteger1)
            // =================================================================

            // A. Ambil Radius Toleransi dari Setting Admin
            $setting = DB::table('flexnotesetting')->where('settingtypecode', 'customerinfo2')->first();
            $maxRadiusMeter = $setting ? ((float)$setting->datadecimal3 * 1000) : 100; // Default 100m

            // B. Cek Penempatan Kerja (freeinteger1), BUKAN areaid (Domisili)
            // freeinteger1 berisi ID dari tabel masterarea
            if (!empty($staff->freeinteger1)) {
                
                // Ambil koordinat dari Master Area berdasarkan freeinteger1
                $area = DB::table('masterarea')->where('areaid', $staff->freeinteger1)->first();
                
                if ($area) {
                    // Ambil koordinat target (Kantor/Pabrik/Site)
                    // Pastikan menggunakan latitude1/longitude1 sesuai database Anda
                    $targetLat = (float)$area->latitude1; 
                    $targetLng = (float)$area->longitude1;

                    // Lokasi User Saat Ini
                    $userLat = (float)$request->latitude;
                    $userLng = (float)$request->longitude;

                    // Hitung Jarak
                    $jarak = $this->hitungJarak($userLat, $userLng, $targetLat, $targetLng);

                    if ($jarak > $maxRadiusMeter) {
                        return response()->json([
                            'message' => "Gagal Absen! Anda berada di luar area penempatan ($area->description). Jarak: " . round($jarak) . "m (Maks: " . round($maxRadiusMeter) . "m)"
                        ], 403);
                    }
                }
            } 
            // Jika freeinteger1 NULL, berarti Staff Mobile/Bebas (Tidak ada batasan radius)

            // =================================================================

            // 3. Cek Spam (Mencegah absen double dalam 1 menit)
            $now = Carbon::now('Asia/Jakarta');
            $today = $now->toDateString();
            
            $lastLog = Attendance::where('staffid', $staff->staffid)
                        ->where('entrydate', $today)
                        ->orderBy('createdate', 'desc')
                        ->first();

            if ($lastLog && $now->diffInMinutes(Carbon::parse($lastLog->createdate)) < 1) {
                return response()->json(['message' => 'Anda baru saja absen, tunggu sebentar.'], 400);
            }

            // 4. Hitung Shift Otomatis
            $nowMinutes = ($now->hour * 60) + $now->minute;
            $workHour = DB::table('masterworkinghour')->where('workinghourcode', 'HL')->first();
            $jamMasukDB = $workHour->starthour ?? 480; 
            $jamPulangDB = $workHour->endhour ?? 1020; 

            $shiftDetermined = 1;
            if ($nowMinutes < ($jamMasukDB + 180)) $shiftDetermined = 1; // Masuk
            elseif ($nowMinutes > ($jamPulangDB - 120)) $shiftDetermined = 4; // Pulang
            else {
                if ($lastLog) {
                    if ($lastLog->shift == 1) $shiftDetermined = 2; // Keluar Istirahat
                    elseif ($lastLog->shift == 2) $shiftDetermined = 3; // Masuk Istirahat
                    else $shiftDetermined = 2;
                } else {
                    $shiftDetermined = 1; 
                }
            }

            // 5. Upload Foto
            $filename = null;
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('public/attendance');
                $filename = basename($path);
            }

            // 6. Simpan ke Database
            Attendance::create([
                'staffid'           => $staff->staffid,
                'entrydate'         => $today,
                'shour'             => $now->hour,
                'sminute'           => $now->minute,
                'ssec'              => $now->second,
                'status'            => 'F', 
                'shift'             => $shiftDetermined,
                'freedescription1'  => $filename,           
                'freedescription2'  => $request->longitude, 
                'freedescription3'  => $request->latitude,  
                'absentransentryno' => (string) Str::uuid(),
                
                'createby'          => $user->staffid, 
                'createdate'        => $now,
                'modifyby'          => $user->staffid,
                'modifydate'        => $now,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Absensi berhasil dicatat',
                'shift_detect' => $shiftDetermined
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    // --- FUNCTION HISTORY ---
    public function history(Request $request)
    {
        $user = $request->user();
        $startDate = Carbon::now()->subDays(7)->toDateString();
        
        $data = DB::table('absentrans')
            ->where('staffid', $user->staffid)
            ->where('entrydate', '>=', $startDate)
            ->orderBy('entrydate', 'desc')
            ->orderBy('shour', 'desc')
            ->get();

        return response()->json(['data' => $data]);
    }

    // Fungsi Rumus Jarak (Haversine Formula)
    private function hitungJarak($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000; 
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;
        return $distance; 
    }
}