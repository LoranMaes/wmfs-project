<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Child extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $hidden = ['pivot', 'user_id'];

    protected $fillable = [
        'first_name',
        'last_name',
        'image',
        'user_id'
    ];

    public function name(): Attribute
    {
        return new Attribute(
            get: function ($value) {
                $child = $this->getAttributes();
                return $child['first_name'] . ' ' . $child['last_name'];
            },
            set: function ($value) {
                return $value;
            },
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'notifications_has_children')
            ->withPivot('seen', 'filled_in');
    }

    public function seenNotifications()
    {
        return $this->belongsToMany(Notification::class, 'notifications_has_children')
            ->withPivot('seen', 'filled_in')
            ->wherePivot('seen', 1);
    }

    public function notSeenNotifications()
    {
        return $this->belongsToMany(Notification::class, 'notifications_has_children')
            ->withPivot('seen', 'filled_in')
            ->wherePivot('seen', 0);
    }

    public function todoNotifications()
    {
        return $this->belongsToMany(Notification::class, 'notifications_has_children')
            ->withPivot('seen', 'filled_in')
            ->wherePivot('filled_in', 0);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'groups_has_children', 'child_id', 'group_id')
            ->withPivot('subscribed');
    }
}
