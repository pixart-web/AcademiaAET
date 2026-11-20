<?php

namespace App\Http\Controllers;

use App\Models\ScreeningItem;
use App\Models\ScreeningSession;
use App\Services\AuditLogger;
use App\Services\Screening\ScreeningAge;
use App\Services\Screening\ScreeningEngine;
use App\Services\Screening\ScreeningReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreeningController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ScreeningSession::class);
        $user = $request->user();

        $sessions = ScreeningSession::query()
            ->where('organization_id', $user->organization_id)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('conducted_by_user_id', $user->id))
            ->latest('id')
            ->limit(100)
            ->get(['id', 'child_name', 'institution_name', 'age_months', 'age_block', 'status', 'screened_on', 'result', 'report_guardian_sent_at', 'report_clinic_sent_at', 'report_error'])
            ->map(fn (ScreeningSession $s) => [
                'id' => $s->id,
                'child_name' => $s->child_name,
                'institution_name' => $s->institution_name,
                'age' => $s->ageLabel(),
                'age_block' => $s->age_block,
                'status' => $s->status,
                'screened_on' => $s->screened_on->toDateString(),
                'attention' => $s->result['attention'] ?? null,
                'total_percent' => $s->result['total']['percent'] ?? null,
                'guardian_sent' => $s->report_guardian_sent_at !== null,
                'clinic_sent' => $s->report_clinic_sent_at !== null,
                'report_error' => $s->report_error,
            ]);

        return Inertia::render('Screening/Index', ['sessions' => $sessions]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ScreeningSession::class);

        $institutions = ScreeningSession::query()
            ->where('organization_id', $request->user()->organization_id)
            ->distinct()->orderBy('institution_name')->pluck('institution_name');

        return Inertia::render('Screening/Create', ['institutions' => $institutions]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ScreeningSession::class);

        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'child_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date_format:d/m/Y'],
            'gender' => ['required', 'in:feminino,masculino,outro'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_email' => ['required', 'email:rfc', 'max:255'],
            'guardian_phone' => ['required', 'regex:/^[0-9+()\s-]{9,20}$/'],
            'consent' => ['accepted'],
        ], [
            'birth_date.date_format' => 'Use o formato DD/MM/AAAA.',
            'consent.accepted' => 'É necessário confirmar a autorização do encarregado de educação.',
            'guardian_phone.regex' => 'Indique um número de telefone válido.',
        ]);

        $tz = $request->user()->organization->timezone;
        $today = Carbon::now($tz)->startOfDay();
        $birth = Carbon::createFromFormat('d/m/Y', $data['birth_date'], $tz)->startOfDay();

        if ($birth->isFuture()) {
            throw ValidationException::withMessages(['birth_date' => 'A data de nascimento não pode ser no futuro.']);
        }

        $ageMonths = ScreeningAge::months($birth, $today);
        $block = ScreeningAge::block($ageMonths);

        if ($block === null) {
            throw ValidationException::withMessages([
                'birth_date' => 'Idade calculada: '.ScreeningAge::label($ageMonths).'. Este rastreio aplica-se dos 3 anos e 0 meses aos 7 anos e 11 meses.',
            ]);
        }

        $orgId = $request->user()->organization_id;

        if (! ScreeningItem::query()->where('organization_id', $orgId)->where('age_block', $block)->exists()) {
            throw ValidationException::withMessages([
                'birth_date' => "Ainda não há itens carregados para o bloco de {$block} anos. Importe o banco de itens (php artisan screening:import).",
            ]);
        }

        $session = ScreeningSession::create([
            'organization_id' => $orgId,
            'conducted_by_user_id' => $request->user()->id,
            'institution_name' => $data['institution_name'],
            'child_name' => $data['child_name'],
            'birth_date' => $birth,
            'gender' => $data['gender'],
            'guardian_name' => $data['guardian_name'],
            'guardian_email' => $data['guardian_email'],
            'guardian_phone' => $data['guardian_phone'],
            'screened_on' => $today,
            'age_months' => $ageMonths,
            'age_block' => $block,
            'consent_confirmed_at' => now(),
        ]);

        AuditLogger::log('screening.started', $session, ['age_block' => $block]);

        return redirect()->route('screenings.run', $session);
    }

    public function run(Request $request, ScreeningSession $screening, ScreeningEngine $engine): Response
    {
        $this->authorize('view', $screening);

        $item = $screening->isCompleted() ? null : $engine->next($screening);

        return Inertia::render('Screening/Run', [
            'session' => [
                'id' => $screening->id,
                'child_first_name' => explode(' ', trim($screening->child_name))[0],
                'age' => $screening->ageLabel(),
                'age_block' => $screening->age_block,
                'status' => $screening->status,
            ],
            'item' => $item ? $this->presentItem($screening, $item, $engine) : null,
            'progress' => $engine->progress($screening),
            'result' => $screening->isCompleted() ? $this->presentResult($screening) : null,
        ]);
    }

    public function answer(Request $request, ScreeningSession $screening, ScreeningEngine $engine): RedirectResponse
    {
        $this->authorize('update', $screening);

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'outcome' => ['nullable', 'string'],
            'produced_sound' => ['nullable', 'string', 'max:32'],
            'option_index' => ['nullable', 'integer', 'min:0', 'max:3'],
        ]);

        $item = ScreeningItem::query()
            ->where('organization_id', $screening->organization_id)
            ->findOrFail($data['item_id']);

        $engine->record($screening, $item, $data);

        return redirect()->route('screenings.run', $screening);
    }

    public function report(Request $request, ScreeningSession $screening, ScreeningReportService $reports): StreamedResponse
    {
        $this->authorize('view', $screening);
        abort_unless($screening->isCompleted(), 404);

        AuditLogger::log('screening.report_downloaded', $screening);

        return Storage::disk('local')->exists((string) $screening->report_path)
            ? Storage::disk('local')->download($screening->report_path, 'relatorio-rastreio.pdf')
            : response()->streamDownload(fn () => print $reports->pdf($screening), 'relatorio-rastreio.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function resend(Request $request, ScreeningSession $screening, ScreeningReportService $reports): RedirectResponse
    {
        $this->authorize('update', $screening);
        abort_unless($screening->isCompleted(), 404);

        $reports->resend($screening);
        AuditLogger::log('screening.report_resent', $screening);

        return back()->with('status', 'Relatório reenviado.');
    }

    private function presentItem(ScreeningSession $session, ScreeningItem $item, ScreeningEngine $engine): array
    {
        $payload = [
            'id' => $item->id,
            'kind' => $item->kind->value,
            'prompt' => $item->prompt,
            'image_url' => $item->image ? URL::temporarySignedRoute('media.show', now()->addMinutes(30), ['media' => $item->image->id]) : null,
        ];

        if ($item->kind->isAutoValidated()) {
            // Never the `correct` flag — the answer is validated server-side.
            $payload['options'] = collect($engine->presentedOptions($session, $item))
                ->map(fn (array $o, int $i) => ['index' => $i, 'label' => $o['label']])
                ->all();
        } else {
            // Shown only in the adult's "Avaliador" panel.
            $payload['evaluator'] = [
                'target_word' => $item->target_word,
                'phoneme' => $item->phonemeNorm()['label'] ?? null,
                'expected_answer' => $item->expected_answer,
            ];
        }

        return $payload;
    }

    private function presentResult(ScreeningSession $session): array
    {
        return [
            'areas' => $session->result['areas'],
            'total' => $session->result['total'],
            'findings' => $session->result['findings'],
            'attention' => $session->result['attention'],
            'conclusion' => $session->result['conclusion'],
            'guardian_sent' => $session->report_guardian_sent_at !== null,
            'clinic_sent' => $session->report_clinic_sent_at !== null,
            'report_error' => $session->report_error,
        ];
    }
}
