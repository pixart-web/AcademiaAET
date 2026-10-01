# Relatório de entrega — Portal Web Academia AET

Checkpoint para auditoria independente do responsável do projeto. **Não é
uma aprovação clínica, legal, ou de produção** — é o estado real do código
nesta data, com o que foi verificado e o que não foi, sem arredondar para
cima.

## O que está funcional

Percurso completo verificado manualmente no browser e coberto por testes
automáticos: **atribuir** uma atividade publicada a uma criança →
**entrar** no dispositivo da criança (código+PIN → PIN curto) →
**realizar** (com pelo menos um passo de cada tipo testado: escolha única
com pontuação automática, texto curto, gravação de voz) → **guardar e
retomar** (uma segunda chamada a "iniciar" reutiliza a tentativa em
curso, não cria uma nova) → **submeter** (idempotente) → **avaliar** (nota
interna separada do feedback partilhado, pontuação automática nas
escolhas) → **consultar feedback** do lado da criança (estado neutro
enquanto não avaliado, mensagem honesta quando não há feedback escrito,
nunca inventado).

Desde o checkpoint anterior, numa segunda ronda de trabalho não-stop
(mesma sessão, commits `9d6350d`…`03aa2d7`): pré-visualização de uma
atividade nos três layouts infantis, duplicar atividade, indicador de
atraso visível, registo de consentimentos (grant/revoke), exportação e
eliminação permanente de dados de uma criança (com cascata e limpeza de
ficheiros verificadas por teste), lista de sessões ativas com revogação,
resumo numérico no perfil da criança, e cobertura de auditoria alargada.
Também se corrigiu um **bug crítico real**: `MediaAssetPolicy` não tinha
`viewAny()`, pelo que a biblioteca de conteúdos devolvia 403 a todos os
utilizadores — só descoberto ao carregar mesmo a página no browser, não
por leitura de código, o que motivou escrever também o primeiro teste de
feature para esse controller (não existia nenhum).

Numa terceira ronda (commits `9d6350d`…`a3c89a8`): mais um **bug crítico
real e mais grave**, encontrado da mesma forma (a escrever um teste, não a
ler código) — `User`, `Assignment` e `DeviceAssociation` não tinham
`disabled_at`/`mfa_enabled`/`mfa_secret`/`cancelled_at`/`revoked_at`/
`revoked_by_user_id` na lista `$fillable`. O botão "Desativar" uma conta,
ativar/desativar MFA, cancelar uma atribuição e revogar um dispositivo
pareciam funcionar (redirecionamento, mensagem de sucesso, e nalguns casos
até o evento de auditoria) mas a **base de dados nunca mudava**. Confirmado
ao vivo no browser: desativei a conta da terapeuta de demonstração,
verifiquei por `tinker` que `disabled_at` continuava `null` antes da
correção e passou a ter data depois. Corrigido; testes reforçados para
verificar o valor real do campo, não só o redirecionamento.

Repeti também o percurso completo (atribuir → entrar → responder aos 3
tipos de passo da atividade de demonstração → guardar/retomar após uma
interrupção de sessão a meio → submeter → avaliar → consultar feedback)
na experiência de **14–18 anos**, que só tinha sido verificada
parcialmente antes — incluindo confirmar ao vivo que uma criança recebe
403 ao tentar aceder ao feedback de outra (não só por teste automático).

Numa quarta ronda (commits `c27fd06`…atual): confirmado que a recuperação
de acesso (Breeze) já estava coberta por teste — só mal classificada na
matriz. Limites de upload deixaram de estar fixos no código, agora vêm de
`config/media.php` (`MEDIA_MAX_*_KB`). A auditoria passou a cobrir também
a edição de perfil de criança e a edição de conteúdo de atividade (as duas
lacunas explícitas apontadas na ronda anterior), e ganhou uma página de
consulta própria (`/audit`, só para administradores, com filtro e
paginação) em vez de só existir na base de dados — confirmado ao vivo no
browser: uma terapeuta recebe 403, um admin só vê eventos da própria
organização, e uma edição real aparece na lista de imediato sem expor o
texto da nota clínica alterada. **Decisão deliberada de não avançar**: uma
regra automática de retenção de dados por prazo não foi implementada,
mesmo como mecanismo desligado por omissão — ao contrário do limite de
upload, aqui o próprio prazo é uma decisão clínica ainda por tomar, e
construir agora um apagamento automático (ainda que configurável) numa
aplicação que guarda notas clínicas de crianças é um risco real se alguém
o ativar sem essa decisão estar fechada. Ver `docs/requirements-matrix.md`
para o detalhe módulo a módulo do enunciado original.

