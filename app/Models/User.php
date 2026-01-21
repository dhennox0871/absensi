<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // 1. Hubungkan ke nama tabel asli Anda
    protected $table = 'masterstaff';

    // 2. Tentukan kunci utama (Primary Key) tabel Anda
    protected $primaryKey = 'staffid';

    // 3. Karena staffid mungkin bukan angka otomatis (Auto Increment), matikan ini
    public $incrementing = true;

    // 4. Jika staffid Anda adalah string (misal: S001), tambahkan ini
    protected $keyType = 'int';

    // 5. Matikan fitur created_at & updated_at karena tabel lama tidak mempunyainya
    public $timestamps = false;

    protected $fillable = [
        'staffid', 
        'name', 
        'staffcode',
        'password', 
        'staffpositionid'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */

     // Tambahkan ini di dalam class User
    public function position()
    {
        // Relasi: staffpositionid di masterstaff merujuk ke staffpositionid di masterstaffposition
        return $this->belongsTo(StaffPosition::class, 'staffpositionid', 'staffpositionid');
    }
    
}


