<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InitialDataSeeder extends Seeder
{
    public function run()
    {
        // ... (Bagian Area, Position, Staff TETAP SAMA seperti sebelumnya) ...
        // Copy paste bagian area, position, staff dari jawaban sebelumnya
        // Disini saya fokuskan update bagian FLEXNOTESETTING

        // 1. MASTER AREA
        DB::table('masterarea')->insert([
            ['areaid' => 1, 'areacode' => 'SBY', 'description' => 'Kantor Surabaya', 'latitude1' => -7.2642671, 'longitude1' => 112.7983622, 'created_at' => now(), 'updated_at' => now()],
            ['areaid' => 2, 'areacode' => 'TP', 'description' => 'Tunjungan Plaza', 'latitude1' => -7.2623869, 'longitude1' => 112.7390060, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. MASTER STAFF POSITION
        DB::table('masterstaffposition')->insert([
            ['staffpositionid' => 1, 'staffpositioncode' => 'MNG', 'description' => 'Manager', 'blocked' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['staffpositionid' => 2, 'staffpositioncode' => 'SPV', 'description' => 'Supervisor', 'blocked' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['staffpositionid' => 3, 'staffpositioncode' => 'STF', 'description' => 'Staff', 'blocked' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. MASTER STAFF
        DB::table('masterstaff')->insert([
            ['staffid' => 2, 'staffcode' => '5001', 'name' => 'Dhenny Hariyanto', 'password' => '123456', 'staffcategoryid' => 1, 'staffpositionid' => 2, 'freeinteger1' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['staffid' => 3, 'staffcode' => '6001', 'name' => 'Efendi', 'password' => '654321', 'staffcategoryid' => 1, 'staffpositionid' => 3, 'freeinteger1' => 1, 'created_at' => now(), 'updated_at' => now()]
        ]);

        // 4. SETTING (UPDATED SESUAI SCREENSHOT)
        DB::table('flexnotesetting')->insert([
            'settingtypecode' => 'CUSTOMERINFO2', // Huruf Besar sesuai screenshot
            'datadecimal1' => -7.26426,           // Latitude Pusat (Sesuai screenshot)
            'datadecimal2' => 112.79837,          // Longitude Pusat (Sesuai screenshot)
            'datadecimal3' => 7.5,                // Radius 7.5 meter
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. JAM KERJA
        DB::table('masterworkinghour')->insert([
            'workinghourcode' => 'HL', 'description' => 'Harian Lepas', 'starthour' => 480, 'endhour' => 1020, 'startscaninhour' => 420, 'endscaninhour' => 480, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}