## AET-RC01 — correções da auditoria externa (ronda mais recente)

Uma auditoria externa independente ao código (commit `fc8c842`) encontrou
8 problemas reais, nenhum deles apanhado pelas rondas anteriores: media
clínica de uma criança listada na biblioteca didática e reproduzível por
uma profissional sem ligação ao caso; revogar um dispositivo sem efeito
em sessões/tokens já emitidos; um código de ativação reutilizável;
`startOrResume()` a impedir retomar uma tentativa só por já ter atingido
o limite (verificava o limite antes de procurar a tentativa em curso);
instruções multimédia sem tipo explícito (e sem nenhum caso para vídeo);
a resposta certa (`response_config.correct`) enviada tal e qual para o
portal da criança, e submissão aceitando passos obrigatórios por
responder; estado de texto/gravação a misturar-se entre passos
consecutivos; e gravações substituídas a ficarem órfãs (nunca limpas,
nem numa eliminação "completa" da criança).

Os 8 foram reproduzidos por leitura direta do código antes de qualquer
alteração (nunca assumidos), corrigidos, e verificados — cada um com
teste de regressão automático e, nos casos com superfície de UI, também
ao vivo no browser. Detalhe completo achado a achado — reprodução,
causa, correção, teste, resultado obtido — em `docs/aet-rc01.md`; linhas
tocadas em `docs/requirements-matrix.md` foram atualizadas para
referenciar essa auditoria.

Branch `claude/aet-rc01-remediation`, ainda não integrada em `main` —
ver secção "Repositório" abaixo para o SHA exato e o estado do PR.
Acrescentado nesta ronda: CI (`.github/workflows/ci.yml`) com a suite
completa a correr contra SQLite (rápida) **e** contra PostgreSQL real
(incluindo um teste de concorrência genuína, com duas ligações
independentes à base de dados, uma delas num processo forkado), mais
verificação TypeScript e build — nenhum destes existia antes desta
ronda.

## Repositório

`https://github.com/pixart-web/AcademiaAET`. Trabalho anterior (checkpoints
1 a 4 acima) está em `main`. A ronda AET-RC01 está na branch
`claude/aet-rc01-remediation` — ver o relatório final dessa ronda para o
SHA inicial/final exatos e o estado do pull request. Histórico coerente
por etapa (ver `git log`); sem segredos nem dados reais commitados
(`.env` no `.gitignore`, só `.env.example` versionado).

## Como iniciar o ambiente

Ver README.md, secção "Ambiente de desenvolvimento". Resumo:

```bash
docker-compose up -d pgsql redis mailpit
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev
```

## Contas de demonstração

**Só existem em ambiente local** (`APP_ENV=local`/`testing`, ou
`APP_ALLOW_DEMO_SEEDING=true` explícito) — `DemoDataSeeder` recusa-se a
correr em produção. Ver README.md para as credenciais.

## Testes realmente executados

```
php artisan test
→ 143 testes, 518 assertions, todos a passar (SQLite, última execução na branch AET-RC01)

vendor/bin/phpunit -c phpunit.pgsql.xml
→ os mesmos 143 testes, 518 assertions, todos a passar contra PostgreSQL real
```

38 ficheiros de teste, cobrindo (além do já listado antes da ronda AET-RC01):

- **Isolamento de media clínica** (achado 1): terapeuta sem associação ao
  caso não lista nem reproduz a gravação de uma criança; terapeuta
  associada e admin conseguem; outra organização é rejeitada; a gravação
  nunca aparece na biblioteca didática; um id de gravação clínica não
  pode ser usado como conteúdo de instrução via id manual.
- **Revogação/ativação de dispositivo** (achados 2 e 3): revogar termina a
  sessão/token no pedido seguinte, sem afetar outro dispositivo da mesma
  criança nem exigir logout explícito; reativar um código já usado é
  sempre rejeitado, com a mesma mensagem genérica de "código já usado" ou
  "nunca existiu"; duas ativações concorrentes só deixam uma vencer.
