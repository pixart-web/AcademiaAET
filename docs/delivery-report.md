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

Ver `docs/requirements-matrix.md` para o detalhe módulo a módulo do
enunciado original.

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
→ 57 testes, 169 assertions, todos a passar (última execução nesta sessão)
```

16 ficheiros de teste, cobrindo:

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
- **Auditoria**: login de equipa, desativar/reativar conta, e
  emitir/revogar acesso de dispositivo escrevem mesmo um `audit_events`.
- Testes Breeze originais mantidos (autenticação, verificação de email,
  atualização de password/perfil).

### O que não foi testado automaticamente

- Editor de atividades (`Activities/Edit.tsx`) — sem teste de feature para
  o fluxo de criação/edição via HTTP, só a `ActivityVersioningService`
  subjacente.
- Biblioteca de conteúdos (upload de media) — sem teste de feature; a
  validação de mime real foi verificada manualmente.
- MFA — o fluxo de configuração (`MfaSettingsController`) não tem teste de
  feature, só a verificação de que o evento de auditoria é escrito.

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
