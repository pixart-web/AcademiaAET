# Academia AET — Portal Web — Estado do projeto

Última atualização: ver `git log`. Este ficheiro é um registo de progresso e decisões, não documentação de utilizador final (ver README.md para isso, quando existir).

## Decisões de arquitetura

- **Stack**: Laravel 13 (PHP 8.5) + Inertia + React 18 + TypeScript + Tailwind v3, conforme o master prompt. Vite 8 exigiu subir `@vitejs/plugin-react` para `^6` e `@types/node` para `^22` (conflito de peer deps do scaffold inicial do Breeze).
- **Base de dados**: PostgreSQL 16 via Docker Compose (`docker-compose.yml`), porta local `55432` (5432 já estava ocupada por outro processo na máquina de desenvolvimento). `.env.example` já aponta para isto.
- **Organization**: entidade única nesta fase (uma clínica), mas todo o modelo de dados está desde já particionado por `organization_id` e as Policies verificam-no — não é um SaaS multi-inquilino completo, mas está pronto para isolar dados se um dia for necessário.
- **Papéis**: `admin`, `professional`, `guardian` na tabela `users` (coluna `role`, enum PHP `App\Enums\UserRole`). Não se usa Spatie/permission — é um enum simples + Policies por recurso, autorizado sempre no servidor.
- **Criança/jovem**: `ChildProfile` **não** é um `User`. Implementa `Illuminate\Contracts\Auth\Authenticatable` diretamente e autentica-se através de um guard de sessão dedicado (`child`), sem email/password. Ver Módulo de acesso infantil abaixo.
- **Versionamento de atividades**: `Activity` → `ActivityVersion` (imutável assim que tem uma `Assignment`) → `ActivityStep`. Editar uma versão já atribuída cria sempre uma nova versão (`ActivityVersioningService`); a atribuição antiga mantém-se a apontar para a versão antiga. Testado em `tests/Feature/ActivityVersioningTest.php`.
- **Notas clínicas vs. feedback partilhado**: `ClinicalNote` é uma tabela própria, nunca carregada em nenhuma rota/página do lado da criança. `Evaluation.shared_feedback` é o único texto visível fora do portal profissional.
- **Recompensas**: `RewardEvent` com `dedupe_key` único por `(attempt, tipo)` — reenvio de submissão ou reavaliação nunca duplica pontos.
- **Auditoria**: tabela `audit_events` existe no esquema; ainda não há escrita automática a partir de todas as ações (ver "Por fazer").

## Acesso e segurança

- **Contas de equipa**: só por convite (`UserInvitationService`), sem registo público. O convite cria a conta com password aleatória inutilizável e dispara o fluxo padrão de "recuperar password" do Laravel como ativação.
- **MFA**: TOTP opcional para `admin`/`professional` (`pragmar x/google2fa`). O código QR é gerado **inteiramente no servidor** como SVG inline em base64 — o segredo nunca sai para um serviço de terceiros.
- **Acesso da criança**: `DeviceAssociation` liga um `ChildProfile` a um dispositivo físico.
  1. Um adulto autorizado gera um código de ativação + PIN de uso único (mostrados uma única vez).
  2. No dispositivo, essa dupla é trocada por um token de dispositivo de longa duração guardado num cookie HttpOnly — nunca num identificador visível.
  3. A partir daí, esse dispositivo só precisa do PIN curto para desbloquear — nunca é uma credencial válida sozinha pela internet.
  4. Bloqueio automático ao fim de 5 tentativas erradas (15 min), revogação manual pela equipa, expiração configurável.
  5. "Trocar" (botão no portal da criança) termina a sessão da criança sem tocar na associação do dispositivo nem expor dados a quem usar o tablet a seguir; login de equipa/criança fazem sempre logout do outro guard como defesa em profundidade.
- **Media privado**: qualquer imagem/áudio/vídeo/documento é servido apenas via `MediaStreamController`, atrás de URLs assinadas de curta duração (`temporarySignedRoute`), nunca por um disco público. Tipo de ficheiro validado pelo MIME real (`finfo`), não pela extensão.

## O que está funcional (verificado no browser contra Postgres real)

- Login de equipa (email/password) com redirecionamento por papel e desafio MFA quando ativo.
- CRUD de perfis de criança/jovem, associação de terapeutas e encarregados de educação (que recebem convite igual ao da equipa).
- Geração/revogação de acesso de dispositivo para a criança.
- Biblioteca e editor de atividades por passos, com os 6 tipos de resposta do enunciado; publicar/arquivar.
- Atribuição de atividade publicada a uma criança.
- Execução do lado da criança: entrada por dispositivo+PIN, uma tarefa de cada vez, gravação de voz/vídeo real via `MediaRecorder`, desenho em `<canvas>`, escolha única/múltipla com correção automática, texto curto, confirmação de tarefa — submissão idempotente.
- Fila de avaliação: pontuação automática nas escolhas, pontuação manual, feedback partilhado separado de nota interna, notificação por email ao encarregado de educação (sem conteúdo clínico).
- Recompensas por participação/esforço/mediante pontuação, sem duplicação.
- Percurso ponta-a-ponta testado manualmente: atribuir → criança realiza (incluindo os 3 tipos de resposta acima) → submeter → avaliar → recompensas geradas (visto via tinker).

## Testes automáticos (31 a passar, `php artisan test`)

Cobrem os riscos mais críticos do enunciado: isolamento entre crianças, isolamento entre terapeutas não atribuídas, notas clínicas nunca expostas à criança, imutabilidade de versão após atribuição, não duplicação de recompensas em resubmissão, ativação/PIN de dispositivo (incluindo dispositivo revogado).

**Por fazer**: cobertura de Policies para media/staff, testes de acessibilidade automatizados, testes Playwright end-to-end no browser real (só foi verificado manualmente nesta sessão).

## Por fazer (ordem sugerida)

1. **Diferenciação real dos 3 grupos etários** — hoje os três shells partilham o mesmo layout/fluxo de execução, diferindo apenas em paleta/raio via `data-shell`. Falta: layout de tarefa única sem menu para 3–6 anos com leitura de instruções em áudio; sensação de progresso/missão para 7–13; cromo minimalista e linguagem direta para 14–18.
2. **Feedback do lado da criança** — a criança ainda não tem uma página para ver `shared_feedback`/recompensas depois de avaliada.
3. **Portal do encarregado de educação** — só existe uma página placeholder (`Guardian/Home.tsx`), conforme combinado no enunciado ("não obrigatório nesta etapa").
4. **API móvel `/api/v1` + OpenAPI** — ainda não iniciada (Etapa 7 do enunciado).
5. **Acessibilidade**: falta auditoria WCAG 2.2 AA formal (navegação por teclado, foco visível, contraste, `prefers-reduced-motion` já tratado globalmente em `resources/css/app.css`).
6. **Exportação/eliminação de dados, retenção configurável** — modelo de dados suporta (soft deletes, audit_events), mas não há UI/fluxo administrativo ainda.
7. **Documentação de entrega**: README de instalação, `.env.example` (já existe), guia dos 4 layouts, plano de substituição de conteúdo demonstrativo, checklist de produção.
8. **Registo de auditoria automático** — tabela existe, escrita ainda não está ligada a todas as ações sensíveis.

## Ambiente de desenvolvimento

```bash
docker-compose up -d pgsql redis mailpit
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # cria dados de demonstração (is_demo=true) — nunca corre em produção sem APP_ALLOW_DEMO_SEEDING=true
composer run dev             # servidor Laravel + Vite + fila + logs
```

Login de demonstração: `admin@academia-aet.test` / `terapeuta@academia-aet.test`, password `password`.
