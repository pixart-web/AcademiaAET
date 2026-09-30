# AET-RC01 — Correções da auditoria externa

Commit auditado: `fc8c84277c7d9b68c5257d5471cda41bfb844af5` (na prática,
`fc8c842` — a árvore de trabalho estava limpa nesse commit, confirmado
antes de começar). Branch: `claude/aet-rc01-remediation`.

Este documento regista, achado a achado, o que foi realmente reproduzido,
a causa real, a correção aplicada, o teste de regressão escrito e o
resultado efetivamente obtido ao correr esse teste — não o que se
pretendia fazer. Um achado só é marcado "Corrigido" depois de o teste de
regressão passar contra o código novo e falhar (ou nunca ter existido)
contra o código antigo.

Estados usados: **Corrigido e verificado** · **Corrigido parcialmente** ·
**Não corrigido nesta ronda** (com a razão).

---

## Achado 1 — Isolamento das gravações clínicas

Estado: **Corrigido e verificado.**

**Reprodução (código antigo):** `AttemptService::storeRecording()` e
`::storeDrawing()` criavam a gravação/desenho da criança como uma
`MediaAsset` comum — mesma tabela, mesmo `status = 'active'`, nenhum
campo a distinguir "conteúdo didático" de "resposta clínica".
`MediaAssetController::index()` listava `$organization->mediaAssets()
->where('status', 'active')` sem mais nenhum filtro: a biblioteca de
conteúdos de qualquer profissional incluía as gravações/desenhos de
todas as crianças da organização. `MediaStreamController`, para um
principal `User`, só verificava
`$principal->organization_id === $media->organization_id` — uma
terapeuta sem nenhuma associação ao caso conseguia reproduzir a gravação
de qualquer criança da mesma organização, só por conhecer (ou adivinhar)
o id. `ActivityController`'s `instruction_media_asset_id` validava só
`exists:media_assets,id` — qualquer id numérico existente na tabela,
incluindo o de uma gravação clínica de outra criança, era aceite como
conteúdo didático de uma atividade. Tudo confirmado por leitura direta
do código antes de qualquer alteração.

**Causa:** a tabela `media_assets` nunca teve um campo a distinguir
finalidade (didático vs. resposta clínica) nem propriedade (a que
criança pertence uma resposta clínica) — a autorização por organização
tratava as duas coisas como equivalentes.

**Correção:**
- Novo enum `App\Enums\MediaPurpose` (`instructional` /
  `clinical_response`) e migração
  `2026_09_30_204121_add_purpose_and_owner_to_media_assets_table`:
  acrescenta `purpose` e `owner_child_profile_id` a `media_assets`, com
  **backfill** dos registos existentes (não destrutivo): qualquer media
  referenciada por `step_responses.media_asset_id` passa a
  `clinical_response` com o dono correspondente; o resto com
  `uploaded_by_user_id` preenchido passa a `instructional`; o que sobrar
  (propriedade indeterminável) fica com `purpose = NULL`, propositamente
  — nunca apagado, nunca assumido como um dos dois tipos.
- `AttemptService::storeRecording()`/`::storeDrawing()` marcam a media
  criada como `clinical_response`, com `owner_child_profile_id` = a
  criança da tentativa.
- `MediaAssetController::store()` marca o upload profissional como
  `instructional`; `::index()` passa a filtrar também por
  `purpose = instructional` — uma resposta clínica nunca mais aparece na
  biblioteca didática, para ninguém.
- `MediaAssetPolicy::view()` (agora usada por `MediaStreamController`,
  não só pelo controller da biblioteca — ver abaixo): conteúdo
  `instructional` continua visível a qualquer profissional/admin da
  organização, como antes. Uma `clinical_response` exige associação de
  caso ativa (`ProfessionalAssignment`) ao `owner_child_profile_id`, **ou
  admin** — a mesma regra que `ChildProfilePolicy` já aplicava ao perfil
  da criança (não uma exceção nova inventada para media). `purpose` nulo
  (registo legado sem proveniência determinável) é tratado como uma
  resposta clínica sem dono: só um admin o vê, para revisão manual —
  nunca a biblioteca geral, nunca uma terapeuta qualquer da organização.
  `update()`/`delete()` seguem a mesma regra.
