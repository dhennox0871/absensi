<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'absentrans'; 
    protected $primaryKey = 'absentransid';
    public $timestamps = false;

    // Tambahkan kolom-kolom baru ke sini
    protected $fillable = [
        'staffid',
        'entrydate',
        'shour',
        'sminute',
        'ssec',
        'status',
        'shift',
        'freedescription1',
        'freedescription2',
        'freedescription3',
        
        // --- TAMBAHAN BARU ---
        'absentransentryno', // UUID
        'createby',
        'createdate',
        'modifyby',
        'modifydate',
    ];
}