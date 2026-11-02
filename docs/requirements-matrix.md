# Matriz de requisitos — Master Prompt Academia AET

> **AET-RC01 (ronda de remediação de auditoria externa, branch
> `claude/aet-rc01-remediation`)**: uma auditoria externa ao código
> (commit `fc8c842`) encontrou 8 problemas reais de isolamento clínico,
> autenticação de dispositivo, validação/pontuação e gestão de ficheiros
> que não tinham sido apanhados pelas rondas anteriores desta matriz —
> nomeadamente: gravações clínicas de uma criança eram listadas na
> biblioteca didática de qualquer profissional da organização e
> reproduzíveis por uma terapeuta sem ligação ao caso (achado 1);
> revogar um dispositivo não terminava sessões/tokens já emitidos
> (achado 2); um código de ativação podia ser reutilizado (achado 3);
> `response_config.correct` (a resposta certa) era enviado ao portal da
> criança, e submeter uma tentativa sem responder aos passos obrigatórios
> tinha sucesso (achado 6). Detalhe completo, achado a achado — reprodução,
> causa, correção, teste e resultado obtido — em `docs/aet-rc01.md`. As
> linhas abaixo que estes achados tocam diretamente foram atualizadas;
> o resto da matriz reflete o estado de antes desta ronda e continua
> válido.

Estados: **Implementado e verificado** (testado por teste automático e/ou no
browser nesta sessão) · **Implementado, por verificar** (código escrito,
sem teste automático nem verificação manual direta) · **Parcial** ·
**Em falta** · **Opcional/fora desta etapa** (combinado explicitamente no
master prompt).

> **Nota sobre uma classe de bug encontrada nesta ronda**: vários campos
> (`User.disabled_at`, `User.mfa_enabled`/`mfa_secret`,
> `Assignment.cancelled_at`, `DeviceAssociation.revoked_at`/
> `revoked_by_user_id`) tinham a coluna na base de dados e o código a
> chamar `->update([...])` com esse campo, mas **faltavam na lista
> `$fillable` do modelo** — o Laravel descarta silenciosamente atributos
> em mass-assignment que não estão listados, sem erro nenhum. O botão
> "funcionava" (redirecionava, mostrava sucesso) mas o valor nunca mudava
> na base de dados. Nenhum destes tinha um teste que verificasse o valor
> real do campo (só o redirecionamento ou, nalguns casos, o evento de
> auditoria) — por isso passavam. Corrigido em todos os casos encontrados;
> os testes foram reforçados para verificar o estado real, não só o
> efeito colateral. Isto foi encontrado ao escrever testes novos para MFA
> que falharam à primeira tentativa, não por revisão de código.
>
> **Bug relacionado, mesma ronda**: `ChildProfile.care_notes` estava
> marcado `#[Hidden]` no modelo — pensado para nunca ser exposto ao lado
> da criança, mas essa proteção já era feita por seleção explícita de
> campos noutro sítio (`HandleInertiaRequests`, API), tornando o `#[Hidden]`
> redundante. O efeito real: a própria página "Editar perfil" da equipa
> também nunca recebia o valor, pelo que abrir o formulário mostrava
> sempre uma nota em branco — e guardar sem reescrever a nota manualmente
> **apagava silenciosamente** qualquer nota clínica já existente. Corrigido
> (removido o `#[Hidden]`); confirmado ao vivo no browser que o formulário
> agora vem pré-preenchido.
>
> **Auditoria de seguimento (mesma ronda, depois da correção acima)**: por
> precaução, revi todas as outras 17 chamadas `->where()`/`->whereIn()` no
> código contra `status`/`role`/`kind`/`type` para procurar a mesma classe
> de bug (comparação de um enum já carregado em `Collection` do PHP contra
> uma string simples, que nunca é verdadeira). Todas as restantes — em
> `DashboardController`, `EvaluationController`, `UserController`,
> `AssignmentController`, `MediaAssetController`,
> `ProfessionalAssignmentController`, `Api\AssignmentController`,
> `AttemptService`, `DeviceAuthService`, `ChildHomeController` — são
> chamadas sobre um query builder do Eloquent (`Model::query()->where(...)`
> ou `$model->relacao()->where(...)`, sempre antes de `->get()`/`->first()`/
> `->paginate()`), que compilam para SQL `WHERE coluna = ...` sobre o valor
> em bruto da base de dados — não são afetadas pelo cast do enum em PHP.
> Confirmei que nenhuma delas repete o bug. A única instância real
> encontrada continua a ser a de `ChildProfileController::show()` (já
> corrigida acima, com `'status.value'`), que operava sobre
> `$child->assignments` já carregado como `Collection`, não sobre uma nova
> query.

