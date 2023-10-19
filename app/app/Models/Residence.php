<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Residence extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $hidden = [''];

    protected $fillable = ['city', 'zip', 'streetname', 'country', 'number', 'created_at', 'updated_at'];

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
