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
        // 1. Validasi Input GPS & Foto
        $request->validate([
            'image'     => 'required|image|max:5120', // Max 5MB
            'latitude'  => 'required',
            'longitude' => 'required',
        ]);

        try {
            $user = $request->user(); // Ambil user yang login
            
            // Ambil data detail staff
            $staff = DB::table('masterstaff')->where('staffid', $user->staffid)->first();
            if (!$staff) return response()->json(['message' => 'Data staff tidak ditemukan'], 404);

            // =================================================================
            // LOGIKA PENGECEKAN LOKASI (PENEMPATAN KERJA)
            // =================================================================

            // A. Ambil Radius Toleransi (Table: flexnotesetting, Code: customerinfo2)
            // Berdasarkan screenshot: datadecimal3 = 7.5 (Asumsi KM, jadi dikali 1000 = 7500 Meter)
            $setting = DB::table('flexnotesetting')
                         ->where('settingtypecode', 'customerinfo2') // <--- SUDAH DIPERBAIKI
                         ->first();
            
            // Jika setting tidak ditemukan, default radius 100 meter
            $maxRadiusMeter = $setting ? ((float)$setting->datadecimal3 ) : 5.0;

            // B. Cek Apakah Staff Punya Penempatan? (freeinteger1 tidak null/0)
            if (!empty($staff->freeinteger1) && $staff->freeinteger1 != 0) {
                
                // Ambil Data Master Area dari tabel masterarea
                $area = DB::table('masterarea')->where('areaid', $staff->freeinteger1)->first();
                
                // Pastikan Area Ditemukan & Punya Koordinat Valid
                if ($area && !empty($area->latitude1) && !empty($area->longitude1)) {
                    
                    // Koordinat Kantor/Area (Dari Database)
                    $targetLat = (float)$area->latitude1; 
                    $targetLng = (float)$area->longitude1;

                    // Koordinat Staff Saat Ini (Dari GPS HP)
                    $userLat = (float)$request->latitude;
                    $userLng = (float)$request->longitude;

                    // Hitung Jarak (Meter)
                    $jarak = $this->hitungJarak($userLat, $userLng, $targetLat, $targetLng);

                    // VALIDASI JARAK
                    // Jika jarak user > radius yang diizinkan => TOLAK
                    if ($jarak > $maxRadiusMeter) {
                        return response()->json([
                            'status' => 'error', 
                            'message' => "Gagal Absen! Posisi Anda diluar radius area penempatan.",
                            'detail' => "Lokasi: $area->description. Jarak Anda: " . round($jarak) . "m (Maks: " . round($maxRadiusMeter) . "m)"
                        ], 403);
                    }
                }
                // Jika Area ditemukan tapi koordinatnya NULL di database, sistem akan meloloskan (Bypass)
                // karena tidak bisa dihitung jaraknya.
            } 
            // NOTE: Jika freeinteger1 NULL, kode di atas dilewati (Bebas Absen Dimana Saja / Mobile)

            // =================================================================

            // 3. Cek Spam (Mencegah absen double dalam 1 menit)
            $now = Carbon::now('Asia/Jakarta');
            $today = $now->toDateString();
            
            $lastLog = Attendance::where('staffid', $staff->staffid)
                        ->where('entrydate', $today)
                        ->orderBy('createdate', 'desc')
                        ->first();

            if ($lastLog && $now->diffInMinutes(Carbon::parse($lastLog->createdate)) < 1) {
                return response()->json(['message' => 'Anda baru saja absen, mohon tunggu sebentar.'], 400);
            }

            // 4. Hitung Shift Otomatis (Masuk/Pulang/Istirahat)
            $nowMinutes = ($now->hour * 60) + $now->minute;
            
            // Ambil jam kerja (Default HL)
            $workHour = DB::table('masterworkinghour')->where('workinghourcode', 'HL')->first();
            $jamMasukDB = $workHour->starthour ?? 480; // 08:00
            $jamPulangDB = $workHour->endhour ?? 1020; // 17:00

            $shiftDetermined = 1; // Default Masuk
            
            // Logika Penentuan Status Absen
            if ($nowMinutes < ($jamMasukDB + 180)) {
                $shiftDetermined = 1; // Masuk (Pagi sampai jam 11)
            } elseif ($nowMinutes > ($jamPulangDB - 120)) {
                $shiftDetermined = 4; // Pulang (Sore)
            } else {
                // Di tengah hari (Istirahat)
                if ($lastLog) {
                    if ($lastLog->shift == 1) $shiftDetermined = 2; // Keluar Istirahat
                    elseif ($lastLog->shift == 2) $shiftDetermined = 3; // Masuk Istirahat
                    else $shiftDetermined = 2;
                } else {
                    $shiftDetermined = 1; // Telat banget masuk
                }
            }

            // 5. Upload Foto ke Server
            $filename = null;
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('public/attendance');
                $filename = basename($path);
            }

            // 6. Simpan Transaksi Absen ke DB
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

    // --- FUNCTION HISTORY DINAMIS ---
    public function history(Request $request)
    {
        $user = $request->user();
        
        // Ambil parameter 'type' dari URL (default 'week')
        $type = $request->query('type', 'week'); 

        $query = DB::table('absentrans')
                    ->where('staffid', $user->staffid);

        // FILTER BERDASARKAN TIPE
        if ($type == 'month') {
            // PROFILE PAGE: Data Bulan Ini (Tgl 1 s/d Sekarang)
            $startDate = Carbon::now()->startOfMonth()->toDateString();
            $query->where('entrydate', '>=', $startDate);
        } 
        elseif ($type == 'all') {
            // HISTORY PAGE: Semua Data (Dibatasi 1 tahun agar tidak berat)
            $startDate = Carbon::now()->subYear()->toDateString();
            $query->where('entrydate', '>=', $startDate);
        } 
        else {
            // HOME VIEW: Data 7 Hari Terakhir
            $startDate = Carbon::now()->subDays(7)->toDateString();
            $query->where('entrydate', '>=', $startDate);
        }

        $data = $query->orderBy('entrydate', 'desc')
                      ->orderBy('shour', 'desc')
                      ->get();

        return response()->json(['data' => $data]);
    }

    // --- RUMUS JARAK (HAVERSINE FORMULA) ---
    private function hitungJarak($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000; // Radius bumi dalam meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;
        return $distance; // Hasil dalam Meter
    }
}