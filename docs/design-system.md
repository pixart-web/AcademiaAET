# Sistema visual — Academia AET

Direção: proximidade, serenidade, descoberta e cuidado. Fundos marfim/creme,
verde profundo nas ações, sálvia/menta de apoio, pêssego/damasco em
destaques, cantos moderados, sombras discretas, ilustração de natureza.
Referência aprovada pelo responsável do projeto (imagem de conceito); os
elementos de apresentação da imagem (portátil, molduras, títulos de faixas
etárias) não fazem parte da interface.

## Camadas de tokens (`resources/css/app.css`)
1. `--aet-*` — paleta e escalas brutas (espelhadas em `design/tokens.json`
   para Flutter).
2. `--color-*` — papéis semânticos que todos os componentes leem
   (`bg`, `bg-alt`, `surface`, `ink`, `ink-muted`, `border`, `accent`,
   `accent-soft`, `highlight`, `success/warning/danger/info` + `-soft`).
3. `[data-shell]` — sobrepõe só a camada 2 por experiência:
   `professional`, `early` (3–6), `middle` (7–13), `teen` (14–18).

Tailwind (`tailwind.config.js`) mapeia os papéis para classes
(`bg-accent`, `text-ink-muted`, `bg-highlight`, `font-display`, `shadow-soft`…)
e, via `color-mix`, mantém os modificadores de opacidade (`bg-surface/90`)
a funcionar com variáveis CSS.

| Token | Valor |
|---|---|
| Marfim / Creme | `#FAF7EF` / `#F2EBDD` |
| Verde profundo (ação) | `#174E45` |
| Sálvia / Menta | `#8FA994` / `#E6EFE6` |
| Pêssego / Damasco | `#F4C6A3` / `#ECAF76` |
| Texto / secundário | `#193B35` / `#5A6B64` |
| Borda | `#E5E5DA` |
| Funcionais | sucesso `#2C6E3F`, aviso `#8A5300`, erro `#A8321F`, info `#1F5D85` — distintas da paleta de marca |

Os pares usados em texto foram escolhidos para ≥ 4.5:1 (ex.: texto secundário
`#5A6B64` sobre marfim; botão 3–6 `#2C7352` com branco ≈ 5.4:1). Não foi corrida
uma ferramenta automática de contraste (ver `docs/visual-review.md`).

## Tipografia (fontes locais, SIL OFL 1.1, sem pedidos externos)
- **Fraunces** (serifada) — só em títulos selecionados do portal profissional e
  da experiência 14–18 (`font-display`).
- **Nunito Sans** — texto e controlos do portal e dos 14–18.
- **Nunito** (arredondada) — experiências 3–6 e 7–13.

## Espaço, raios, sombras, movimento, controlos
Escala de 4px; raios 8/12/16/24/pílula (portal 12, 7–13 16, 3–6 24);
sombras `soft`/`lift`; movimento 120/200 ms com `cubic-bezier(.2,.7,.2,1)`,
entrada curta `aet-rise`, tudo reduzido por `prefers-reduced-motion`; altura de
controlos 40/52/72px (3–6 usa os maiores). Foco visível: contorno 3px na cor de
ação em todos os elementos interativos.

## Componentes
`Components/Icon` (ícones), `art/Logo`, `art/Squirrel` + `Mascot`,
`art/Scenes` (bosque, paisagem), `art/Botanicals`, `art/Avatar`,
`Child/StepInput` (os 7 tipos de resposta, partilhado pelos 3 shells),
`Child/SpeakButton` (variante `pill` e `big`; só aparece se existir síntese de
voz), layouts `ProfessionalLayout`, `Early|Middle|Teen/Layout`.

## As quatro experiências
- **Portal profissional** — barra lateral compacta com ícones (Visão geral,
  Crianças e jovens, Atividades, Avaliações, Conteúdos; Equipa e Auditoria só
  para administradores), cabeçalho com pesquisa **funcional** (`/pesquisa`,
  limitada aos casos que a pessoa pode abrir e às atividades da organização),
  notificações e perfil; "Bom dia/tarde/noite, {nome}", ação "Criar atividade",
  respostas por avaliar e atividades em curso com **progresso real** (passos
  respondidos / passos da versão). Menu em gaveta em ecrãs < 1024px.
- **3–6** — faixa de bosque, esquilo, **uma tarefa por ecrã** num cartão limpo,
  botão grande "Ouvir" (só se houver síntese de voz), escolhas grandes,
  "Continuar". Sem navegação; "Sair" só para o adulto.
- **7–13** — paisagem com ribeira e casa na árvore, saudação, progresso real
  (concluídas / total), missão seguinte, "Começar missão", conquistas reais,
  concluídas. A paisagem encolhe durante a execução para nunca esconder a tarefa.
- **14–18** — "O teu espaço": claro, serifa nos títulos, sem mascote; banner de
  feedback **só quando existe**; barra inferior apenas em telemóvel com destinos
  reais (Atividades, Histórico, Sair). A experiência continua escolhida pela
  terapeuta (`visual_experience`), nunca pela idade.

## Reutilização em Flutter
`design/tokens.json` contém paleta, papéis por experiência, raios, sombras,
movimento e tamanhos; ilustrações são SVG (exportáveis para `flutter_svg`).
Nenhuma app foi criada.

## Diferenças conhecidas face à referência
- **Ilustração**: a referência usa arte raster texturizada (aguarela, esquilo
  pintado, bosque rico em detalhe). A implementação usa vetores planos
  originais — reconhecíveis na paleta, composição e personagem, mas **menos
  ricos**. Ver `docs/assets.md` ("Por produzir").
- Escolhas ilustradas (cão/gato/pássaro): as opções são texto até existir
  imagem por opção no editor.
- Duração estimada nas atividades e separador "Recursos": não existem no
  backend — deliberadamente omitidos em vez de inventados.
- Avatares são genéricos/ficcionais; o portal não tem fotografias.
- Navegação 14–18 em telemóvel tem 3 destinos (a referência tem 4).
