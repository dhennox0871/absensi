<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AreaController extends Controller
{
    public function index()
    {
        $data = DB::table('masterarea')
            ->select('areaid', 'areacode', 'description', 'latitude1', 'longitude1')
            ->orderBy('areaid', 'desc')
            ->get();
        return response()->json(['data' => $data]);
    }

    // FUNGSI DEBUG TOTAL UNTUK SAVE
    public function save(Request $request)
    {
        // 1. TANGKAP SEMUA INPUT MENTAH
        $allInput = $request->all();
        $rawContent = $request->getContent(); // Body mentah dari Flutter
        
        // 2. LOG DI FILE (Opsional, buat jaga-jaga)
        \Illuminate\Support\Facades\Log::info('Save Request:', ['input' => $allInput]);

        $id = $request->input('areaid'); 
        $code = $request->input('areacode');
        $desc = $request->input('description');
        
        // Cek apakah ID terbaca?
        if ($id === null && $request->has('areaid')) {
             // Kasus khusus: kadang areaid dikirim string "null" atau kosong
             $val = $request->input('areaid');
             if ($val === "null" || $val === "") $id = null;
        }

        // --- SIMULASI SAJA DULU (BIAR KITA LIHAT DATA) ---
        // Kita return langsung apa yang diterima server
        // HAPUS BAGIAN INI NANTI KALAU SUDAH BENAR
        /*
        return response()->json([
            'status' => 'debug',
            'message' => 'Ini data yang diterima Server',
            'id_detected' => $id,
            'input_all' => $allInput,
            'is_json' => $request->isJson(),
            'method' => $request->method()
        ]);
        */
        // ------------------------------------------------

        // LOGIC SAVE ASLI
        $latRaw = $request->input('latitude1') ?? $request->input('latitude');
        $lngRaw = $request->input('longitude1') ?? $request->input('longitude');

        $latClean = str_replace(',', '.', (string)$latRaw);
        $lngClean = str_replace(',', '.', (string)$lngRaw);
        $latFinal = ($latClean === '' || strtolower($latClean) === 'null') ? null : $latClean;
        $lngFinal = ($lngClean === '' || strtolower($lngClean) === 'null') ? null : $lngClean;

        $dataToSave = [
            'areacode'    => $code,
            'description' => $desc,
            'latitude1'   => $latFinal,
            'longitude1'  => $lngFinal,
            'modifydate'  => Carbon::now(),
            'modifyby'    => 'ADMIN'
        ];

        try {
            $affected = 0;
            $msg = "";

            if ($id) {
                // UPDATE
                $exists = DB::table('masterarea')->where('areaid', $id)->exists();
                if ($exists) {
                    $affected = DB::table('masterarea')->where('areaid', $id)->update($dataToSave);
                    // PENTING: Cek apakah affected 0?
                    if ($affected == 0) {
                        return response()->json([
                            'status' => 'warning',
                            'message' => 'Update sukses (200), tapi data DB tidak berubah. Data dikirim mungkin sama dengan data lama.',
                            'sent_data' => $dataToSave
                        ]);
                    }
                    $msg = "Update Berhasil (ID: $id)";
                } else {
                    return response()->json(['status'=>'error', 'message'=>"ID $id Tidak Ditemukan di DB"], 404);
                }
            } else {
                // INSERT
                $dataToSave['blocked'] = 0;
                $dataToSave['createby'] = 'ADMIN';
                $dataToSave['createdate'] = Carbon::now();
                
                $newId = DB::table('masterarea')->insertGetId($dataToSave);
                $affected = 1;
                $msg = "Simpan Baru Berhasil (ID: $newId)";
            }

            return response()->json([
                'status' => 'success',
                'message' => $msg,
                'rows_affected' => $affected
            ]);

        } catch (\Exception $e) {
            return response()->json(['status'=>'error', 'message'=>'DB Error: ' . $e->getMessage()], 500);
        }
    }

    // FUNGSI DEBUG TOTAL UNTUK DELETE
    public function delete(Request $request)
    {
        $id = $request->input('areaid');
        
        // DEBUG: Cek apa yang diterima
        if (!$id) {
            return response()->json([
                'status' => 'error',
                'message' => 'ID Area Kosong/Tidak Terbaca',
                'received_input' => $request->all()
            ], 400);
        }

        try {
            $deleted = DB::table('masterarea')->where('areaid', $id)->delete();
            if ($deleted) {
                return response()->json(['status'=>'success', 'message'=>'Data Terhapus']);
            } else {
                return response()->json(['status'=>'error', 'message'=>"Gagal Hapus. ID $id tidak ada di DB."], 404);
            }
        } catch (\Exception $e) {
             return response()->json(['status'=>'error', 'message'=>$e->getMessage()], 500);
        }
    }
}