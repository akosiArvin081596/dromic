<?php

namespace App\Http\Controllers;

use App\Models\LguSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LguSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $settings = LguSetting::query()
            ->where('city_municipality_id', $user->city_municipality_id)
            ->first();

        return Inertia::render('Settings/Lgu', [
            'settings' => $settings ? [
                ...$settings->toArray(),
                'logo_url' => $settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
                'ldrrmc_logo_url' => $settings->ldrrmc_logo_path ? Storage::disk('public')->url($settings->ldrrmc_logo_path) : null,
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'signatory_1_name' => ['nullable', 'string', 'max:255'],
            'signatory_1_designation' => ['nullable', 'string', 'max:255'],
            'signatory_2_name' => ['nullable', 'string', 'max:255'],
            'signatory_2_designation' => ['nullable', 'string', 'max:255'],
            'signatory_3_name' => ['nullable', 'string', 'max:255'],
            'signatory_3_designation' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'ldrrmc_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_ldrrmc_logo' => ['nullable', 'boolean'],
        ]);

        $settings = LguSetting::query()->firstOrCreate(
            ['city_municipality_id' => $user->city_municipality_id],
        );

        $settings->update([
            'signatory_1_name' => $validated['signatory_1_name'],
            'signatory_1_designation' => $validated['signatory_1_designation'],
            'signatory_2_name' => $validated['signatory_2_name'],
            'signatory_2_designation' => $validated['signatory_2_designation'],
            'signatory_3_name' => $validated['signatory_3_name'],
            'signatory_3_designation' => $validated['signatory_3_designation'],
        ]);

        if ($request->boolean('remove_logo') && $settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->update(['logo_path' => null]);
        } elseif ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $path = $request->file('logo')->store('lgu-logos', 'public');
            $settings->update(['logo_path' => $path]);
        }

        if ($request->boolean('remove_ldrrmc_logo') && $settings->ldrrmc_logo_path) {
            Storage::disk('public')->delete($settings->ldrrmc_logo_path);
            $settings->update(['ldrrmc_logo_path' => null]);
        } elseif ($request->hasFile('ldrrmc_logo')) {
            if ($settings->ldrrmc_logo_path) {
                Storage::disk('public')->delete($settings->ldrrmc_logo_path);
            }
            $path = $request->file('ldrrmc_logo')->store('ldrrmc-logos', 'public');
            $settings->update(['ldrrmc_logo_path' => $path]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'LGU settings updated successfully.']);

        return redirect()->route('settings.lgu');
    }
}