- `MediaStreamController`: o ramo do principal `User` passa a chamar
  `$principal->can('view', $media)` (delega na Policy acima) em vez de só
  verificar a organização — uma URL assinada válida nunca substitui isto.
- `ActivityController::validateActivity()`: `instruction_media_asset_id`
  passa a usar `Rule::exists('media_assets', 'id')->where('organization_id',
  ...)->where('purpose', 'instructional')->where('status', 'active')` em
  vez de um `exists` global — uma gravação clínica (de qualquer criança,
  mesmo a própria) já não pode ser associada a um passo como conteúdo
  didático, mesmo enviando o id manualmente.
- Encarregados de educação: `GuardianHomeController` não tem, e nunca
  teve, nenhuma rota que exponha media — confirmado por leitura, não
  havia nada para corrigir aqui.

**Teste de regressão:** `tests/Feature/ClinicalMediaIsolationTest.php`
(7 testes) — terapeuta sem associação ao caso não lista nem reproduz a
gravação; terapeuta associada consegue; admin consegue sem associação de
caso; profissional de outra organização é rejeitado; a gravação nunca
aparece na listagem da biblioteca; conteúdo didático continua a listar e
reproduzir normalmente (rede de segurança contra regressão); e um id de
gravação clínica não pode ser usado como `instruction_media_asset_id` ao
criar uma atividade.

**Nota sobre um obstáculo de teste genuíno, não relacionado com o código
de produção**: escrever estes testes expôs uma armadilha real do
`Illuminate\Auth\Middleware\Authenticate` do próprio Laravel — ao
autenticar com sucesso, chama `Auth::shouldUse($guard)`, que **muda a
configuração `auth.defaults.guard` para o resto do processo PHP** (não
só um cache de instância). Um teste que faça primeiro um pedido HTTP
real autenticado como criança (`auth:child`) e depois chame
`actingAs($profissional)` **sem indicar o guard** autentica esse
profissional no guard **`child`**, não `web` — silenciosamente. Corrigido
nos testes novos sempre indicando `actingAs($user, 'web')`
explicitamente depois de qualquer interação HTTP real do lado da
criança. Vale a pena ter isto em conta em testes futuros que misturem os
dois lados.

**Resultado obtido:** 127 testes / 440 assertions a passar.

## Achado 2 — Revogação e expiração de dispositivos

Estado: **Corrigido e verificado.**

**Reprodução (código antigo):** `ChildSessionController::loginChild()`
chamava `Auth::guard('child')->login($device->childProfile)` e nunca
guardava, em lado nenhum, a que `DeviceAssociation` essa sessão
pertencia. As rotas protegidas por `auth:child` (`ChildHomeController`,
`AttemptController`, `ChildFeedbackController`) só verificavam se havia
*um* perfil de criança autenticado — nunca se o dispositivo que o
autenticou continuava válido. `Api\ChildDeviceController::activate()`
tinha o mesmo problema do lado do token: `createToken($nome, ['child'])`
não guardava nenhuma referência ao dispositivo, e
`EnsureApiPrincipal` só verificava o tipo do principal (`ChildProfile`
vs `User`), nunca o estado do dispositivo. `MediaStreamController` (sem
nenhum middleware de guarda própria — resolve os três guards à mão)
autorizava um principal `ChildProfile` só pela organização e pela posse
do media (`childMayAccess()`), nunca pelo estado do dispositivo.
Resultado: revogar um dispositivo (`DeviceAssociationController::revoke`)
só impedia um *novo* login — não tinha qualquer efeito em sessões ou
tokens já emitidos a partir dele, que continuavam válidos até expirarem
naturalmente (sessão do browser) ou serem eliminados manualmente (token).