- **Retoma e cancelamento de tentativas** (achado 4): `max_attempts=1`
  permite sair e retomar sem criar segunda tentativa; cancelar bloqueia
  gravar/submeter; concorrência genuína (duas ligações Postgres, uma num
  processo forkado) prova que o bloqueio de linha serializa corretamente.
- **Validação e pontuação** (achado 6): a resposta certa nunca aparece no
  payload da criança; os 4 tipos sem ficheiro são validados contra o
  conjunto de opções do próprio passo; escolha múltipla pontua por
  conjunto, não por ordem; submissão com passo obrigatório por responder
  é rejeitada com mensagem clara.
- **Propriedade e limpeza de ficheiros** (achado 8): regravar um passo
  retira a media anterior (soft-delete + ficheiro apagado); eliminação de
  uma criança remove também gravações já substituídas que nada referencia
  atualmente, sem nunca tocar noutra criança ou conteúdo partilhado.

29 ficheiros de teste anteriores a esta ronda (9 novos nesta ronda —
`ActivityMediaInstructionsTest`, `AttemptResumeAndCancellationTest`,
`ClinicalMediaIsolationTest`, `Concurrency/AttemptConcurrencyPostgresTest`,
`DeviceRevocationPropagationTest`, `MediaOwnershipAndCleanupTest`,
`MediaReconcileOrphansCommandTest`, `StepResponseValidationTest`,
`StepStateSerializationTest`), cobrindo:

- **Isolamento**: uma terapeuta não atribuída não vê o perfil/avaliação de
  uma criança; uma criança não acede a tentativas/feedback doutra criança
  (web e API); um token de API de um tipo (staff/child) não alcança
  endpoints do outro tipo.
- **Notas clínicas**: nunca aparecem em nenhuma resposta do lado da
  criança, testado explicitamente com uma string única a procurar na
  resposta HTTP inteira.
- **Versionamento**: editar uma versão sem atribuições altera-a no
  próprio lugar; editar uma versão já atribuída cria uma nova, mantendo a
  atribuição antiga a apontar para a versão antiga.
- **Idempotência**: submeter a mesma tentativa duas vezes não duplica
  recompensas (testado via portal e via API).
- **Dispositivo**: ativação com PIN errado falha e não autentica;
  dispositivo revogado não consegue ativar; PIN errado três vezes leva a
  bloqueio (lógica testada na unidade do serviço, ver
  `DeviceAuthService`); desbloqueio sem o código original de ativação
  funciona via API.
- **Auditoria**: login de equipa (incluindo com MFA), desativar/reativar
  conta, e emitir/revogar acesso de dispositivo escrevem mesmo um
  `audit_events` — e, mais importante depois do bug encontrado nesta
  ronda, o **campo real** (`disabled_at`, `mfa_enabled`, `cancelled_at`,
  `revoked_at`) muda mesmo na base de dados, não só o evento.
- **Editor de atividades**: criar/editar via HTTP, incluindo que editar
  uma atividade já atribuída bifurca uma nova versão e que uma terapeuta
  sem ligação à atividade não a pode editar.
- **MFA**: percurso completo — pedir configuração, código errado
  rejeitado, código certo ativa, login passa a exigir o desafio, desafio
  aceita o código certo e autentica, desativar volta a permitir login
  direto.
- Testes Breeze originais mantidos (autenticação, verificação de email,
  atualização de password/perfil).

### O que não foi testado automaticamente

- Categorias/áreas de atividade continuam texto livre — não há UI de
  configuração para testar. Limites de upload deixaram de estar fixos no
  código: agora vêm de `config/media.php` (`MEDIA_MAX_*_KB` no `.env`),
  com teste a confirmar que baixar o limite por configuração é respeitado
  — mas continua sem UI de administração, só variável de ambiente.
- Não foi feita uma segunda auditoria de `->update()`/`::create()` para
  garantir que nenhum novo campo ficou de fora de `$fillable` desde esta
  correção — vale a pena repetir sempre que um campo novo for adicionado
  a um modelo existente.
- Foi feita uma auditoria de seguimento às comparações `->where()`/
  `->whereIn()` contra `status`/`role`/`kind`/`type` em toda a app, à
  procura da mesma classe de bug do `care_notes` (comparação de enum já
  carregado em `Collection` contra string simples). Não encontrou mais
  nenhuma instância real — detalhe em `docs/requirements-matrix.md`.

