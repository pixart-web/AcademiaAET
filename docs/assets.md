# Inventário de assets e licenças

Todos os assets visuais deste projeto são **vetoriais, originais, desenhados
para o projeto** (componentes React/SVG em `resources/js/Components/art/`).
Nenhum ficheiro raster, nenhum clipart de terceiros, nenhum serviço externo
em runtime. Nenhum asset contém texto — títulos e instruções são HTML.

| Asset | Ficheiro | Origem / licença | Estado |
|---|---|---|---|
| Wordmark "academia AET" + símbolo botânico | `art/Logo.tsx` | Original, proposta visual para o protótipo. **Não é marca registada** nem logótipo oficial da clínica; substituição num único ficheiro. | Proposta |
| Esquilo "Nogueira" (4 estados: acolhimento, explicação, espera, celebração) | `art/Squirrel.tsx`, `Components/Mascot.tsx` | Original, desenhado para o projeto. Cores fixas para identidade consistente. | Proposta (ver diferenças) |
| Cenário de bosque (3–6 anos) | `art/Scenes.tsx` → `ForestScene` | Original | Proposta |
| Paisagem com ribeira, pedras, casa na árvore, placas (7–13 anos) | `art/Scenes.tsx` → `TrailScene` | Original | Proposta |
| Ramos/folhas decorativos | `art/Botanicals.tsx` | Original | Proposta |
| Avatares ilustrados fictícios | `art/Avatar.tsx` | Original; gerados por semente numérica, não representam crianças reais nem inferem nada dos dados | Proposta |
| Ícones funcionais (≈30) | `Components/Icon.tsx` | Originais (grelha 24px, traço 1.7) — sem licença de terceiros a acompanhar | Final |
| Fontes: Fraunces, Nunito, Nunito Sans | `@fontsource-variable/*` (npm), servidas localmente pelo Vite | SIL Open Font License 1.1; cobertura completa de português | Final |

## Por produzir
- Ilustrações **raster texturizadas** como as da referência (aguarela suave,
  folhagem com textura, esquilo com pelo pintado): os assets atuais são
  vetoriais planos. Recomenda-se encomendar ou gerar (com revisão) um pacote
  definitivo e substituí-lo mantendo os mesmos componentes.
- Ilustrações para escolhas de resposta (ex.: cão/gato/pássaro do ecrã de
  referência): hoje as opções são texto; o editor ainda não tem upload de
  imagem por opção.
- Avatares mais variados / personalizáveis pela terapeuta.
