<?php

namespace App\Models;

use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $date
 * @property string|null $check_in
 * @property float|null $check_in_latitude
 * @property float|null $check_in_longitude
 * @property string|null $check_out
 * @property float|null $check_out_latitude
 * @property float|null $check_out_longitude
 * @property string $status
 * @property string|null $notes
 * @property string|null $check_in_map_url
 * @property string|null $check_out_map_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'employee_id',
    'date',
    'check_in',
    'check_in_latitude',
    'check_in_longitude',
    'check_out',
    'check_out_latitude',
    'check_out_longitude',
    'status',
    'notes',
])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = [
        'check_in_map_url',
        'check_out_map_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_out_latitude' => 'float',
            'check_out_longitude' => 'float',
        ];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function checkInMapUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => ($this->check_in_latitude !== null && $this->check_in_longitude !== null)
                ? "https://www.google.com/maps?q={$this->check_in_latitude},{$this->check_in_longitude}"
                : null
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function checkOutMapUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => ($this->check_out_latitude !== null && $this->check_out_longitude !== null)
                ? "https://www.google.com/maps?q={$this->check_out_latitude},{$this->check_out_longitude}"
                : null
        );
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