## Browsers e dispositivos realmente testados

**Só** o motor Chromium do browser embutido do Claude Code (Chrome/152),
em modos de emulação de largura (360px, 375px, 768px, 1024px+). **Nunca**
testado em:
- Safari (desktop ou iOS) — relevante porque `MediaRecorder` tem suporte e
  formatos diferentes entre motores; o código tenta `audio/mp4`/`video/mp4`
  como alternativa pensando no Safari, mas isto nunca foi confirmado.
- Android real.
- Nenhum dispositivo físico (tablet ou telemóvel).
- Nenhum leitor de ecrã (VoiceOver, NVDA, TalkBack).
- Nenhum microfone/câmara reais — o browser embutido usado nesta e nas
  rondas anteriores não tem acesso a dispositivos de captura. A correção
  de limpeza de `MediaRecorder`/`getUserMedia` da ronda AET-RC01 (achado
  7) foi revista e o estado de erro de permissão recusada foi confirmado
  ao vivo, mas uma gravação real de fim a fim (pedir permissão → gravar →
  pré-visualizar → enviar) nunca foi observada a correr neste ambiente.

Ver `docs/visual-review.md` para o detalhe completo do que foi e não foi
verificado em acessibilidade.

## Capturas dos quatro layouts

Capturadas e inspecionadas durante esta sessão de desenvolvimento (ver
histórico da conversa) nas larguras 360/375/768/1024px:
portal profissional (login, painel, lista de crianças, perfil de criança,
editor de atividades), experiência 3–6 anos (entrada, tarefa única),
experiência 7–13 anos (entrada, missões), experiência 14–18 anos (lista de
atividades). Não foram commitadas como ficheiros binários no repositório —
podem ser regeneradas a qualquer momento seguindo o README.

## O que está preparado para Android e iOS

- API REST versionada (`/api/v1`) com autenticação por token (Sanctum),
  documentada em `docs/openapi.yaml`, usando exatamente os mesmos serviços
  de negócio que o portal web (`AttemptService`, `DeviceAuthService`,
  `ChildFeedbackService`, `MfaService`).
- Fluxo de emparelhamento de dispositivo pensado desde o início para uma
  app nativa guardar `device_id`+`device_token` em armazenamento seguro
  (Keychain/Keystore) em vez de um cookie.
- **Não existe nenhuma app Flutter/nativa** — não foi pedido nesta etapa e
  não foi criada.

## O que falta verificar antes de usar dados reais

Ver `docs/production-checklist.md` (lista completa) — os pontos mais
importantes:
- Confirmar em produção real que `APP_ALLOW_DEMO_SEEDING` está ausente.
- Decidir e configurar uma política de backup que inclua o disco de media
  privado, não só a base de dados.
- Testar reposição (restore) pelo menos uma vez antes de depender dela.
- A auditoria (`audit_events`) cobre um conjunto inicial de ações
  sensíveis, não é exaustiva — decidir com a clínica se falta cobrir mais
  alguma ação antes de produção.

## Decisões e conteúdos pendentes da clínica

- **Identidade visual definitiva** — logótipo, paleta e mascote atuais são
  provisórios, criados para este protótipo.
- **Textos legais de consentimento** — a tabela `consent_records` está
  pronta para os guardar com versão/autor/data, mas nenhum texto legal foi
  escrito; não inventámos nenhum.
- **Conteúdo terapêutico real** — todas as atividades atuais são exemplos
  fictícios explicitamente marcados como demonstração; substituição
  descrita em `docs/content-guide.md`.
- **Política de retenção de dados** — quanto tempo guardar cada tipo de
  registo (respostas, gravações, notas) é uma decisão da clínica, ainda
  por tomar.
- **Portal do encarregado de educação** — combinado como fora de âmbito
  nesta etapa; o modelo de dados (`GuardianRelationship`, notificações por
  email) já está pronto para quando for decidido avançar.
- **Categorias/áreas de atividade configuráveis** — atualmente texto
  livre; a clínica pode querer uma lista fechada no futuro.

## Não declaramos

Aprovação de produção, conformidade RGPD certificada, certificação de
acessibilidade, nem compatibilidade testada com nenhum browser ou
dispositivo além do indicado acima.
