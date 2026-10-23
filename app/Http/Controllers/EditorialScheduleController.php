<?php

namespace App\Http\Controllers;

use App\Models\AiIntegration;
use App\Models\Site;
use App\Models\XConnection;
use App\Services\EditorialScheduleRunner;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EditorialScheduleController extends Controller
{
    public function runNow(Site $site, EditorialScheduleRunner $runner): RedirectResponse
    {
        if (! $site->is_active) {
            return back()->with('workflow_error', 'Attiva il sito prima di avviare il controllo.');
        }

        if (! $runner->runNow($site)) {
            return back()->with('workflow_error', 'Un controllo è già in corso. Attendi il suo completamento.');
        }

        $schedule = $site->editorialSchedule()->firstOrFail();

        return back()->with($schedule->last_error ? 'workflow_error' : 'status', $schedule->last_error
            ?: "Controllo completato: {$schedule->last_imported_count} nuove uscite, {$schedule->last_proposal_count} post proposti. L’AI ha rivalutato anche i capitoli già noti.");
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $values = $request->validate([
            'enabled' => ['required', 'boolean'],
            'x_autopublish_enabled' => ['sometimes', 'boolean'],
            'interval_hours' => ['required', 'integer', Rule::in([6, 12, 24])],
            'start_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone', 'max:64'],
        ]);
        $values['x_autopublish_enabled'] = (bool) ($values['x_autopublish_enabled'] ?? false);

        if ($values['enabled'] && ! $site->is_active) {
            throw ValidationException::withMessages(['enabled' => 'Attiva prima il sito per avviare il piano automatico.']);
        }

        if ($values['enabled'] && ! $site->editorialSources()->where('is_active', true)->where('verification_status', 'verified')->exists()) {
            throw ValidationException::withMessages(['enabled' => 'Verifica e attiva almeno una fonte prima di avviare il piano automatico.']);
        }

        if ($values['enabled'] && ! AiIntegration::query()->where('active_marker', 'active')->where('status', 'verified')->exists()) {
            throw ValidationException::withMessages(['enabled' => 'Verifica e attiva un servizio AI prima di avviare il piano automatico.']);
        }

        if ($values['x_autopublish_enabled'] && (! $values['enabled'] || ! app()->environment('production')
            || ! config('services.x.auto_publish_allowed')
            || ! XConnection::query()->where('status', 'active')->exists()
            || ! $site->editorialSources()->whereNotNull('promotion_authorized_at')->where('is_active', true)
                ->where('verification_status', 'verified')->exists())) {
            throw ValidationException::withMessages(['x_autopublish_enabled' => 'Per pubblicare automaticamente servono: ambiente di produzione, abilitazione operativa, piano attivo, account X collegato e fonte verificata autorizzata alla promozione.']);
        }

        $schedule = $site->editorialSchedule()->firstOrNew();
        $wasEnabled = (bool) $schedule->enabled;
        $schedule->fill($values);
        if ($schedule->enabled && ! $wasEnabled) {
            $schedule->consecutive_failures = 0;
            $schedule->last_error = null;
        }
        $schedule->next_run_at = ! $schedule->enabled
            ? null
            : ($wasEnabled ? $schedule->nextAfter(CarbonImmutable::now('UTC')) : now());
        $schedule->save();

        return redirect()->route('sites.editorial-plan.show', $site)->with('status', $schedule->enabled
            ? 'Piano automatico attivato. Il primo controllo partirà al prossimo passaggio dello Scheduler.'
            : 'Piano automatico disattivato. Le proposte esistenti restano disponibili.');
    }
}
