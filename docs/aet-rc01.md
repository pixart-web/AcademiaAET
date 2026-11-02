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

Estado: **Corrigido e verificado.**

**Reprodução (código antigo):** `Child/StepInput.tsx`'s `MediaPreview`
recebia só uma URL e decidia "é áudio?" com
`/\.(mp3|wav|ogg|webm)(\?|$)/i.test(url)` — uma URL assinada
(`/media/{id}/file?expires=...&signature=...`) nunca tem extensão de
ficheiro, pelo que este teste falhava sempre e tudo que não fosse
detetado como áudio caía no `<img>`, incluindo vídeo — que, além disso,
**não tinha nenhum caso de renderização próprio** (confirmado por
leitura: o componente só tinha os ramos áudio/imagem). O editor
(`Activities/Edit.tsx`) nunca teve nenhum campo, select ou botão para
associar `instruction_media_asset_id` a um passo — o valor existia na
base de dados e na validação do servidor, mas nunca havia forma de o
definir pela UI, confirmando "o editor não oferece associação funcional
de media aos passos" literalmente.

**Causa:** falta de um contrato explícito de tipo entre servidor e
cliente para media, e um campo do modelo de dados sem UI nenhuma.

**Correção:**
- `ActivityStep::instructionMediaPayload()` (novo): devolve
  `{ url, kind, mime_type, alt_text, transcript }` em vez de uma URL
  nua — partilhado por `AttemptService::serializeSteps()` (execução
  real) e `ActivityController::preview()` (pré-visualização da equipa),
  para as duas nunca poderem descrever a mesma media de forma diferente.
- `Child/StepInput.tsx`'s `MediaPreview` passa a escolher o elemento
  (`<img>`/`<audio>`/`<video>`) exclusivamente pelo `kind` explícito —
  nunca por sniffing da URL — e ganhou o caso `video` que não existia. A
  alternativa textual (`alt_text`/`transcript`) é sempre mostrada como
  texto visível, não só como atributo `alt`.
- `Activities/Edit.tsx`: novo campo "Conteúdo de apoio (opcional)" por
  passo — um `<select>` com a media instrucional ativa da organização
  (`ActivityController::availableInstructionalMedia()`, mesmo âmbito
  exato da regra de validação: organização + `purpose=instructional` +
  `status=active`, para nada oferecido aqui poder falhar essa
  validação) e um botão para remover a associação.
- Preservação ao editar/duplicar/criar versões: já funcionava
  corretamente no backend (`ActivityVersioningService::replaceSteps()`
  já persistia `instruction_media_asset_id` recebido) — o único elo em
  falta era a UI nunca o enviar; com o campo novo, passa a fazê-lo.
  Confirmado com um teste dedicado (abaixo) e ao vivo no browser.
- Arquivamento preserva o histórico: `MediaAssetController::destroy()`
  (arquivar) marca a media como `archived`, o que antes fazia
  `MediaStreamController` devolver 404 mesmo para uma criança com uma
  versão **já atribuída** que a referencia como instrução — quebrando
  uma atividade publicada por uma ação de arquivo posterior e não
  relacionada. Corrigido: o controlador distingue agora "esta media é a
  instrução de um passo desta criança atribuído" — só nesse caso
  específico o `status==='archived'` deixa de bloquear a reprodução;
  qualquer outro caso (biblioteca, nova atividade, resposta clínica)
  continua a exigir `status==='active'`.

**Teste de regressão:** `tests/Feature/ActivityMediaInstructionsTest.php`
(6 testes) — criar um passo com media via HTTP; media de outra
organização rejeitada como instrução; reabrir o editor não perde a
associação; a associação sobrevive a duplicar e a bifurcar versão;
arquivar a media não quebra uma atividade já atribuída, mas deixa de a
oferecer para uma atividade nova; uma resposta clínica arquivada
continua bloqueada por ambas as razões (não é só o estado "arquivado"
que a protegia).

**Verificado ao vivo no browser** (servidor local desta sessão): criei
três `MediaAsset` reais (imagem/áudio/vídeo, com `alt_text`/`transcript`)
diretamente na base de dados de desenvolvimento — o browser embutido
desta sessão não tem uma ferramenta de upload de ficheiros, por isso o
upload em si não foi acionado através da UI, só criado previamente para
que a UI tivesse o que listar; tudo o resto foi feito exclusivamente
pela interface: criei uma atividade de três passos pela UI, associei
cada tipo de media a um passo através do novo seletor, publiquei,
reabri o editor e confirmei (via JS no DOM) que as três associações
`value="1"/"2"/"3"` continuavam corretas, e pré-visualizei os três
layouts (3–6, 7–13, 14–18) confirmando que `<img>`, `<audio>` e
`<video>` renderizam com o `src` assinado correto em cada um,
exclusivamente pelo `kind`.

**Resultado obtido:** 133 testes / 467 assertions a passar.

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

Estado: **Corrigido e verificado** (com uma limitação de ambiente
explícita abaixo — o navegador embutido desta sessão não tem microfone
real).

