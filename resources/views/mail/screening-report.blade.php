<p>Olá,</p>
@if ($forClinic)
<p>O rastreio de linguagem e fala de <strong>{{ $session->child_name }}</strong> ({{ $session->institution_name }}) foi concluído. O relatório segue em anexo (PDF).</p>
@else
<p>O rastreio de linguagem e fala de <strong>{{ $session->child_name }}</strong> foi concluído. O relatório segue em anexo (PDF).</p>
<p>Este relatório é um rastreio e não constitui um diagnóstico. Em caso de dúvida, a clínica entrará em contacto ou pode contactá-la diretamente.</p>
@endif
<p>{{ $clinicName }}</p>
