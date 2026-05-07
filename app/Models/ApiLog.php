<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $fillable = [
        'service',
        'method',
        'url',
        'request_headers',
        'request_body',
        'response_code',
        'response_headers',
        'response_body',
        'duration_ms',
        'is_success',
        'error_message',
        'context',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_headers' => 'array',
        'is_success' => 'boolean',
        'context' => 'array',
        'duration_ms' => 'integer',
    ];

    public function scopeForService($query, string $service)
    {
        return $query->where('service', $service);
    }

    public function scopeFailed($query)
    {
        return $query->where('is_success', false);
    }
}
