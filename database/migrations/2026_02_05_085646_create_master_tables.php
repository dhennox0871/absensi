<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. TABEL MASTER AREA
        Schema::create('masterarea', function (Blueprint $table) {
            $table->id('areaid');
            $table->string('areacode')->nullable();
            $table->string('description'); 
            $table->decimal('latitude1', 10, 7)->nullable(); 
            $table->decimal('longitude1', 10, 7)->nullable(); 
            $table->timestamps();
        });

        // 2. TABEL MASTER STAFF POSITION
        Schema::create('masterstaffposition', function (Blueprint $table) {
            $table->id('staffpositionid'); 
            $table->string('staffpositioncode')->nullable(); 
            $table->string('description'); 
            $table->integer('blocked')->default(0); 
            $table->timestamps();
        });

        // 3. TABEL SETTING (KOREKSI SESUAI SCREENSHOT)
        Schema::create('flexnotesetting', function (Blueprint $table) {
            // PK adalah flexnotesettingid
            $table->id('flexnotesettingid'); 
            
            $table->string('settingtypecode')->index(); // 'CUSTOMERINFO2'
            
            // Tambahan datadecimal1 & 2 untuk koordinat (sesuai screenshot)
            $table->decimal('datadecimal1', 11, 7)->nullable(); // Latitude (-7.26426)
            $table->decimal('datadecimal2', 11, 7)->nullable(); // Longitude (112.79837)
            $table->decimal('datadecimal3', 11, 5)->nullable(); // Radius (7.50000)
            
            // Opsional: Tambahkan dataint/datachar jika ada di screenshot lain, 
            // tapi yang krusial untuk logic absen adalah decimal di atas.
            
            $table->timestamps();
        });

        // 4. TABEL JAM KERJA
        Schema::create('masterworkinghour', function (Blueprint $table) {
            $table->id('workinghourid');
            $table->string('workinghourcode')->index(); 
            $table->string('description')->nullable();
            $table->integer('starthour')->default(480); 
            $table->integer('endhour')->default(1020);  
            $table->integer('startscaninhour')->nullable();
            $table->integer('endscaninhour')->nullable();
            $table->timestamps();
        });

        // 5. TABEL MASTER STAFF
        Schema::create('masterstaff', function (Blueprint $table) {
            $table->id('staffid'); 
            $table->string('staffcode')->unique(); 
            $table->string('name');
            $table->string('password'); 
            
            // Relasi Jabatan & Area
            $table->integer('staffpositionid')->nullable(); 
            $table->integer('freeinteger1')->nullable(); 
            
            $table->integer('staffcategoryid')->default(0); 
            $table->string('photo_url')->nullable(); 
            $table->string('email')->nullable()->unique();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('masterstaff');
        Schema::dropIfExists('masterworkinghour');
        Schema::dropIfExists('flexnotesetting');
        Schema::dropIfExists('masterstaffposition');
        Schema::dropIfExists('masterarea');
    }
};