## Módulo A — Autenticação e contas

| Requisito | Estado | Nota |
|---|---|---|
| Login/logout equipa | Implementado e verificado | `AuthenticatedSessionController`, testado |
| Recuperação de acesso | Implementado e verificado | Rotas Breeze padrão (`password.request`/`reset`); `tests/Feature/Auth/PasswordResetTest.php` (mantido do scaffold original) cobre o percurso completo — pedir link, receber notificação, abrir o formulário com o token, repor a password — e passa. Reclassificado nesta ronda: já estava coberto, só não tinha sido creditado como tal |
| Convites (sem registo público) | Implementado e verificado | `UserInvitationService`; registo público removido propositadamente |
| Ativação/desativação de contas | Implementado e verificado | `UserController@destroy/reactivate`, `EnsureAccountIsActive`. **Bug crítico corrigido nesta ronda**: `User::$fillable` não incluía `disabled_at` — o botão "Desativar" não fazia nada de facto (só o evento de auditoria era escrito); confirmado com teste que falhava antes da correção e ao vivo no browser |
| MFA para contas profissionais | Implementado e verificado | TOTP, QR gerado no servidor (nunca enviado a terceiros); ligado à navegação em `Profile/Edit`. **Mesmo bug crítico**: `mfa_enabled`/`mfa_secret` também não estavam em `$fillable` — ativar/desativar MFA não persistia; corrigido e agora com teste de percurso completo (ativar → login pede código → código errado no login geral falha → código certo entra) |
| Gestão/revogação de sessões | Implementado e verificado | "Sessões ativas" em Perfil → lista por conta (nunca doutra conta, testado), termina qualquer sessão exceto a atual |
| Sem credenciais fixas em produção | Implementado e verificado | `DemoDataSeeder` recusa-se a correr fora de local/testing sem `APP_ALLOW_DEMO_SEEDING=true` |

## Módulo B — Gestão de crianças e jovens

| Requisito | Estado | Nota |
|---|---|---|
| Perfil individual, dados mínimos | Implementado e verificado | Sem morada/NIF; `care_notes` oculto por omissão da API pública do modelo |
| Profissional responsável | Implementado e verificado | `ProfessionalAssignment` |
| Relações autorizadas (encarregados) | Implementado e verificado | `GuardianRelationship`, convite igual ao da equipa |
| Experiência visual por perfil | Implementado e verificado | Sugerida por idade na criação, sempre editável pela profissional, nunca muda sozinha no aniversário |
| Áreas separadas: histórico/resultados/notas | Implementado e verificado | Histórico e notas isolados (ver Segurança); resumo numérico (atribuídas/concluídas/por avaliar/atrasadas) no perfil da criança — números, não gráficos, por decisão deliberada de âmbito |

## Módulo C — Biblioteca de conteúdos