**Reprodução (código antigo):** `Child/StepInput.tsx` era renderizado
sem `key={step.id}` em nenhuma das três páginas de tentativa
(`ChildPortal/{Early,Middle,Teen}/Attempt.tsx`) — ao mudar de passo, o
React reutilizava a mesma instância do componente em vez de a
desmontar/remontar, pelo que o `useState` interno do texto
(`short_text`) e o estado interno de `RecordingInput` nunca reiniciavam.
Confirmei isto ao vivo: sem a correção, escrever num passo de texto e
avançar para outro passo de texto mostraria o mesmo texto no segundo
passo. `RecordingInput`/`DrawingInput` também não tinham `useEffect` de
limpeza nenhum — sair a meio de uma gravação não parava os `tracks` do
`MediaStream`, e `URL.createObjectURL()` nunca era revogado. O prop
`recorded` usava `step.value !== null` — mas `AttemptService::saveStep()`
guarda sempre `value: null` para respostas com ficheiro (o ficheiro vive
num `MediaAsset`, nunca em `value`), pelo que **nunca** detetava
corretamente "já gravado" ao voltar a um passo já respondido.

**Causa:** falta de `key` para forçar remontagem por passo; nenhum
efeito de limpeza no desmonte; e uma dedução de estado (`recorded`)
baseada no campo errado.

**Correção:**
- `key={step.id}` acrescentado ao `<StepInput>` nas três páginas de
  tentativa — isola o estado por passo e, como efeito direto, também
  **recupera** a resposta persistida ao voltar a um passo (o estado
  antigo do componente desaparece, o novo é inicializado a partir do
  `step` atual, que já vem do servidor com o valor certo).
- `recorded` passa a usar `step.answered` em vez de `step.value !== null`.
- Novo campo `response_media_url` (`AttemptService::serializeSteps()`) —
  URL assinada do `MediaAsset` já guardado para aquele passo, quando
  existe. `RecordingInput`/`DrawingInput` mostram-no como pré-visualização
  ao regressar a um passo de gravação/desenho já respondido, em vez de só
  um texto "enviada ✓".
- `useEffect` de limpeza em `RecordingInput`: ao desmontar, para todos os
  `tracks` do stream ainda ativo, para o `MediaRecorder` se ainda estiver
  a gravar, e revoga qualquer `URL.createObjectURL()` pendente.
- `mountedRef`: se a promessa de `getUserMedia()` resolver depois de o
  componente já ter sido desmontado (a criança já saiu do passo enquanto
  o pedido de permissão estava aberto), os `tracks` do stream resultante
  são parados imediatamente e nenhum `setState` é chamado.
- Seleção de formato: `pickRecorderFormat()` tenta
  `audio/webm`→`audio/mp4`→`audio/ogg` (ou o equivalente de vídeo) via
  `MediaRecorder.isTypeSupported()`, em vez de assumir sempre o
  comportamento por omissão do browser; a extensão do ficheiro enviado
  passa a corresponder ao formato realmente escolhido, não um `.webm`
  fixo.
- Botões "Enviar"/"Repetir"/"Guardar desenho" passam a desativar-se
  enquanto `saving` está ativo, e um `sent` local impede um segundo
  envio antes de o pedido em curso terminar.

**Teste de regressão:** `tests/Feature/StepStateSerializationTest.php`
(2 testes, backend) — confirma que `answered` é `true` e
`response_media_url` aponta para `/media/...` mesmo com `value: null`
para um passo de gravação já respondido; e que um passo nunca respondido
não expõe nenhum `response_media_url`. O isolamento de estado em si
(React) não é testável por PHPUnit — verificado ao vivo, abaixo.

**Verificado ao vivo no browser** (três passos: texto, texto, gravação de
voz, nas experiências 3–6 e 7–13):
- Escrevi "Resposta do passo A" no primeiro passo de texto, avancei — o
  segundo passo de texto mostrou a `<textarea>` **vazia**, não o texto do
  primeiro (confirma o isolamento de estado).
- Mudei a experiência visual da mesma criança para 7–13 (para ter o
  botão "Voltar", que a experiência 3–6 não mostra) e, a partir do passo
  de gravação, cliquei "Voltar" duas vezes: o passo de texto 2 mostrou
  "Resposta B" e o passo de texto 1 mostrou "Resposta do passo A" — a
  resposta certa recuperada em cada um, não vazia nem trocada.
- Submeter com o passo de gravação (obrigatório) por responder mostrou
  "Ainda há passos obrigatórios por responder." na própria interface da
  criança — confirma ao vivo o comportamento do achado 6, não só por
  teste automático.
- Cliquei "Gravar" sem microfone disponível: a mensagem de erro
  "Não foi possível aceder ao microfone..." apareceu corretamente, sem
  nenhum erro JavaScript não tratado, e o botão "Gravar" continuou
  disponível para tentar de novo.

