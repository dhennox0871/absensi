<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('absentrans', function (Blueprint $table) {
            // 1. Primary Key Auto Increment (Big Integer)
            $table->id('absentransid'); 

            // 2. Entry No (UUID/Nomor Transaksi) - Di-index biar pencarian cepat
            $table->string('absentransentryno')->index(); 
            
            $table->unsignedBigInteger('staffid')->index(); // Index staffid juga biar query history cepat
            $table->date('entrydate')->index(); // Index tanggal biar filter tanggal cepat
            
            $table->integer('shour');   
            $table->integer('sminute'); 
            $table->integer('ssec')->default(0); 
            
            $table->string('status', 5)->default('F'); 
            $table->integer('shift')->default(1); 
            
            $table->string('freedescription1')->nullable(); // Foto
            $table->string('freedescription2')->nullable(); // Longitude
            $table->string('freedescription3')->nullable(); // Latitude
            
            // Audit Trail
            $table->string('createby')->nullable();
            $table->dateTime('createdate')->nullable();
            $table->string('modifyby')->nullable();
            $table->dateTime('modifydate')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('absentrans');
    }
};