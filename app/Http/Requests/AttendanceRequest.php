<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $attendance = $this->route('attendance');
        $attendanceId = $attendance instanceof Attendance
            ? $attendance->id
            : ($attendance ?? $this->input('id') ?? $this->input('attendance_id'));
        $employeeId = $this->input('employee_id');

        return [
            'id' => ['nullable', 'integer'],
            'attendance_id' => ['nullable', 'integer'],
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => [
                'required',
                'date',
                Rule::unique('attendances', 'date')
                    ->where(fn ($query) => $query->where('employee_id', $employeeId))
                    ->ignore($attendanceId),
            ],
            'check_in' => ['nullable', 'date_format:H:i,H:i:s'],
            'check_in_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'check_in_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'check_out' => ['nullable', 'date_format:H:i,H:i:s'],
            'check_out_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'check_out_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', 'in:hadir,izin,terlambat,sakit,alpa,dinas_pagi,dinas_sore,cuti'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.unique' => 'Data presensi untuk pegawai ini pada tanggal tersebut sudah ada.',
            'employee_id.required' => 'Pegawai wajib dipilih.',
            'employee_id.exists' => 'Data pegawai tidak ditemukan.',
            'date.required' => 'Tanggal presensi wajib diisi.',
            'status.required' => 'Status kehadiran wajib dipilih.',
            'status.in' => 'Status kehadiran tidak valid.',
        ];
    }
}