| Requisito | Estado | Nota |
|---|---|---|
| Upload imagem/áudio/vídeo/documento | Implementado e verificado | `MediaAssetController`, tipo real por `finfo`, não extensão |
| Metadados (título, descrição, alt, transcrição) | Implementado e verificado | Alt/transcrição obrigatórios para imagem/áudio/vídeo |
| Pré-visualização e arquivo | Implementado e verificado | Arquivar existe; a listagem mostra miniatura/leitor inline (imagem/áudio/vídeo) via URL assinada de curta duração, tal como no resto do sistema |
| Limites configuráveis | Implementado e verificado | Limites de tamanho por tipo movidos para `config/media.php`, ajustáveis por variável de ambiente (`MEDIA_MAX_IMAGE_KB` etc., ver `.env.example`) sem tocar em código; ainda não há UI de administração para o fazer sem acesso ao servidor. Testado: baixar o limite via config e confirmar que o upload passa a ser recusado |
| Armazenamento privado | Implementado e verificado | Disco `local` sem `serve`, só acessível via `MediaStreamController` com URL assinada de curta duração |
| Isolamento de conteúdo clínico vs. didático | Implementado e verificado | **AET-RC01 achado 1**: até esta ronda, a gravação/desenho de uma criança (`AttemptService::storeRecording`/`storeDrawing`) era guardada na mesma tabela e listada na biblioteca de qualquer profissional da organização, reproduzível por quem não tivesse nenhuma ligação ao caso. Corrigido com `MediaPurpose` (`instructional`/`clinical_response`) + `owner_child_profile_id`; `MediaAssetPolicy` exige associação de caso ativa (ou admin) para uma resposta clínica, a biblioteca didática exclui-as sempre. Ver `docs/aet-rc01.md`. |
| Propriedade e limpeza de ficheiros | Implementado e verificado | **AET-RC01 achado 8**: regravar um passo deixava o ficheiro anterior órfão (sem referência, nunca limpo mesmo por uma eliminação "completa" da criança). Corrigido — ver `docs/aet-rc01.md`; novo comando `php artisan media:reconcile-orphans` (read-only) para dados legados sem proprietário seguro. |

## Módulo D — Biblioteca e editor de atividades

| Requisito | Estado | Nota |
|---|---|---|
| Estados rascunho/publicada/arquivada | Implementado e verificado | |
| Duplicação | Implementado e verificado | `ActivityController@duplicate`, cria rascunho novo, nunca uma versão da original |
| Categorias/áreas configuráveis | Parcial | Campos de texto livre, não uma lista configurável pela clínica |
| Dificuldade, instruções, critérios | Implementado e verificado | |
| Pré-visualização nos 3 layouts | Implementado e verificado | `Activities/Preview.tsx` — interativo, nunca persiste (sem `Attempt` criado, testado) |
| Editor por passos (sem drag-and-drop) | Implementado e verificado | `Activities/Edit.tsx`. **AET-RC01 achado 5**: até esta ronda, o editor não tinha nenhum campo para associar conteúdo de apoio (`instruction_media_asset_id`) a um passo — o valor existia na base de dados e na validação, mas era sempre descartado silenciosamente por falta de UI. Corrigido com um seletor por passo, escopado exatamente à mesma regra de validação do servidor (organização + conteúdo didático ativo). |
| **7 tipos de resposta** | **Implementado e verificado** | Ver nota abaixo — o relatório anterior mencionava "6 tipos" por erro de contagem, não por funcionalidade em falta. **AET-RC01 achado 6**: `response_config.correct` (a resposta certa) era enviado tal e qual para o portal da criança, e não havia validação por tipo nem verificação de que os passos obrigatórios tinham resposta antes de submeter — corrigido, ver `docs/aet-rc01.md`. **Achado 7**: estado de texto/gravação por passo podia misturar-se entre passos consecutivos (faltava `key={step.id}`); corrigido. |
| Repetição verbal = instrução áudio + gravação | Implementado e verificado | `voice_recording`, sem reconhecimento automático de fala |
| Sem IA clínica/emoção/diagnóstico | Implementado e verificado | Nenhum destes existe no código |

### Nota sobre os 7 tipos de resposta

O `App\Enums\ResponseType` sempre teve as 7 entradas do enunciado: escolha
única, escolha múltipla, texto curto, desenho, gravação de voz, gravação de
vídeo, confirmação de realização. Todas as 7 estão implementadas em
`AttemptService::saveStep`, em `resources/js/Child/StepInput.tsx` (motor
partilhado pelos 3 shells) e no editor (`Activities/Edit.tsx`). O relatório
de progresso anterior a esta etapa disse "6 tipos" — foi um erro de
contagem no texto do relatório, confirmado e corrigido aqui.

