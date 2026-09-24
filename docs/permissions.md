# Mapa de permissões

Todas as autorizações são verificadas no servidor (Policies em
`app/Policies/`, aplicadas via `$this->authorize()` nos controllers) — a
interface nunca é a única barreira. Esta página descreve o resultado; para
a lógica exata, ver o ficheiro de Policy correspondente.

## Papéis

| Papel | Onde vive | Autentica-se com |
|---|---|---|
| `admin` | `users.role` | Email + password (+ MFA opcional) |
| `professional` | `users.role` | Email + password (+ MFA opcional) |
| `guardian` | `users.role` | Email + password (portal próprio fora de âmbito nesta etapa — ver `docs/progress.md`) |
| Criança/jovem | `ChildProfile` (não é um `User`) | Dispositivo emparelhado (código+PIN único → PIN curto) |

## Matriz por recurso

| Recurso | Admin | Terapeuta | Terapeuta (não atribuída) | Criança | Encarregado de educação |
|---|---|---|---|---|---|
| Gerir contas de equipa (`staff.*`) | ✅ | ❌ | ❌ | ❌ | ❌ |
| Ver/criar perfil de criança | ✅ (toda a organização) | ✅ (criar); ver só se atribuída | ❌ | ❌ | ❌ |
| Editar perfil de criança | ✅ | ❌ (só admin) | ❌ | ❌ | ❌ |
| Dados clínicos da criança (notas, atribuições, avaliações, dispositivos) | ✅ | ✅ **apenas se `ProfessionalAssignment` ativa** | ❌ (403) | ❌ | ❌ |
| Nota clínica interna | ✅ | ✅ (se atribuída) | ❌ | **nunca** (nem via API) | **nunca** |
| Feedback partilhado (`shared_feedback`) | ✅ escreve | ✅ escreve (se atribuída) | ❌ | ✅ lê o seu próprio | ❌ (sem portal ainda) |
| Biblioteca de atividades | ✅ | ✅ (criar/editar as próprias; admin edita todas) | ✅ (só leitura) | ❌ | ❌ |
| Publicar/arquivar atividade | ✅ | ✅ (autora ou admin) | ❌ | ❌ | ❌ |
| Biblioteca de conteúdos (media) | ✅ | ✅ | ✅ (só leitura da própria organização) | ❌ (só o media autorizado de uma atividade atribuída) | ❌ |
| Gerar/revogar acesso de dispositivo | ✅ | ✅ (se atribuída) | ❌ | — | ❌ |
| Executar a própria atividade | — | — | — | ✅ (só as suas atribuições) | — |
| Ver notas internas doutra criança | ❌ (fora da sua organização) | ❌ | ❌ | ❌ | ❌ |

## Isolamento entre organizações

Toda a query relevante filtra por `organization_id` (direto ou via
relação). Uma conta de uma organização nunca vê dados de outra — não há
ainda mais do que uma organização em produção real, mas o modelo já está
preparado e testado para isso.

## Onde isto está implementado

- `app/Policies/ChildProfilePolicy.php` — a peça central; `manageClinicalData()`
  é o que decide se uma terapeuta pode agir sobre uma criança.
- `app/Policies/ActivityPolicy.php`, `AssignmentPolicy.php`,
  `AttemptPolicy.php`, `ClinicalNotePolicy.php`, `MediaAssetPolicy.php`,
  `UserPolicy.php`, `DeviceAssociationPolicy.php`.
- `app/Http/Middleware/EnsureUserHasRole.php` (`role:admin,professional` nas
  rotas do portal profissional).
- `app/Http/Middleware/EnsureApiPrincipal.php` (equivalente na API: um
  token nunca alcança o lado errado, `staff` vs. `child`).
- Guardas de sessão distintos ao nível do Laravel (`web` vs. `child`) —
  nunca coexistem na mesma sessão (ver `docs/progress.md`).

## Testado (ver `tests/Feature/`)

`ChildIsolationTest`, `AttemptSubmissionTest`, `ChildFeedbackTest`,
`Api/ApiAuthTest` cobrem: terapeuta não atribuída não vê perfil/avaliação;
criança não acede a tentativa/feedback doutra criança; token de um tipo não
alcança endpoints do outro; nota clínica nunca aparece numa resposta do
lado da criança (nem Inertia nem API).
