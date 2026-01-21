<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    // --- HELPER: Ubah Menit ke Format "08:00" ---
    private function minutesToTime($totalMinutes) {
        if ($totalMinutes === null) return "00:00";
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        return sprintf("%02d:%02d", $hours, $minutes);
    }

    // --- HELPER: Ubah "08:00" ke Total Menit (480) ---
    private function timeToMinutes($timeStr) {
        if (!$timeStr) return 0;
        $parts = explode(':', $timeStr);
        return ((int)$parts[0] * 60) + (int)$parts[1];
    }

    public function index()
    {
        // 1. AMBIL LOKASI
        $loc = DB::table('flexnotesetting')
                     ->where('settingtypecode', 'customerinfo2')
                     ->first();

        // 2. AMBIL JAM KERJA
        $work = DB::table('masterworkinghour')
                    ->where('workinghourcode', 'HL')
                    ->first();

        // Default Values
        $lat = $loc->datadecimal1 ?? -7.2575;
        $lng = $loc->datadecimal2 ?? 112.7521;
        $rad = $loc->datadecimal3 ?? 0.5;

        // Ambil 4 Waktu dari Database (Dalam Menit -> Ubah ke Jam)
        $workStart = $this->minutesToTime($work->starthour ?? 480);        // Jam Masuk
        $workEnd   = $this->minutesToTime($work->endhour ?? 1020);         // Jam Pulang
        $scanStart = $this->minutesToTime($work->startscaninhour ?? 420);  // Awal Scan
        $scanEnd   = $this->minutesToTime($work->endscaninhour ?? 495);    // Batas Akhir (Telat)

        return response()->json([
            'office_lat' => (string)$lat,
            'office_lng' => (string)$lng,
            'radius_km'  => (string)$rad,
            'work_start' => $workStart,
            'work_end'   => $workEnd,
            'scan_start' => $scanStart,
            'scan_end'   => $scanEnd,
        ]);
    }

    public function update(Request $request)
    {
        Log::info('Settings Update:', $request->all());

        // 1. Update Lokasi
        if ($request->has('office_lat')) {
            DB::table('flexnotesetting')
                ->where('settingtypecode', 'customerinfo2')
                ->update([
                    'datadecimal1' => $request->office_lat,
                    'datadecimal2' => $request->office_lng,
                    'datadecimal3' => $request->radius_km,
                ]);
        }

        // 2. Update Jam Operasional (4 Input Manual)
        if ($request->has('work_start')) {
            // Konversi semua ke menit
            $startMinutes = $this->timeToMinutes($request->work_start);
            $endMinutes   = $this->timeToMinutes($request->work_end);
            $scanStartMin = $this->timeToMinutes($request->scan_start);
            $scanEndMin   = $this->timeToMinutes($request->scan_end);

            DB::table('masterworkinghour')
                ->where('workinghourcode', 'HL')
                ->update([
                    'starthour'       => $startMinutes, // Jam Masuk
                    'endhour'         => $endMinutes,   // Jam Pulang
                    'startscaninhour' => $scanStartMin, // Mulai Boleh Scan
                    'endscaninhour'   => $scanEndMin,   // Batas Scan (Lewat ini Telat)
                    'modifydate'      => now()
                ]);
        }

        return response()->json(['message' => 'Pengaturan Berhasil Disimpan!']);
    }
}