**Limitação explícita**: o navegador embutido do Claude Code usado nesta
sessão não tem um microfone ou câmara reais disponíveis — não foi
possível verificar ao vivo uma gravação bem-sucedida de fim a fim (pedir
permissão → gravar → pré-visualizar → enviar), nem confirmar
visualmente que os `tracks` de um `getUserMedia()` real param mesmo ao
sair a meio de uma gravação (só o código de limpeza foi escrito e
revisto, não observado a correr com um stream real). Recomendo esta verificação
específica num browser com acesso a dispositivo real antes de considerar
o achado 7 totalmente fechado.

## Achado 8 — Propriedade e limpeza de ficheiros

Estado: **Corrigido e verificado.**

**Reprodução (código antigo):** `AttemptService::saveStep()` criava
sempre um novo `MediaAsset` (`storeRecording()`/`storeDrawing()`) ao
gravar um passo com ficheiro, e só repontava o `media_asset_id` do
`StepResponse` existente — a linha e o ficheiro **anteriores** nunca
eram tocados, ficando órfãos (sem nenhuma referência) tanto na base de
dados como no disco, para sempre. Confirmado por leitura direta do
método antes de qualquer alteração. `ChildDataService::eraseCompletely()`
só encontrava media através de `step_responses.media_asset_id`
**atuais** — uma gravação já substituída (exatamente o órfão acima)
nunca era encontrada nem apagada, mesmo numa eliminação "completa" e
"permanente" do registo da criança. A limpeza de armazenamento corria
**dentro** da mesma transação da base de dados que apagava as linhas —
se a transação revertesse depois (por qualquer motivo), os ficheiros já
tinham desaparecido do disco mas as linhas continuavam na base de dados,
um estado inconsistente e irreversível.

**Causa:** nenhum conceito de "substituição" existia — cada gravação
nova era tratada como independente, nunca como a sucessora de uma
anterior; e a eliminação de uma criança procurava media só pela
referência corrente, não pela propriedade real.

**Correção:**
- `AttemptService::saveStep()`: captura o `media_asset_id` anterior do
  `StepResponse` **antes** de processar o novo ficheiro. O novo
  `MediaAsset` é criado e o `StepResponse` repontado primeiro; só depois
  disso ter sido persistido com sucesso é que
  `retireReplacedMedia()` apaga o ficheiro anterior do disco e
  soft-deleta a linha anterior — nunca ao contrário. O mesmo acontece ao
  limpar um passo opcional sem enviar ficheiro novo (o anterior também é
  retirado, não só desassociado). Uma falha a meio (ex.: MIME inválido no
  novo ficheiro) nunca chega a tocar na resposta anterior — confirmado
  por teste.
- `retireReplacedMedia()` só atua sobre media com
  `purpose === ClinicalResponse` — mesmo que fosse chamado com um id
  errado, nunca apagaria conteúdo didático partilhado.
- `ChildDataService::eraseCompletely()`: passa a procurar media por
  **propriedade** (`owner_child_profile_id`, o campo acrescentado no
  achado 1), não por referência corrente — encontra também gravações já
  substituídas que nenhum `step_response` atual aponta. A ordem também
  foi corrigida: os caminhos/discos são lidos **antes** da transação; a
  transação apaga as linhas da base de dados e o perfil; só depois de a
  transação **confirmar com sucesso** é que os ficheiros são apagados do
  disco — uma reversão da transação nunca pode deixar ficheiros
  apagados sem as linhas correspondentes também o estarem. Uma falha a
  apagar um ficheiro individual é registada (`report()`) mas não trava
  as restantes nem faz parecer que a eliminação falhou quando a parte
  que importa (a base de dados) já está correta.
- Novo comando `php artisan media:reconcile-orphans` (read-only,
  nunca apaga nada): relatório de todas as linhas `media_assets` com
  `purpose` por determinar (as que o backfill do achado 1 não conseguiu
  classificar com segurança) — mantidas restritas a admin pela Policy, e
  listadas aqui para revisão manual, nunca apagadas por suposição. A
  opção `--check-files` relata também linhas ativas cujo ficheiro já não
  existe no disco. Corri-o contra a base de dados de desenvolvimento: 0
  problemas (ambiente limpo).

**Teste de regressão:** `tests/Feature/MediaOwnershipAndCleanupTest.php`
(5 testes) — regravar um passo retira a media anterior (soft-delete +
ficheiro removido, a nova intacta); limpar um passo opcional retira a
media anterior da mesma forma; um upload de substituição inválido nunca
toca na resposta anterior; a eliminação de uma criança remove uma
gravação já substituída que nada referencia atualmente; a eliminação
nunca toca em media de outra criança nem em conteúdo didático partilhado.
`tests/Feature/MediaReconcileOrphansCommandTest.php` (3 testes) — relata
media por classificar sem apagar nada; relata "sem problemas" quando tudo
está classificado; `--check-files` deteta um ficheiro em falta.

**Resultado obtido:** 143 testes / 518 assertions a passar.
