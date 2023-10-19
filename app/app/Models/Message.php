<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'message',
        'group_id',
        'child_id',
        'created_at',
        'updated_at'
    ];

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
