<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'logo',
        'work_start_time',
        'work_end_time',
        'late_tolerance_minutes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'late_tolerance_minutes' => 'integer',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'logo_url',
    ];

    /**
     * Get the publicly accessible URL of the company logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return Storage::disk('public')->url($this->logo);
    }

    /**
     * Get or create the singleton instance of company profile.
     */
    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'SIMPEG Presensi',
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
                'late_tolerance_minutes' => 0,
            ]
        );
    }
}
