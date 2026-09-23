<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyProfileController extends Controller
{
    /**
     * Display the company profile edit form.
     */
    public function edit(): Response
    {
        $company = CompanyProfile::current();

        return Inertia::render('company-profile/Edit', [
            'company' => $company,
        ]);
    }

    /**
     * Update the company profile.
     */
    public function update(Request $request, ImageService $imageService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $company = CompanyProfile::current();

        $logoPath = $company->logo;

        if ($request->boolean('remove_logo')) {
            $imageService->deleteAvatar($company->logo);
            $logoPath = null;
        } elseif ($request->hasFile('logo')) {
            $imageService->deleteAvatar($company->logo);
            $logoPath = $imageService->convertToWebp($request->file('logo'), 'company');
        }

        $company->update([
            'name' => $validated['name'],
            'logo' => $logoPath,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Profil perusahaan berhasil diperbarui.',
        ]);

        return back();
    }
}
