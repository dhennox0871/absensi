<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffPosition extends Model
{
    use HasFactory;

    // Hubungkan ke nama tabel jabatan sesuai gambar Anda
    protected $table = 'masterstaffposition';

    // Set primary key sesuai gambar
    protected $primaryKey = 'staffpositionid';

    // Karena tabel existing biasanya tidak pakai timestamps otomatis Laravel
    public $timestamps = false;

    // Izinkan semua kolom diisi
    protected $guarded = [];

    protected $fillable = [
        'staffpositioncode',
        'description',
        'blocked'
    ];
}
