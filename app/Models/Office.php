<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'abbreviation',
        'is_active',
        'sso_id',
        'sso_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sso_id' => 'integer',
            'sso_synced_at' => 'datetime',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