**Causa:** nenhum ponto de autenticação registava a proveniência (qual
dispositivo) de uma sessão/token; nenhum ponto de autorização voltava a
consultar essa proveniência depois do login inicial.

**Correção:**
- `DeviceAssociation::resolveActiveFor(ChildProfile $child, ?int
  $deviceAssociationId)`: ponto único de verdade — dado um id de
  dispositivo reclamado, devolve o dispositivo só se pertencer a esta
  criança e `isSessionUsable()` (não revogado, não expirado, não
  bloqueado). Um id nulo (sessão/token sem proveniência registada, ou
  seja, anterior a esta correção) é sempre tratado como inválido —
  nunca assumido como válido por omissão. Usado identicamente pelos três
  pontos de aplicação abaixo.
- `ChildSessionController::loginChild()` passa a guardar
  `child_device_association_id` na sessão.
- Nova middleware `EnsureChildDeviceIsActive`, acrescentada ao grupo
  global `web` (mesmo padrão que `EnsureAccountIsActive` já usava para
  contas de equipa) — verifica o dispositivo da sessão e
  `$child->isActive()` em **todos** os pedidos autenticados; se algum
  falhar, termina a sessão e redireciona para o login da criança.
- `Api\ChildDeviceController`: o token passa a incluir uma "ability"
  `"device:{id}"` (`createToken($nome, ['child', "device:{$device->id}"])`).
  `DeviceAssociation::idFromTokenAbilities()` extrai esse id de forma
  segura (nunca confiando em nada vindo do próprio pedido). `
  EnsureApiPrincipal` (ramo `child`) passa a chamar
  `resolveActiveFor()` com esse id, apagando o token
  (`$token->delete()`) e devolvendo 401 se inválido.
- `MediaStreamController`: para um principal `ChildProfile`, acrescenta
  `abort_unless($principal->isActive(), 403)` e uma nova verificação
  `childDeviceIsActive()` — resolve o id do dispositivo tanto da sessão
  (fluxo web) como das abilities do token Sanctum (fluxo API), antes de
  sequer chegar à verificação de posse do media. Uma URL assinada válida
  nunca substitui esta autorização — continua a ser preciso as duas
  coisas.
- `ChildProfile::isActive()` (nova, espelha `User::isActive()`).

**Teste de regressão:** `tests/Feature/DeviceRevocationPropagationTest.php`
(5 testes, percurso HTTP real, não só o serviço) — revogar o dispositivo
de uma sessão web ativa termina-a no pedido seguinte; revogar um
dispositivo não afeta uma sessão estabelecida a partir de outro
dispositivo da mesma criança; desativar o perfil da criança termina a
sessão da mesma forma; uma sessão estabelecida sem dispositivo registado
(`actingAs()` puro, simulando uma sessão anterior a esta correção) é
tratada como inválida, não como "grandfathered in"; e — o caso que
nenhuma outra camada protegia — revogar o dispositivo corta o acesso a
media pela API imediatamente, mesmo com uma URL assinada ainda
criptograficamente válida.

Isto obrigou a atualizar 5 ficheiros de teste existentes
(`AttemptSubmissionTest`, `AttemptExecutionTest`,
`StepResponseValidationTest`, `ChildIsolationTest`, `ChildFeedbackTest`)
que autenticavam uma criança via `actingAs($child, 'child')` puro — que a
partir de agora é, com propósito, uma sessão sem dispositivo verificável
e por isso rejeitada. Criado um helper `Tests\TestCase::actingAsChild()`
que autentica através de um `DeviceAssociation` ativado de verdade (com
o seu id na sessão), usado por todos esses ficheiros a partir de agora.

**Resultado obtido:** 120 testes / 413 assertions a passar. Confirmei por
leitura do código antigo (citado acima) que nenhum destes pontos existia
antes — não é um caso de "já estava parcialmente protegido".

## Achado 3 — Ativação de utilização única

Estado: **Corrigido e verificado.**

