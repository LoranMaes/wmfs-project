<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'message',
        'deadline',
        'duration',
        'image',
        'type',
        'event',
        'obligatory',
        'group_id'
    ];

    // protected $hidden = ['group_id'];

    public function tags()
    {
        return $this->hasMany(Tag::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function children()
    {
        return $this->belongsToMany(Child::class, 'notifications_has_children')
            ->withPivot('seen', 'filled_in');
    }

    public function todoNot()
    {
        return $this->belongsToMany(Child::class, 'notifications_has_children')
            ->withPivot('seen', 'filled_in')
            ->wherePivot('filled_in', 0);
    }

    public function unseen(){
        return $this->belongsToMany(Child::class, 'notifications_has_children')
        ->withPivot('seen', 'filled_in')
        ->wherePivot('seen', 0);
    }
}
