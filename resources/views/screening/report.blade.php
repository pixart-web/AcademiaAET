<!doctype html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #193b35; }
    h1 { font-size: 18px; margin: 0; color: #174e45; }
    h2 { font-size: 13px; margin: 18px 0 6px; color: #174e45; border-bottom: 1px solid #e5e5da; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 4px 6px; text-align: left; vertical-align: top; }
    th { background: #f2ebdd; font-size: 10px; }
    .head { border-bottom: 2px solid #174e45; padding-bottom: 8px; }
    .muted { color: #5a6b64; }
    .bar-bg { background: #e6efe6; height: 14px; width: 100%; }
    .bar { background: #174e45; height: 14px; }
    .flag { color: #a8321f; font-weight: bold; }
    .box { border: 1px solid #e5e5da; padding: 10px; margin-top: 8px; }
    .attention { background: #fbefd5; border-color: #8a5300; }
    .ok { background: #e3f1e5; border-color: #2c6e3f; }
</style>
</head>
<body>
<div class="head">
    <h1>{{ $clinic }}</h1>
    <div class="muted">Rastreio de linguagem e fala · {{ $session->screened_on->format('d/m/Y') }} · {{ $session->institution_name }}</div>
</div>

<h2>Dados pessoais</h2>
<table>
    <tr><td class="muted" width="30%">Criança</td><td>{{ $session->child_name }}</td></tr>
    <tr><td class="muted">Data de nascimento</td><td>{{ $session->birth_date->format('d/m/Y') }}</td></tr>
    <tr><td class="muted">Idade cronológica</td><td>{{ $ageLabel }}</td></tr>
    <tr><td class="muted">Encarregado de educação</td><td>{{ $session->guardian_name }}</td></tr>
    <tr><td class="muted">Contacto</td><td>{{ $session->guardian_email }} · {{ $session->guardian_phone }}</td></tr>
</table>

<h2>Resumo visual</h2>
<table>
    @foreach (['speech' => 'Fala / Articulação', 'comprehension' => 'Compreensão', 'expression' => 'Expressão'] as $key => $label)
        @php $a = $result['areas'][$key]; $pct = $a['percent']; @endphp
        <tr>
            <td width="26%">{{ $label }}</td>
            <td width="54%">
                <div class="bar-bg"><div class="bar" style="width: {{ $pct ?? 0 }}%"></div></div>
            </td>
            <td width="20%">
                @if ($pct !== null) <strong>{{ number_format($pct, 0) }}%</strong> ({{ $a['points'] }}/{{ $a['applicable'] }}) @else — @endif
                @if ($a['not_applied'] > 0)<br><span class="muted">{{ $a['not_applied'] }} não aplicado(s)</span>@endif
            </td>
        </tr>
    @endforeach
    <tr>
        <td><strong>Total</strong></td>
        <td><div class="bar-bg"><div class="bar" style="width: {{ $result['total']['percent'] ?? 0 }}%"></div></div></td>
        <td><strong>{{ $result['total']['percent'] !== null ? number_format($result['total']['percent'], 0).'%' : '—' }}</strong> ({{ $result['total']['points'] }}/{{ $result['total']['applicable'] }})</td>
    </tr>
</table>

<h2>Resumo clínico — sons alterados ou omitidos</h2>
@if (count($result['findings']) === 0)
    <p class="muted">Não foram identificados sons alterados ou omitidos no bloco de fala.</p>
@else
    <table>
        <tr><th>Palavra</th><th>Som-alvo</th><th>Produção</th><th>Adquirido para a idade?</th></tr>
        @foreach ($result['findings'] as $f)
            <tr>
                <td>{{ $f['word'] }}</td>
                <td>{{ $f['phoneme_label'] }}</td>
                <td>{{ $f['outcome_label'] }}@if ($f['produced_sound']) — disse «{{ $f['produced_sound'] }}»@endif</td>
                <td>@if ($f['expected_for_age'])<span class="flag">Sim — já deveria estar adquirido</span>@else Ainda dentro do esperado @endif</td>
            </tr>
        @endforeach
    </table>
@endif

<h2>Conclusão e recomendação</h2>
<div class="box {{ $result['attention'] ? 'attention' : 'ok' }}">{{ $result['conclusion'] }}</div>

<p class="muted" style="margin-top:14px">Este relatório resulta de um rastreio automático e não constitui um diagnóstico clínico.</p>
</body>
</html>
