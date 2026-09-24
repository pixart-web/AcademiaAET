# Checklist de preparação do servidor e política de backup

Nada disto foi executado nesta etapa — nenhum deployment foi feito, por
instrução explícita. Isto é a lista do que falta verificar/decidir antes de
uma instalação real, não uma confirmação de que já está pronto.

## Antes de apontar para dados reais

- [ ] `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] `APP_ALLOW_DEMO_SEEDING` **ausente ou `false`** — confirmar que
      `php artisan migrate` sozinho (sem `--seed`, e mesmo com `--seed`
      fora de local/testing) não cria nenhuma criança, resultado ou conta
      fictícia. `DatabaseSeeder` só chama `DemoDataSeeder`, que se recusa a
      correr fora de local/testing sem essa flag — testar isto
      explicitamente no ambiente de produção antes de ligar ao público.
- [ ] Gerar `APP_KEY` novo e único para produção (nunca reutilizar o de
      desenvolvimento).
- [ ] Rever `.env` de produção contra `.env.example` — nenhum segredo deve
      estar commitado no repositório (confirmado: `.env.example` não tem
      valores reais).
- [ ] `SESSION_ENCRYPT=true` e cookies `secure` (o código já define
      `secure: app()->isProduction()` no cookie do dispositivo da
      criança — confirmar que `APP_ENV=production` está mesmo definido
      para isto entrar em vigor).
- [ ] Disco de armazenamento privado (`FILESYSTEM_DISK`) apontado para um
      backend persistente e privado (S3 privado ou equivalente) — nunca o
      disco `public`.
- [ ] Configurar um mailer real (não Mailpit) para os emails operacionais
      (novo trabalho atribuído, avaliação disponível).
- [ ] Confirmar que o worker de filas (`php artisan queue:work` ou
      equivalente gerido) está a correr — as notificações por email e a
      notificação interna de submissão são despachadas por fila.
- [ ] HTTPS obrigatório ponta-a-ponta (as URLs assinadas de media e os
      cookies de dispositivo dependem de um canal seguro).
- [ ] Rever `config/sanctum.php` — domínios stateful, `expiration` dos
      tokens (por omissão os tokens da API não expiram automaticamente;
      decidir uma política antes de expor a API publicamente).

## Backups

- **Base de dados**: usar o mecanismo de backup do fornecedor de Postgres
  escolhido (snapshot gerido, `pg_dump` agendado, etc.) — não existe ainda
  um comando/agendamento de backup neste repositório.
- **Media privado** (gravações, desenhos, documentos): fica fora da base de
  dados, no disco configurado (`storage/app/private` em desenvolvimento).
  Uma cópia de segurança da base de dados **sem** incluir este disco perde
  todas as gravações e desenhos das crianças — os dois têm de ser
  salvaguardados em conjunto e de forma consistente (mesmo ponto no tempo).
- **Retenção**: o modelo de dados usa `SoftDeletes` nas tabelas sensíveis
  (crianças, atividades, contas, media, notas clínicas), o que permite
  repor um registo apagado por engano. Isto **não** é uma política de
  retenção — é só uma rede de segurança contra eliminação acidental. A
  política real de quanto tempo guardar cada tipo de dado é uma decisão da
  clínica, ainda pendente (ver `docs/delivery-report.md`).

## Reposição (restore)

Não testado nesta etapa. Antes de confiar num backup:
1. Testar restaurar a base de dados + o disco de media num ambiente
   separado.
2. Confirmar que as URLs assinadas de media continuam a funcionar após a
   reposição (dependem de `APP_KEY` para a assinatura — restaurar com a
   `APP_KEY` errada invalida todas as URLs assinadas existentes, não os
   ficheiros em si).
3. Confirmar que as sessões de dispositivo (`device_associations`) e os
   tokens Sanctum sobrevivem ou são corretamente invalidados, consoante a
   decisão da clínica sobre reautenticação pós-reposição.

## O que este repositório não faz (por instrução)

Não foi feito nenhum deployment, nenhuma configuração de servidor real, e
nenhuma app foi publicada em loja de aplicações — como pedido
explicitamente no enunciado.