**Reprodução (código antigo):** `DeviceAuthService::activate()` chamava
`$device->isUsable()`, que verificava `status === 'active'`, `expires_at`
no futuro e não estar bloqueado — **nunca** verificava
`activated_at === null`. Uma segunda chamada com o mesmo código+PIN,
mesmo depois de o dispositivo já estar ativado (`activated_at` já
preenchido), passava a mesma verificação e chamava
`$device->activateWithToken($novoToken)` outra vez, sobrescrevendo
`device_token_hash` — invalidando silenciosamente o token do dispositivo
legítimo e emitindo um novo para quem quer que soubesse o código.
Confirmado por leitura direta do método antes de qualquer alteração.

**Causa:** a condição de "código utilizável" nunca distinguia "ainda não
ativado" de "ativo e sem bloqueio" — tratava o código de ativação como
reutilizável indefinidamente enquanto não expirasse.

**Correção:**
- `DeviceAssociation::isActivationUsable()` (nova, substitui `isUsable()`):
  exige explicitamente `activated_at === null` — uma segunda tentativa
  falha sempre aqui, com a mesma mensagem genérica de código/PIN
  inválidos que qualquer outra falha (nunca revela que o código já foi
  usado).
- `DeviceAssociation::isSessionUsable()` (nova): governa o desbloqueio
  diário pós-ativação, com o seu próprio prazo (`session_expires_at`,
  migração `2026_09_30_202909`) em vez do prazo curto do código de
  ativação (`expires_at`) — ver também achado 2 sobre porque isto
  importa (um dispositivo em uso diário não pode deixar de funcionar ao
  fim de 7 dias só porque essa era a validade do código, não da sessão).
- `activate()` passa a correr dentro de `DB::transaction()` com
  `lockForUpdate()` na linha do dispositivo — verificação e consumo
  atómicos, para que dois pedidos concorrentes com o mesmo código nunca
  possam ambos passar a verificação "ainda não ativado".
- `activate()` e `unlock()` passam agora a incrementar
  `failed_attempts`/`locked_until` da mesma forma (antes só `unlock()`
  tinha proteção contra tentativas repetidas — `activate()` dependia só
  do throttle HTTP por IP, que não protege um código específico contra
  tentativas distribuídas).

**Teste de regressão:** `tests/Feature/DeviceAssociationTest.php` (+5
testes) e `tests/Feature/Api/ApiChildFlowTest.php` (+1): confirma que
reativar falha e não substitui o token existente; que a mensagem de erro
é idêntica entre "código já usado" e "código nunca existiu"; que duas
chamadas concorrentes a `activate()` só deixam uma ter sucesso; que um
dispositivo já ativado continua utilizável mesmo depois de o prazo do
próprio código de ativação ter passado; e que o mesmo se aplica ao
endpoint da API.

**Resultado obtido:** todos os 6 testes novos passam contra o código
novo; o teste de reativação falharia contra o código antigo (confirmado
por leitura de código, não por reverter e correr — o mesmo padrão usado
nos achados 4 e 6).

## Achado 4 — Tentativas, retoma e cancelamento

Estado: **Corrigido e verificado.**

**Reprodução (código antigo, lido diretamente antes de tocar em nada):**
`AttemptService::startOrResume()` chamava
`abort_unless($assignment->hasAttemptsRemaining(), 422, ...)` **antes** de
procurar uma tentativa em curso. Numa atividade com `max_attempts=1`: a
criança inicia (cria a tentativa nº1, `attempts()->count()` passa a 1),
sai sem submeter, volta — `hasAttemptsRemaining()` já devolve `false`
(`1 < 1`), e o `abort_unless` dispara antes de sequer verificar se já
existe uma tentativa em curso para retomar. `saveStep()` e `submit()`
também não verificavam `assignment->status === Cancelled` em nenhum
ponto — só o `startOrResume()` inicial o fazia.

