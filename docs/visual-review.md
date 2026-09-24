# Revisão visual e de acessibilidade — registo

Esta revisão foi feita **manualmente**, com o browser embutido do Claude Code
(motor Chromium via CDP) contra o servidor de desenvolvimento local, em
2026-09-24. Não é uma auditoria de acessibilidade certificada nem substitui
uma revisão por um profissional com deficiência ou uma ferramenta dedicada
(axe-core, WAVE, Lighthouse) — nenhuma dessas ferramentas está instalada
neste ambiente. O que se segue é o que foi realmente verificado, e o que
ficou por verificar.

## O que foi verificado

### Larguras de ecrã
- **360px** (largura mínima exigida): portal profissional (login) e
  experiência 3–6 anos (entrada + painel). Sem scroll horizontal, texto
  legível, sem sobreposição.
- **375×812** (mobile): experiência 3–6 anos (login e passo de atividade
  com escolha única), experiência 14–18 anos (lista de atividades).
- **768×1024** (tablet): ativação de dispositivo e painel de missões da
  experiência 7–13 anos.
- **1024×768** (desktop): portal profissional completo (painel, lista de
  crianças, perfil de criança, editor de atividades, fila de avaliação).

### Navegação por teclado
- Formulário de login: `Tab` avança corretamente Email → Palavra-passe →
  Manter sessão → Entrar → Esqueceu-se da palavra-passe, com anel de foco
  visível (cor de destaque, não apenas mudança subtil). O campo Email tem
  `autoFocus`, confirmado via `document.activeElement`.
- Não foi percorrido manualmente o teclado em todos os formulários (editor
  de atividades, avaliação) — ficou por verificar.

### Contraste de cor
Calculado programaticamente (fórmula de luminância relativa WCAG) para os
tokens de cada shell, não apenas "a olho":

| Combinação | Antes | Depois | AA (4.5:1 texto normal) |
|---|---|---|---|
| Profissional: accent `#0f6b5c` / branco | 6.41 | — | ✅ |
| Profissional: ink-muted `#5b665f` / bg | 5.62 | — | ✅ |
| **3–6: accent `#ee8b3c` / branco (texto do botão principal)** | **2.50** | **5.39** (`#a6540d`) | ❌ → ✅ |
| **7–13: accent `#1785a3` / branco** | **4.27** | **5.90** (`#0f6d87`) | ❌ (~) → ✅ |
| 14–18: accent `#4338ca` / branco | 7.90 | — | ✅ |

As duas falhas foram corrigidas em `resources/css/app.css` no mesmo commit
desta revisão — a mais grave era o botão principal (maior, mais visível) da
experiência de 3–6 anos, a mais nova e a que menos deveria depender de o
utilizador "adivinhar" o texto por baixo do contraste.

### Alvos de toque
Botão "Entrar" do portal profissional: 40px de altura — cumpre o mínimo AA
(24×24px, WCAG 2.2 SC 2.5.8). Os botões das três experiências infantis são
deliberadamente maiores (ver `EarlyLayout`/`MiddleLayout`/`TeenLayout`,
`py-5`/`py-6` em 3–6 anos) e não foram medidos individualmente, mas nenhum
é visualmente mais pequeno do que os do portal profissional.

### `prefers-reduced-motion`
Regra global em `resources/css/app.css` desativa/reduz todas as animações e
transições quando o sistema operativo pede movimento reduzido. Não foi
testado a emular essa preferência no browser desta sessão — ficou por
verificar visualmente (a regra CSS em si foi revista por leitura, não por
captura de ecrã com a preferência ativa).

### Alternativas de texto/áudio
- `SpeakButton` (Web Speech API) presente nos três ecrãs de execução de
  atividade, com repetição sem limite.
- Upload de conteúdos exige texto alternativo para imagem/áudio/vídeo
  (`MediaAssetController`), testado no Módulo D.
- Gravações de voz/vídeo mostram um indicador visual "A gravar…", não
  apenas um som.

## O que ficou por verificar (limitações explícitas)

- **Leitor de ecrã real** (VoiceOver, NVDA, TalkBack) — não usado. A
  estrutura semântica (`role`, `aria-label`, `role="radiogroup"` nas
  escolhas, `role="alert"` nos erros) foi escrita seguindo boas práticas,
  mas nunca ouvida.
- **Zoom do browser a 200%** — não testado.
- **Gestos complexos / apenas rato** — não há gestos multi-touch exigidos
  em lado nenhum (arrastar no desenho usa apenas toque/clique simples), mas
  não foi testado com um rato físico real, só com cliques simulados.
- **Safari e dispositivos reais** — tudo aqui foi testado no motor Chromium
  do browser embutido do Claude Code. **Não foi testado em Safari, iOS,
  Android real, nem em nenhum dispositivo físico.** Em particular, a
  gravação de voz/vídeo (`MediaRecorder`) tem suporte e formatos (`webm`)
  que variam entre navegadores — o código aceita `audio/mp4`/`video/mp4`
  como alternativa pensando no Safari, mas isto nunca foi confirmado num
  Safari real.
- **Ferramenta automática de acessibilidade** — nenhuma está instalada
  neste ambiente (sem axe-core, sem Lighthouse CI, sem Playwright). Os
  achados de contraste acima vieram de cálculo manual, não de uma
  ferramenta.

## Capturas

Capturas de ecrã representativas dos quatro layouts (profissional, 3–6,
7–13, 14–18) em várias larguras foram mostradas durante esta sessão de
desenvolvimento e ficam no histórico da conversa para revisão do
responsável do projeto. Não foram commitadas como ficheiros binários no
repositório para não o inchar — podem ser regeneradas a qualquer momento
correndo `composer run dev` e seguindo `README.md`.
