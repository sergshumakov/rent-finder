<?php

namespace App\Models;

use App\Jobs\FindDuplicateJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flat extends Model
{
    use HasFactory;

    protected $casts = [
        'photos' => 'array',
    ];

    protected static function booted()
    {
        static::created(function ($flat) {
            FindDuplicateJob::dispatch($flat);
            \Log::debug('Created a new flat – ' . $flat->id);
        });
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
