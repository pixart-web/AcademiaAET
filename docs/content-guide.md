# Guia de conteúdos — criar atividades e substituir a demonstração

## Os exemplos atuais são só demonstração

Tudo o que existe hoje (`DemoDataSeeder`) está marcado `is_demo = true` na
base de dados e identificado como "demonstração" na interface. Não é um
instrumento clínico, não foi validado cientificamente, e deve ser
substituído pelo conteúdo real da clínica através do backoffice — nunca
editando código.

## Criar uma atividade nova (sem tocar em código)

1. Entrar no portal profissional → **Atividades → Nova atividade**.
2. Preencher título, descrição, categoria, área, dificuldade (texto livre
   por agora — ver limitação abaixo).
3. Escrever as instruções gerais e os critérios de avaliação (texto livre,
   visível só à equipa).
4. Adicionar passos, um a um. Cada passo tem:
   - Um título e um texto de instrução para a criança/jovem.
   - Um tipo de resposta — as 7 opções: escolha única, escolha múltipla,
     texto curto, desenho, gravação de voz, gravação de vídeo, confirmação
     de realização.
   - Para escolha única/múltipla: as opções, e (só em escolha única) qual é
     a correta, para pontuação automática.
5. Guardar → a atividade fica em **rascunho**. Só atividades **publicadas**
   podem ser atribuídas a uma criança.
6. **Publicar** quando estiver pronta.

### Sobre o conteúdo áudio/imagem/vídeo de cada passo

A biblioteca de conteúdos (**Conteúdos** no menu) aceita imagem, áudio,
vídeo e documento. Texto alternativo (ou transcrição) é **obrigatório**
para imagem/áudio/vídeo — sem isso o upload é recusado. Este é o ficheiro
que, associado a um passo, a criança pode voltar a ouvir/ver sem limite
(botão de repetir na experiência de execução).

Quando não existe áudio próprio gravado, o portal da criança oferece
sempre uma leitura por voz sintética (botão "Ouvir instrução") do texto do
passo — nunca depende exclusivamente de um ficheiro de áudio que a clínica
ainda não tenha produzido.

## Editar uma atividade já atribuída

Se a atividade já tem alguma criança com ela atribuída (ou já foi
publicada e usada), **editar não altera essa atribuição**: o sistema cria
automaticamente uma nova versão, mantendo a versão antiga exatamente como
estava quando foi respondida. Isto é intencional e testado
(`ActivityVersioningTest`) — nunca reescreve o que uma criança já fez.

## Atribuir a uma criança

No perfil da criança (**Crianças e jovens → [nome]**), secção
**Atribuições**: escolher a atividade publicada, prazo opcional, atribuir.
Só terapeutas atualmente associadas a essa criança (ou administradores)
veem esta opção.

## Substituir os exemplos de demonstração pelo conteúdo real da clínica

1. Criar as atividades reais como descrito acima (`is_demo` fica `false`
   automaticamente para tudo o que for criado pela interface).
2. Quando a clínica confirmar que os exemplos fictícios já não são
   necessários, um administrador pode arquivar (não eliminar) cada
   atividade/criança de demonstração — isto preserva o histórico sem a
   mostrar como ativa.
3. Nunca correr `DemoDataSeeder` em produção — está bloqueado por omissão
   (ver `APP_ALLOW_DEMO_SEEDING` no README).

## Limitações conhecidas nesta etapa

- **Categorias/áreas/dificuldade** são texto livre, não uma lista
  configurável centralmente pela clínica.
- **Sem duplicar atividade** — cada nova atividade parte de um formulário
  vazio.
- **Sem pré-visualização nos três layouts infantis** a partir do editor —
  a única forma de ver como uma atividade fica para uma criança é
  atribuí-la e experimentar com um dispositivo emparelhado.
- **Limites de tamanho/formato de ficheiro** são fixos no código
  (`app/Http/Controllers/MediaAssetController.php`), não configuráveis via
  interface.

Estas limitações estão também registadas em `docs/requirements-matrix.md`.
