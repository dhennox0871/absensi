<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validasi Input (Hapus validasi radius)
        $request->validate([
            'image'     => 'required|image|max:2048',
            'latitude'  => 'required',
            'longitude' => 'required',
        ]);

        $user = $request->user();
        $now = Carbon::now('Asia/Jakarta');
        $nowDate = $now->toDateString();
        $nowMinutes = ($now->hour * 60) + $now->minute; // Konversi jam sekarang ke menit total

        // 2. CEK SPAM (TOLAK JIKA KURANG DARI 5 MENIT DARI SCAN TERAKHIR)
        $lastLog = Attendance::where('staffid', $user->staffid)
                    ->where('entrydate', $nowDate)
                    ->orderBy('createdate', 'desc') // Ambil yang paling baru hari ini
                    ->first();

        if ($lastLog) {
            // Hitung selisih menit
            $lastTime = Carbon::parse($lastLog->createdate);
            $diff = $now->diffInMinutes($lastTime);

            if ($diff < 5) {
                return response()->json([
                    'message' => 'Anda baru saja melakukan scan. Mohon tunggu 5 menit lagi.'
                ], 400); // 400 Bad Request
            }
        }

        // 3. LOGIKA SHIFT OTOMATIS (1, 2, 3, 4)
        // Ambil Jam Kerja dari Database (HL)
        $workHour = DB::table('masterworkinghour')->where('workinghourcode', 'HL')->first();
        
        // Default values jika DB kosong
        $jamMasukDB = $workHour->starthour ?? 480;  // 08:00
        $jamPulangDB = $workHour->endhour ?? 1020;  // 17:00
        $batasTelat = $workHour->endscaninhour ?? 495; // Batas toleransi telat

        $shiftDetermined = 1; // Default Shift 1

        // A. Logika Berdasarkan Waktu
        // Jika scan dilakukan SEBELUM jam 11:00 siang (480 + 180 menit toleransi pagi) -> Anggap Shift 1 (Masuk)
        if ($nowMinutes < ($jamMasukDB + 180)) {
            $shiftDetermined = 1;
        }
        // Jika scan dilakukan SETELAH jam 15:00 sore (1020 - 120 menit) -> Anggap Shift 4 (Pulang)
        elseif ($nowMinutes > ($jamPulangDB - 120)) {
            $shiftDetermined = 4;
        }
        // Jika di tengah-tengah (Jam Istirahat), cek urutan terakhir
        else {
            if ($lastLog) {
                if ($lastLog->shift == 1) {
                    $shiftDetermined = 2; // Keluar Istirahat
                } elseif ($lastLog->shift == 2) {
                    $shiftDetermined = 3; // Masuk Istirahat
                } else {
                    $shiftDetermined = 2; // Default jika bingung
                }
            } else {
                // Belum pernah absen tapi sudah siang? Anggap masuk terlambat (Shift 1)
                $shiftDetermined = 1;
            }
        }

        // 4. LOGIKA STATUS (Hadir / Terlambat)
        // Kita gunakan kolom status 'F' (Full/Hadir) sebagai default.
        // Jika Shift 1 DAN melebihi batas scan -> Tetap simpan, tapi nanti di report dianggap telat.
        $statusAbsen = 'F'; // Default Hadir
        
        // Opsional: Jika ingin menandai status di database langsung
        // if ($shiftDetermined == 1 && $nowMinutes > $batasTelat) {
        //    $statusAbsen = 'L'; // Late / Terlambat (Jika sistem support kode 'L')
        // }

        // 5. SIMPAN FOTO
        $filename = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('public/attendance');
            $filename = basename($path);
        }

        try {
            // 6. INSERT DATABASE
            $attendance = Attendance::create([
                'staffid'           => $user->staffid,
                'entrydate'         => $nowDate,
                'shour'             => $now->hour,
                'sminute'           => $now->minute,
                'ssec'              => $now->second,
                'status'            => $statusAbsen,
                'shift'             => $shiftDetermined, // 1, 2, 3, atau 4

                // Simpan Foto & Lokasi
                'freedescription1'  => $filename,
                'freedescription2'  => $request->longitude,
                'freedescription3'  => $request->latitude,
                
                // UUID & Audit Trail
                'absentransentryno' => (string) Str::uuid(),
                'createby'          => (string) $user->staffid,
                'createdate'        => $now,
                'modifyby'          => (string) $user->staffid,
                'modifydate'        => $now,
            ]);

            return response()->json([
                'message' => 'Absensi Berhasil Disimpan',
                'shift_detect' => $shiftDetermined,
                'waktu' => $now->format('H:i')
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal Simpan Database',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // --- FUNGSI HISTORY (TETAP SAMA SEPERTI SEBELUMNYA) ---
    public function history(Request $request)
    {
        $user = $request->user();
        
        // Ambil NIK & Cari User
        $nik = $user->staffcode; 
        $staff_asli = DB::table('masterstaff')->where('staffcode', $nik)->first();

        if (!$staff_asli) {
            return response()->json(['message' => 'Staff tidak ditemukan', 'data' => []], 200);
        }

        $id_karyawan = $staff_asli->staffid;
        $seminggu_lalu = Carbon::now()->subDays(7)->startOfDay(); 

        $data = DB::table('absentrans')
                    ->where('staffid', $id_karyawan)
                    ->where('entrydate', '>=', $seminggu_lalu)
                    ->orderBy('entrydate', 'desc')
                    ->orderBy('shour', 'desc')
                    ->get();

        return response()->json([
            'message' => 'Sukses',
            'data' => $data
        ], 200);
    }
}