## Módulo E — Versionamento

| Requisito | Estado | Nota |
|---|---|---|
| Versão estável após atribuição | Implementado e verificado | `ActivityVersioningService`; testado que editar uma versão atribuída cria uma nova e a atribuição antiga mantém-se na antiga |

## Módulo F — Atribuição

| Requisito | Estado | Nota |
|---|---|---|
| Atribuir com instruções/prazo/tentativas | Implementado e verificado | Formulário em `Children/Show.tsx`, ligado ao backend (corrigido nesta etapa — antes só existia a rota, sem UI) |
| Estados claros (atribuída/iniciada/submetida/revista/cancelada) | Implementado e verificado | |
| Indicação de atraso | Implementado e verificado | Badge "Atrasada" no Painel e no perfil da criança, derivado de `Assignment::isOverdue()`, testado |
| Cancelamento | Implementado e verificado | Testado. Mesma família de bug: `cancelled_at` não estava em `Assignment::$fillable` — o estado mudava para "cancelada" mas a data nunca era gravada; corrigido |

## Módulo G — Execução infantil

| Requisito | Estado | Nota |
|---|---|---|
| Mostrar atividades do próprio perfil | Implementado e verificado | |
| Reproduzir instrução | Implementado e verificado | Media do professor + `SpeakButton` (síntese de voz) |
| Responder, guardar progresso, retomar | Implementado e verificado | Testado (`AttemptExecutionTest`). **AET-RC01 achado 4**: uma atividade com `max_attempts=1` não podia ser retomada depois de a criança sair a meio (o limite era verificado antes de procurar uma tentativa em curso) — corrigido, com bloqueio em transação + índice único parcial na base de dados contra criação concorrente, verificado com concorrência real (duas ligações Postgres, uma delas num processo forkado). |
| Submeter com confirmação | Implementado e verificado | **AET-RC01 achado 6**: submeter com um passo obrigatório por responder tinha sucesso até esta ronda — corrigido, `submit()` rejeita e indica quais faltam; confirmado ao vivo na própria interface da criança, não só por teste. |
| Falha de rede/sessão expirada/upload | Implementado e verificado | Erros surfaced na UI (não silenciosos); ver commit de tratamento de falhas de upload |
| Permissão de câmara/microfone recusada | Implementado e verificado | Mensagem clara em `RecordingInput`, sem ecrã morto; confirmado ao vivo nesta ronda que o estado de erro não deixa a interface presa |
| Gravação: ação explícita, indicador ativo, ouvir/ver, repetir, eliminar antes de enviar | Implementado e verificado | **AET-RC01 achado 7**: botões "Enviar"/"Repetir" não desativavam durante o envio (risco de envio duplicado); corrigido, com proteção adicional contra duplo-clique |
| Parar streams ao terminar/sair | **Corrigido nesta ronda (AET-RC01 achado 7)** | A nota anterior ("Implementado e verificado") estava incorreta — `stream.getTracks().forEach(t => t.stop())` só corria no callback `onstop` de uma gravação terminada normalmente, nunca num `useEffect` de desmonte: sair de um passo a meio de uma gravação real deixava o microfone/câmara ativos. Corrigido com limpeza ao desmontar e uma guarda contra `getUserMedia()` resolver depois de a criança já ter saído do passo. **Limitação**: o ambiente desta sessão não tem microfone/câmara reais — a correção foi revista e teria passado nos testes automáticos do seu próprio efeito, mas a paragem real de um stream verdadeiro não foi observada ao vivo. |
| Recuperar resposta ao voltar a um passo | Implementado e verificado | **AET-RC01 achado 7** (novo, não existia antes): o estado de texto/gravação de um passo podia ficar preso ao passo anterior por falta de `key={step.id}`, e uma gravação/desenho já respondido não tinha pré-visualização nenhuma ao regressar (só decidia "já gravado" olhando para `value`, que é sempre `null` em respostas com ficheiro). Corrigido; confirmado ao vivo que voltar dois passos atrás recupera o texto certo em cada um. |
| Sem gravações em `localStorage` | Implementado e verificado | Tudo passa por upload para o servidor; nada persiste no browser |

