# Rastreio de Linguagem e Fala (3–7 anos)

Fluxo conduzido por um adulto (terapeuta/administrador) em **Rastreios**. Não é um diagnóstico.

## O que está implementado
- Registo inicial (instituição, criança, data DD/MM/AAAA, género, encarregado) e idade calculada no servidor (anos + meses completos).
- Encaminhamento automático: 3 (36–47 m), 4 (48–59), 5 (60–71), 6–7 (72–95).
- Fala: o avaliador regista Correto / Substituição (+ som produzido) / Omissão / Distorção; fonemas mapeados pela tabela em `config/screening.php`.
- Compreensão: validação 100 % automática no servidor (a resposta certa nunca é enviada ao browser).
- Expressão (nomeação, repetição de frase): Correto/Incorreto pelo avaliador.
- Pontuação 1/0; 3 erros consecutivos na mesma subsecção fecham-na; restantes itens ficam "Não aplicado / Sem pontuação".
- Relatório PDF (cabeçalho, dados, gráfico por área, fonemas, conclusão) enviado ao encarregado e à clínica (`SCREENING_CLINIC_EMAIL`). O e-mail não contém o resultado, só o anexo.

## Pressupostos a confirmar com a clínica
1. Itens "não aplicados" ficam fora do denominador da percentagem (`count_not_applied_as_zero`).
2. A regra dos 3 erros aplica-se a todas as subsecções.
3. "Esperado para a idade" = idade em meses acima do limite superior da faixa do fonema.
4. Posição de /R/ em "Carroça" e item "Prato (em treino)" por validar.

## Limitações
- Não há análise fonética automática: fala, nomeação e repetição são classificadas pelo adulto.
- O banco de itens real e as imagens da clínica não foram fornecidos; `database/data/screening-demo-items.json` tem 80 itens **fictícios** (`is_demo`). Importação: `php artisan screening:import ficheiro.json --organization=ID`.
- Logótipo/nome e e-mail da clínica por configurar.
- Envio de dados de saúde por e-mail exige base legal/consentimento validados pela clínica (RGPD).

## Dependências
`barryvdh/laravel-dompdf` adicionado; `symfony/mime` atualizado (CVE-2026-45070).
