<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $hidden = ['organisation_id'];

    protected $fillable = ['name', 'description', 'organisation_id'];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function children(): BelongsToMany
{
    return $this->belongsToMany(Child::class, 'groups_has_children', 'group_id', 'child_id')
        ->withPivot('subscribed');
}

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'groups_has_children')
            ->withPivot('subscribed')
            ->wherePivot('subscribed', 'accepted');
    }

    public function waitlist(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'groups_has_children')
            ->withPivot('subscribed')
            ->wherePivot('subscribed', 'sent');
    }
}