## Módulo H — Avaliação e notas

| Requisito | Estado | Nota |
|---|---|---|
| Lista de respostas pendentes | Implementado e verificado | |
| Consulta de passos/respostas | Implementado e verificado | |
| Reprodução de gravações | Implementado e verificado | |
| Pontuação automática (objetivas) | Implementado e verificado | Escolha única/múltipla |
| Avaliação manual | Implementado e verificado | |
| Notas internas separadas do feedback partilhado | Implementado e verificado | Testado explicitamente (`ChildFeedbackTest`) que a nota interna nunca chega ao lado da criança |
| Autoria, datas, rastreabilidade de correção | Implementado e verificado | `supersedes_evaluation_id` liga avaliações sucessivas do mesmo attempt |

## Módulo I — Pontuação e conquistas

| Requisito | Estado | Nota |
|---|---|---|
| Separar desempenho de recompensa motivacional | Implementado e verificado | `RewardEvent` é independente da `score` |
| Participação/esforço reconhecidos independentemente do resultado | Implementado e verificado | |
| Sem rankings públicos | Implementado e verificado | Nenhum ecrã mostra dados de outra criança |
| Sem duplicação em reenvio | Implementado e verificado | `dedupe_key` único, testado via API e via portal |

## Módulo J — Acompanhamento

| Requisito | Estado | Nota |
|---|---|---|
| Histórico de atividades/resultados | Implementado e verificado | Por criança (`Children/Show`, com resumo numérico) e por atribuição (feedback da criança); sem dashboard de tendências — ver linha seguinte |
| Gráficos avançados / sinalização de resultados baixos | Em falta | Explicitamente marcado no master prompt como extensão opcional — não bloqueia a entrega base |
| Sem monitorização em direto/videochamada | Implementado e verificado | Nada disto existe |

## Módulo K — Notificações

| Requisito | Estado | Nota |
|---|---|---|
| Centro de notificações interno | Implementado e verificado | `NotificationController`, badge no cabeçalho do portal |
| Emails operacionais para adultos autorizados | Implementado e verificado | `NewAssignmentNotification`, `EvaluationAvailableNotification` — nunca incluem conteúdo clínico |
| Notificação interna de nova submissão | Implementado e verificado | `AttemptSubmittedNotification` (só base de dados, sem email) — adicionado nesta etapa |
| Pontos de integração para push futuro | Parcial | A tabela `notifications` do Laravel suporta múltiplos canais; não existe ainda um canal push configurado (natural, pois não há app móvel nesta etapa) |

## Segurança e privacidade

