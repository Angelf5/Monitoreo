<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'identifier_hash',
        'ip_address',
        'status',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Hash usado para correlacionar intentos de una misma cuenta sin guardar el email.
     */
    public static function hashIdentifier(?string $identifier): ?string
    {
        $identifier = trim((string) $identifier);

        return $identifier === '' ? null : hash('sha256', mb_strtolower($identifier));
    }
}
