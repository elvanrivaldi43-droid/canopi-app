<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSkill extends Model
{
    protected $table = 'user_skill';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'rab_skill_id', 'sumber',
    ];

    protected static function booted(): void
    {
        static::creating(function (UserSkill $row) {
            $row->created_at ??= now();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
