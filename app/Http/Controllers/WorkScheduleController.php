<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkScheduleController extends Controller
{
    /**
     * Display the work hours setting page.
     */
    public function edit(): Response
    {
        $company = CompanyProfile::current();

        return Inertia::render('work-hours/Edit', [
            'schedule' => [
                'work_start_time' => $company->work_start_time ? substr($company->work_start_time, 0, 5) : '08:00',
                'work_end_time' => $company->work_end_time ? substr($company->work_end_time, 0, 5) : '17:00',
                'late_tolerance_minutes' => (int) ($company->late_tolerance_minutes ?? 0),
            ],
            'company' => [
                'name' => $company->name,
                'logo_url' => $company->logo_url,
            ],
        ]);
    }

    /**
     * Update the work hours configuration.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'work_start_time' => ['required', 'date_format:H:i'],
            'work_end_time' => ['required', 'date_format:H:i', 'after:work_start_time'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
        ], [
            'work_start_time.required' => 'Jam masuk kerja wajib diisi.',
            'work_start_time.date_format' => 'Format jam masuk harus sesuai format JJ:MM (contoh: 08:00).',
            'work_end_time.required' => 'Jam pulang kerja wajib diisi.',
            'work_end_time.date_format' => 'Format jam pulang harus sesuai format JJ:MM (contoh: 17:00).',
            'work_end_time.after' => 'Jam pulang kerja harus lebih besar dari jam masuk kerja.',
            'late_tolerance_minutes.integer' => 'Toleransi keterlambatan harus berupa angka bulat dalam menit.',
            'late_tolerance_minutes.min' => 'Toleransi keterlambatan minimal 0 menit.',
            'late_tolerance_minutes.max' => 'Toleransi keterlambatan maksimal 120 menit (2 jam).',
        ]);

        $company = CompanyProfile::current();

        $company->update([
            'work_start_time' => $validated['work_start_time'],
            'work_end_time' => $validated['work_end_time'],
            'late_tolerance_minutes' => (int) ($validated['late_tolerance_minutes'] ?? 0),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengaturan jam kerja berhasil diperbarui.',
        ]);

        return back();
    }
}
