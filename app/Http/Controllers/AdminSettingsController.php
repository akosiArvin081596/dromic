<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function edit(): Response
    {
        $dromicLogoPath = Setting::getValue('dromic_logo_path');

        return Inertia::render('Settings/Admin', [
            'dromicLogoUrl' => $dromicLogoPath ? Storage::disk('public')->url($dromicLogoPath) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'dromic_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_dromic_logo' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_dromic_logo')) {
            $oldPath = Setting::getValue('dromic_logo_path');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
            Setting::setValue('dromic_logo_path', null);
        } elseif ($request->hasFile('dromic_logo')) {
            $oldPath = Setting::getValue('dromic_logo_path');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('dromic_logo')->store('dromic', 'public');
            Setting::setValue('dromic_logo_path', $path);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Settings updated successfully.']);

        return redirect()->route('settings.admin');
    }
}
