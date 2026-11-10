<?php

namespace App\Http\Controllers;

use App\Models\InstallationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstallationSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $values = $request->validate(['app_name' => ['required', 'string', 'max:80']], [
            'app_name.required' => 'Inserisci il nome dell’applicazione.',
            'app_name.string' => 'Il nome dell’applicazione deve essere un testo.',
            'app_name.max' => 'Il nome dell’applicazione può contenere al massimo :max caratteri.',
        ]);

        InstallationSetting::query()->upsert([
            ['key' => InstallationSetting::APP_NAME, 'value' => $values['app_name']],
        ], ['key'], ['value']);

        return redirect()->route('settings.index')->with('status', 'Nome dell’applicazione aggiornato.');
    }
}