| Requisito | Estado | Nota |
|---|---|---|
| Validação de entrada no servidor | Implementado e verificado | `FormRequest`/`validate()` em todos os controllers de escrita |
| CSRF/XSS/rate limiting | Implementado e verificado | CSRF padrão Laravel; `throttle` nas rotas de autenticação/dispositivo |
| URLs temporárias para media privado | Implementado e verificado | |
| Verificação real de tipo de ficheiro | Implementado e verificado | `finfo`, não extensão nem `Content-Type` do browser |
| Nomes de ficheiro controlados | Implementado e verificado | Laravel gera nomes aleatórios no `store()` |
| Segredos em variáveis de ambiente | Implementado e verificado | |
| Auditoria com acesso restrito | Implementado e verificado | `AuditLogger` grava: login de equipa (incluindo via MFA), ativar/desativar conta, avaliação criada, MFA ativado/desativado, emissão/revogação de acesso de dispositivo, publicar/arquivar atividade, **edição de perfil de criança** (`child.profile_updated` — regista só se `care_notes` mudou, nunca o texto), **edição de conteúdo de atividade** (`activity.content_updated` — regista se bifurcou nova versão), associar/desassociar terapeuta, associar encarregado de educação, revogar sessão, exportar/eliminar dados de uma criança — todos testados. Nova página `Audit/Index` (rota `/audit`, `AuditEventPolicy::viewAny` restrito a `isAdmin()`, com filtro por ação e paginação) dá à equipa uma UI de consulta em vez de só a base de dados — confirmado ao vivo no browser: uma terapeuta (não-admin) recebe 403, um admin só vê eventos da própria organização, e um evento novo aparece imediatamente após a ação real. |
| Retenção configurável / exportação / eliminação | Parcial | `ChildDataService`: exportação completa (JSON, inclui notas clínicas — é o registo da própria clínica) e eliminação permanente admin-only com confirmação pelo nome, testada (cascata na base de dados + apagar ficheiro de media). **AET-RC01 achado 8**: a eliminação só encontrava media por referência *atual* em `step_responses` — uma gravação já substituída por uma nova (órfã, sem nenhuma referência) sobrevivia intacta a uma eliminação dita "completa". Corrigido: procura agora por propriedade real (`owner_child_profile_id`), e a ordem de limpeza também foi corrigida (base de dados confirmada antes de tocar no disco, nunca o contrário). **Ainda em falta**: política de retenção *configurável* (hoje é uma ação manual, não uma regra automática por prazo) — deliberadamente não implementada esta ronda: o prazo em si é uma decisão clínica ainda por tomar, e construir um apagamento automático antes disso é um risco, não uma correção. |
| Consentimentos com versão/autor/data | Implementado e verificado | `ConsentRecordController` + secção no perfil da criança; regista tipo/versão/autor/data, revogável; nenhum texto legal é escrito pelo sistema, só qual versão do texto da clínica foi usada |
| Acesso de dispositivo: ativação única, revogação imediata | Implementado e verificado | **AET-RC01 achados 2 e 3** (linhas não existiam nesta matriz antes desta ronda): um código de ativação podia ser reutilizado indefinidamente (achado 3, corrigido com verificação+consumo atómicos); revogar um dispositivo não tinha nenhum efeito em sessões/tokens já emitidos a partir dele — nem no portal web, nem na API, nem no acesso a media (achado 2, corrigido com um ponto único de verdade, `DeviceAssociation::resolveActiveFor()`, usado nos três sítios). Testado com percurso HTTP real, incluindo um dispositivo revogado perder acesso a media na próxima chamada mesmo com uma URL assinada ainda válida. |

## Acessibilidade

Ver `docs/visual-review.md` para o registo detalhado e as limitações
explícitas (sem leitor de ecrã real, sem ferramenta automática, sem Safari
nem dispositivo físico). Duas falhas reais de contraste AA foram
encontradas e corrigidas nesta etapa.

## Documentação e entrega

| Requisito | Estado |
|---|---|
| README (instalação/execução) | Implementado nesta etapa |
| `.env.example` sem segredos | Já existia, confirmado sem segredos reais |
| Docker Compose de desenvolvimento | Já existia (Postgres/Redis/Mailpit) |
| Arquitetura e decisões | `docs/progress.md` |
| Modelo de dados | `docs/progress.md` + migrações comentadas |
| OpenAPI da API móvel | `docs/openapi.yaml` |
| Mapa de permissões | `docs/permissions.md` |
| Guia de criação de atividades / substituição de conteúdo demonstrativo | `docs/content-guide.md` |
| Guia dos 4 layouts | `docs/visual-review.md` (capturas descritas) + este documento |
| Backup/reposição | `docs/production-checklist.md` |
| Checklist de preparação do servidor | `docs/production-checklist.md` |
| Relatório de testes | `docs/delivery-report.md` |
| Limitações e decisões pendentes da clínica | `docs/delivery-report.md` |
| CI (testes + build) | `.github/workflows/ci.yml` — SQLite rápido + suite completa contra PostgreSQL real + verificação TypeScript/build. Adicionado nesta ronda (AET-RC01); não inclui testes de browser automatizados (sem Dusk/Playwright configurado) |
| Relatório de remediação de auditoria externa | `docs/aet-rc01.md` (AET-RC01, esta ronda) |