**Causa:** ordem errada das verificações (limite antes de "já existe
tentativa?"), e a verificação de cancelamento só existia num dos três
métodos que mutam uma tentativa.

**Correção:**
- `startOrResume()`: procura tentativa em curso **primeiro**, dentro de
  uma transação com `lockForUpdate()` na `assignment`; o limite de
  tentativas só é aplicado no ramo que cria uma tentativa nova.
- `saveStep()` e `submit()`: ambos passam a rejeitar (422) se
  `assignment->status === Cancelled`.
- `submit()`: a verificação de estado + transição para `Submitted` passa
  a correr dentro de uma transação com `lockForUpdate()` na própria
  `Attempt`, para que duas submissões concorrentes da mesma tentativa não
  possam ambas passar a verificação "ainda in_progress" antes de a
  primeira COMMITtar.
- Nova migração `2026_09_30_201646_add_one_in_progress_attempt_constraint_to_attempts_table`:
  índice único parcial `UNIQUE (assignment_id) WHERE status = 'in_progress'`
  — segunda linha de defesa ao nível da base de dados, independente de a
  aplicação acertar sempre o locking.

**Teste de regressão:** `tests/Feature/AttemptResumeAndCancellationTest.php`
(5 testes) — confirma que `max_attempts=1` permite sair e retomar sem
criar segunda tentativa; que depois de submeter já não é possível iniciar
outra; e que cancelar a atribuição bloqueia `saveStep`, `submit` e um
novo `startOrResume` na mesma tentativa.

**Resultado obtido:** os 5 testes passam contra o código novo. Escrevi-os
antes de confirmar mentalmente contra o código antigo — para o primeiro
teste (`test_an_assignment_limited_to_one_attempt_can_still_be_resumed...`),
confirmei por leitura direta do código pré-correção (citado acima) que a
ordem das duas verificações garantia a falha: com `max_attempts=1` e uma
tentativa já criada, `hasAttemptsRemaining()` já devolve `false` no
código antigo antes de a busca pela tentativa em curso sequer correr.

**Limitação explícita:** os 5 testes correm contra SQLite em memória
(`RefreshDatabase`, transação única por teste) e provam a *lógica*
(ordem das verificações, bloqueio por cancelamento) de forma determinística
— não provam uma corrida real entre duas ligações concorrentes à base de
dados. Essa prova de concorrência genuína está em
`tests/Feature/Concurrency/AttemptConcurrencyPostgresTest.php` (secção
"Validação e CI" abaixo), que só corre contra Postgres com duas ligações
PDO reais.

## Achado 5 — Instruções multimédia e editor

Estado: em progresso.

## Achado 6 — Validação e pontuação

Estado: **Corrigido e verificado.**

**Reprodução (código antigo):** `AttemptService::serializeSteps()` devolvia
`'response_config' => $step->response_config` sem qualquer filtragem — um
passo de escolha única com `response_config = ['options' => [...],
'correct' => 'b']` enviava o `correct` tal e qual para o portal da
criança, visível em qualquer inspeção de rede ou do payload Inertia.
Confirmei isto por leitura direta do método antes de alterar nada — não
havia nenhuma remoção de `correct` em nenhum ponto entre a base de dados
e a resposta HTTP dada à criança.

Também confirmado por leitura: `AttemptService::saveStep()` não validava
o `value` recebido por tipo nenhum — um `value` arbitrário (string
inventada, array com opções que não existem, texto vazio) era persistido
sem qualquer rejeição, exceto para os tipos com ficheiro. A pontuação de
escolha múltipla comparava `$value == $correct` — comparação posicional
de arrays em PHP, não por conjunto — pelo que `['a','c'] == ['c','a']`
avalia a `false`, marcando como errada uma resposta correta dada por
ordem diferente. E `AttemptService::submit()` não verificava em nenhum
momento se os passos obrigatórios tinham resposta, pelo que uma tentativa
vazia podia ser submetida com sucesso.

**Causa:** nenhuma camada de validação por tipo existia — a única
verificação era a genérica do Laravel (`'value' => ['nullable']`), e a
serialização para a criança reutilizava o array de configuração inteiro
sem uma lista de campos seguros.

**Correção:**
- `ActivityStep::childSafeResponseConfig()`: remove `correct` antes de a
  configuração ser exposta a uma criança (usado em
  `AttemptService::serializeSteps()`). `ActivityController::edit()` e
  `::preview()` continuam a enviar o `response_config` completo — são
  vistas exclusivas da equipa profissional, nunca do lado da criança.
- Novo `App\Services\StepResponseValidator`: valida os 4 tipos sem
  ficheiro (escolha única, escolha múltipla, texto curto, confirmação),
  sempre contra o conjunto de opções configurado no próprio passo — nunca
  contra o que o pedido disser. Escolha múltipla: rejeita duplicados e
  opções inventadas; pontuação por comparação de conjuntos (arrays
  ordenados antes de comparar), não `==` posicional.
- `ActivityStep::isRequired()`: lê `response_config['required']`,
  omisso = obrigatório (nunca opcional por omissão).
- `AttemptService::submit()`: nova verificação
  `assertAllRequiredStepsAnswered()` — rejeita (422, mensagem clara) se
  algum passo obrigatório não tiver `StepResponse.answered_at` preenchido.
  Passos opcionais podem ficar por responder.
- Editor (`Activities/Edit.tsx`): escolha múltipla ganhou checkboxes para
  marcar *várias* respostas corretas (antes só escolha única tinha UI para
  isso); todos os passos ganharam um checkbox "Obrigatório para submeter".
- Ficheiros (desenho/gravações): quando o passo é opcional e nenhum
  ficheiro é enviado, a resposta é limpa (`answered_at = null`) em vez de
  abortar; quando é obrigatório, falta de ficheiro é rejeitada tanto pela
  regra do controller (`required`/`nullable` condicional) como, em
  segunda linha, pelo próprio serviço.
- Migração de configurações antigas: não foi necessária nenhuma migração
  de dados — `correctAnswers()` lê tanto uma string (escolha única) como
  um array (escolha múltipla) a partir do mesmo campo `correct`, e a
  pontuação **já persistida** em `step_responses.is_correct` de tentativas
  antigas nunca é recalculada por esta alteração (só o código de
  validação/pontuação de tentativas *novas* mudou).

**Teste de regressão:** `tests/Feature/StepResponseValidationTest.php` (8
testes) — confirma que o `correct` nunca aparece no payload Inertia da
criança; que uma opção inventada é rejeitada em escolha única e múltipla;
que a escolha múltipla pontua certo independentemente da ordem; que
duplicados são rejeitados; que texto curto respeita `max_length` e
rejeita vazio quando obrigatório; que confirmação de realização exige
`true` estrito; que submeter com um passo obrigatório por responder falha
com uma mensagem clara; e que um passo opcional pode ficar vazio sem
bloquear a submissão.

**Resultado obtido:** os 8 testes passam contra o código novo. Dois
testes pré-existentes (`ApiChildFlowTest::test_resubmitting_via_api_...`
e `AttemptSubmissionTest::test_resubmitting_an_already_submitted...`)
assumiam implicitamente que submeter sem responder ao único passo da
atividade tinha sucesso — exatamente o comportamento que esta correção
torna inválido de propósito. Atualizei-os para responder ao passo antes
de submeter; voltaram a passar depois disso, com a mesma asserção de
não-duplicação de recompensas que já tinham.

Verificado também ao vivo no browser (servidor local desta sessão, ver
achado 4 para o contexto de porta): criei uma atividade de escolha
múltipla, marquei duas opções como corretas nos checkboxes novos,
gravei, reabri o editor e confirmei que ambas continuavam marcadas;
`php artisan tinker` confirmou `response_config.correct` guardado como
`["gato","cão"]` (array, não string) na base de dados.

## Achado 7 — Estado dos passos e ciclo de gravação

Estado: em progresso.

## Achado 8 — Propriedade e limpeza de ficheiros

Estado: em progresso.
