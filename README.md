# Academia AET — Portal Web

Plataforma de acompanhamento terapêutico infantil e juvenil para a Academia
AET. Este repositório contém o Portal Web (profissional + três experiências
infantis por faixa etária) e o backend REST (`/api/v1`) partilhado com as
futuras aplicações Android/iOS (não desenvolvidas nesta etapa).

> Identidade visual provisória (wordmark tipográfico, cores e mascote
> originais) — ver `docs/progress.md`. A ferramenta não diagnostica nem
> substitui decisões clínicas.

## Documentação

| Documento | Conteúdo |
|---|---|
| [`docs/progress.md`](docs/progress.md) | Arquitetura, decisões técnicas, modelo de dados, estado funcional |
| [`docs/requirements-matrix.md`](docs/requirements-matrix.md) | Cada requisito do enunciado original, com estado verificado |
| [`docs/openapi.yaml`](docs/openapi.yaml) | Contrato da API `/api/v1` para as futuras apps móveis |
| [`docs/permissions.md`](docs/permissions.md) | Mapa de papéis e permissões |
| [`docs/content-guide.md`](docs/content-guide.md) | Como criar atividades e substituir o conteúdo de demonstração |
| [`docs/visual-review.md`](docs/visual-review.md) | Revisão visual/acessibilidade manual — e as suas limitações explícitas |
| [`docs/production-checklist.md`](docs/production-checklist.md) | Backups, reposição, checklist antes de produção |
| [`docs/delivery-report.md`](docs/delivery-report.md) | Relatório de entrega, testes executados, decisões pendentes da clínica |

## Arquitetura

- **Backend**: Laravel 13 (PHP 8.5), PostgreSQL 16.
- **Portal Web**: Inertia + React 18 + TypeScript, Tailwind CSS v3.
- **API móvel**: REST JSON versionado em `/api/v1`, autenticado por Laravel
  Sanctum, documentado em `docs/openapi.yaml`.
- **Autenticação web**: sessões + cookies, CSRF nativo do Laravel.
- **Acesso da criança**: guard de sessão dedicado (`child`), sem
  email/password — dispositivo emparelhado por código+PIN de uso único,
  depois PIN curto. Ver `docs/progress.md` para o desenho de segurança.
- **Armazenamento**: disco privado local em desenvolvimento
  (`storage/app/private`), compatível com S3 em produção; media servido
  apenas via URLs assinadas de curta duração.
- **Filas/notificações**: driver de base de dados do Laravel; Mailpit em
  desenvolvimento.

## Ambiente de desenvolvimento

Pré-requisitos: PHP 8.3+, Composer, Node 20+, Docker (ou Postgres/Redis
locais equivalentes).

```bash
git clone <repo> academia-aet && cd academia-aet

# Infraestrutura (Postgres na porta 55432, não 5432 — ver docker-compose.yml)
docker-compose up -d pgsql redis mailpit

composer install
npm install

cp .env.example .env
php artisan key:generate

# Cria o esquema e os dados de demonstração fictícios (bloqueado fora de
# ambiente local/testing, ver "Dados de demonstração" abaixo)
php artisan migrate --seed

# Laravel + Vite + fila + logs num só comando
composer run dev
```

Abrir `http://localhost:8000`.

### Dados de demonstração

`database/seeders/DemoDataSeeder.php` cria uma organização, duas contas de
equipa e três perfis de criança fictícios (um por experiência visual), uma
atividade publicada com os três tipos de resposta mais representativos, e
atribuições às três crianças. **Todos os registos têm `is_demo = true`** e
o seeder **recusa-se a correr** fora de `APP_ENV=local`/`testing` a menos
que `APP_ALLOW_DEMO_SEEDING=true` esteja explicitamente definido — uma
instalação de produção arranca sempre vazia.

Login de demonstração (só em ambiente local):

| Papel | Email | Password |
|---|---|---|
| Administrador | `admin@academia-aet.test` | `password` |
| Terapeuta | `terapeuta@academia-aet.test` | `password` |

O acesso das crianças de demonstração usa código de dispositivo + PIN
gerados dinamicamente (nunca fixos) — gere um em
"Crianças e jovens → (perfil) → Acesso do dispositivo" no portal, ou via
`php artisan tinker` (ver `docs/progress.md`).

### Testes

```bash
php artisan test              # suite completa (backend)
php artisan test --filter=X   # um ficheiro/teste específico
npm run build                 # build de produção do frontend + verificação TypeScript
vendor/bin/pint                # formatação PHP
```

Ver `docs/delivery-report.md` para o resultado real da última execução e o
que ficou por testar (browsers reais, dispositivos físicos, leitores de
ecrã).

## Estrutura

```
app/Http/Controllers/       Portal Web (Inertia)
app/Http/Controllers/Api/   API /api/v1 (mesmos serviços, contrato JSON)
app/Services/                Lógica de negócio partilhada entre portal e API
app/Policies/                 Autorização por recurso (organização + atribuição)
resources/js/Pages/          Páginas Inertia (Children, Activities, Evaluations, …)
resources/js/Pages/ChildPortal/  Os três shells infantis (Early/Middle/Teen) + motor partilhado em resources/js/Child/
database/migrations/         Esquema completo (ver docs/progress.md)
docs/                         Documentação de entrega
```

## Licença

Uso interno da Academia AET / Pixart. Não é software open-source.
