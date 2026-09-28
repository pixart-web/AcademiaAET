# Matriz de requisitos — Master Prompt Academia AET

Estados: **Implementado e verificado** (testado por teste automático e/ou no
browser nesta sessão) · **Implementado, por verificar** (código escrito,
sem teste automático nem verificação manual direta) · **Parcial** ·
**Em falta** · **Opcional/fora desta etapa** (combinado explicitamente no
master prompt).

## Módulo A — Autenticação e contas

| Requisito | Estado | Nota |
|---|---|---|
| Login/logout equipa | Implementado e verificado | `AuthenticatedSessionController`, testado |
| Recuperação de acesso | Implementado, por verificar | Rotas Breeze padrão (`password.request`/`reset`); sem teste próprio escrito nesta fase (os testes originais do Breeze para isto foram mantidos) |
| Convites (sem registo público) | Implementado e verificado | `UserInvitationService`; registo público removido propositadamente |
| Ativação/desativação de contas | Implementado e verificado | `UserController@destroy/reactivate`, `EnsureAccountIsActive` |
| MFA para contas profissionais | Implementado e verificado | TOTP, QR gerado no servidor (nunca enviado a terceiros); ligado à navegação em `Profile/Edit` |
| Gestão/revogação de sessões | Parcial | Logout revoga a sessão atual; não existe uma lista "sessões ativas" para o utilizador revogar sessões noutros dispositivos |
| Sem credenciais fixas em produção | Implementado e verificado | `DemoDataSeeder` recusa-se a correr fora de local/testing sem `APP_ALLOW_DEMO_SEEDING=true` |

## Módulo B — Gestão de crianças e jovens

| Requisito | Estado | Nota |
|---|---|---|
| Perfil individual, dados mínimos | Implementado e verificado | Sem morada/NIF; `care_notes` oculto por omissão da API pública do modelo |
| Profissional responsável | Implementado e verificado | `ProfessionalAssignment` |
| Relações autorizadas (encarregados) | Implementado e verificado | `GuardianRelationship`, convite igual ao da equipa |
| Experiência visual por perfil | Implementado e verificado | Sugerida por idade na criação, sempre editável pela profissional, nunca muda sozinha no aniversário |
| Áreas separadas: histórico/resultados/notas | Parcial | Histórico e notas existem e estão corretamente isolados (ver Segurança); não há um ecrã dedicado de "resultados agregados" para a profissional além da lista de atribuições |

## Módulo C — Biblioteca de conteúdos

| Requisito | Estado | Nota |
|---|---|---|
| Upload imagem/áudio/vídeo/documento | Implementado e verificado | `MediaAssetController`, tipo real por `finfo`, não extensão |
| Metadados (título, descrição, alt, transcrição) | Implementado e verificado | Alt/transcrição obrigatórios para imagem/áudio/vídeo |
| Pré-visualização e arquivo | Parcial | Arquivar existe; pré-visualização na biblioteca é só o título/tipo, sem thumbnail/player inline na listagem |
| Limites configuráveis | Parcial | Limites de tamanho por tipo estão fixos no código (`MediaAssetController::MAX_SIZE_KB`), não configuráveis via UI |
| Armazenamento privado | Implementado e verificado | Disco `local` sem `serve`, só acessível via `MediaStreamController` com URL assinada de curta duração |

## Módulo D — Biblioteca e editor de atividades

| Requisito | Estado | Nota |
|---|---|---|
| Estados rascunho/publicada/arquivada | Implementado e verificado | |
| Duplicação | Implementado e verificado | `ActivityController@duplicate`, cria rascunho novo, nunca uma versão da original |
| Categorias/áreas configuráveis | Parcial | Campos de texto livre, não uma lista configurável pela clínica |
| Dificuldade, instruções, critérios | Implementado e verificado | |
| Pré-visualização nos 3 layouts | Implementado e verificado | `Activities/Preview.tsx` — interativo, nunca persiste (sem `Attempt` criado, testado) |
| Editor por passos (sem drag-and-drop) | Implementado e verificado | `Activities/Edit.tsx` |
| **7 tipos de resposta** | **Implementado e verificado** | Ver nota abaixo — o relatório anterior mencionava "6 tipos" por erro de contagem, não por funcionalidade em falta |
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
| Cancelamento | Implementado e verificado | Testado |

## Módulo G — Execução infantil

| Requisito | Estado | Nota |
|---|---|---|
| Mostrar atividades do próprio perfil | Implementado e verificado | |
| Reproduzir instrução | Implementado e verificado | Media do professor + `SpeakButton` (síntese de voz) |
| Responder, guardar progresso, retomar | Implementado e verificado | Testado (`AttemptExecutionTest`) |
| Submeter com confirmação | Implementado e verificado | |
| Falha de rede/sessão expirada/upload | Implementado e verificado | Erros surfaced na UI (não silenciosos); ver commit de tratamento de falhas de upload |
| Permissão de câmara/microfone recusada | Implementado e verificado | Mensagem clara em `RecordingInput`, sem ecrã morto |
| Gravação: ação explícita, indicador ativo, ouvir/ver, repetir, eliminar antes de enviar | Implementado e verificado | |
| Parar streams ao terminar/sair | Implementado e verificado | `stream.getTracks().forEach(t => t.stop())` |
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
| Histórico de atividades/resultados | Parcial | Existe por criança (`Children/Show`) e por atribuição (feedback da criança); não há um dashboard de tendências/indicadores agregados |
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
| Auditoria com acesso restrito | Implementado, por verificar | `AuditLogger` grava login de equipa, ativar/desativar conta, avaliação criada, MFA ativado/desativado, e emissão/revogação de acesso de dispositivo (testado). **Não** cobre ainda toda a ação sensível possível (ex.: edição de perfil de criança, criação/edição de atividade, associação de encarregado de educação) — é um conjunto inicial, não exaustivo. Sem UI de consulta dos eventos ainda. |
| Retenção configurável / exportação / eliminação | Em falta | Soft deletes existem (permitem reposição), mas não há UI administrativa de exportação/eliminação nem política de retenção configurável |
| Consentimentos com versão/autor/data | Implementado, por verificar | Tabela `consent_records` existe no esquema com estes campos; não há ainda UI para os criar/consultar |

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
