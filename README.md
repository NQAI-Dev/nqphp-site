# nqphp

> Modular monolith PHP framework. Symfony components under the hood.
> Vertical Slice Architecture. JS modules per controller.

## Status: pre-alpha Phase 2

`nqphp` is now a **pure framework package** — only `src/Core/` ships
in this repo. Concrete applications (controllers, commands, jobs,
middleware, JS modules) live in **separate application repositories**
that `composer require nqai-dev/nqphp`. The canonical reference
scaffold lives in
[`nqai-dev/nqphp-template`](https://github.com/NQAI-Dev/nqphp-template)
and demonstrates the framework's conventions.

What ships in this repo:

* `src/Core/Attribute/` — `#[Controller]`, `#[Route]`, `#[AsCommand]`,
  `#[Middleware]`, `#[Schedule]` plus more Phase 2 attributes
* `src/Core/Routing/` — route auto-discovery + named groups
* `src/Core/Kernel/` — minimal HTTP kernel; runs discovered middlewares
  around route dispatch
* `src/Core/Console/` — Symfony Console wrapper + `CommandDiscoverer`
* `src/Core/Middleware/` — auto-discovery + Kernel invocation
* `src/Core/Scheduler/` — `#[Schedule]` attribute + Symfony Scheduler wiring
* `src/Core/Config/` — per-feature `config.php` reader + `Kernel::config()`
* `src/Core/Js/` — JS-module runtime helper + per-feature `client.js`
  ES-module serving
* `src/Core/Security/` — double-submit-cookie CSRF (`CsrfTokenManager`)
* `src/Core/Controller/` — base class with `render / redirect / json`
* `src/Core/Entity/` — custom ORM (`#[Id]` / `#[Column]` / `#[Where]`,
  `EntityManager`, pluggable `DriverInterface` with `InMemoryDriver`
  + `SqliteDriver` PDO-backed)

## Architecture decisions

* **Framework + apps are separate repos.** Framework code ships here
  as a Composer package; consumer apps live in their own repos with
  their own `composer.json` that `require`s `nqai-dev/nqphp`. The
  scaffold repo
  [`nqphp-template`](https://github.com/NQAI-Dev/nqphp-template)
  demonstrates the convention end-to-end.

* **Vertical Slice Architecture.** Each feature in a consumer app is
  a directory under `src/Feature/{Name}/` containing its own
  `Controller/`, `Command/`, `Middleware/`, `Scheduler/`, and
  `config.php`. The framework discovers these at boot via reflection.
  A feature is the unit of isolation and (eventually) extraction.

* **Symfony components as building blocks.** `symfony/routing`,
  `symfony/http-kernel`, `symfony/console`, `symfony/dependency-injection`,
  `symfony/scheduler`. Not `framework-bundle` — too opinionated. We
  glue components together.

* **Custom ORM, no Doctrine.** `#[Id]`, `#[Column]`, `#[Where]`,
  `EntityManager` (Symfony-style `findBy` / `findOneBy` / `count` /
  `persist`), and a pluggable `DriverInterface` with two shipped
  implementations: `InMemoryDriver` (array-backed, ephemeral, fast)
  and `SqliteDriver` (PDO + SQLite, persistent, the production
  choice). Doctrine was rejected because the framework's reflection
  + attribute model is enough for the project's persistence needs
  without the bundle weight.

* **Auto-discovery via attributes.** `#[Controller]` on the class,
  `#[Route]` on the method. No route files. `#[AsCommand]` for CLI.
  `#[Middleware]` for cross-cutting concerns. `#[Schedule]` for
  periodic tasks. `composer scripts` for cs-fixer style checks.

* **Named routes.** Every route has a name like `admin:post:list`
  derived from the `#[Controller]` prefix. Manage thousands of routes
  via `bin/console routes:list` and per-prefix filters.

* **JS modules per controller.** Each controller directory can contain
  a `client.js` that the framework serves as an ES module at
  `/_nqphp/js/{Name}/{file}.js`. HTML works without JS; the module
  progressively enhances interaction. No SPA framework.

* **DX is a product.** `bin/dev` orchestrates `composer install` +
  PHP built-in server. `composer cs:check` / `composer cs:fix`
  enforce PSR-12 via php-cs-fixer (CI step).

* **MIT license.**

## Installation (in a consumer app)

```bash
composer require nqai-dev/nqphp:^0.2
```

Then in your `public/index.php`:

```php
require __DIR__ . '/../vendor/autoload.php';
use Nqphp\Core\Kernel\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel(dirname(__DIR__));
$kernel->handle(Request::createFromGlobals())->send();
```

In your `bin/console`:

```php
require __DIR__ . '/../vendor/autoload.php';
use Nqphp\Core\Kernel\Kernel;
use Nqphp\Core\Console\CommandDiscoverer;
use Nqphp\Core\Scheduler\ScheduleDiscoverer;
use Nqphp\Core\Middleware\MiddlewareDiscoverer;
use Symfony\Component\Console\Application;

$projectDir = dirname(__DIR__);
$kernel = new Kernel($projectDir);
$cmdDiscoverer = new CommandDiscoverer([$projectDir . '/src/Feature', $projectDir . '/src/Core']);
$schedDiscoverer = new ScheduleDiscoverer([$projectDir . '/src/Feature', $projectDir . '/src/Core']);
$mwDiscoverer = new MiddlewareDiscoverer([$projectDir . '/src/Feature', $projectDir . '/src/Core']);

$app = new Application('myapp', '0.1.0');
$app->add(new \Nqphp\Core\Routing\RoutesListCommand($kernel));
$app->add(new \Nqphp\Core\Scheduler\ScheduleListCommand($schedDiscoverer));
$app->add(new \Nqphp\Core\Scheduler\ScheduleRunCommand($schedDiscoverer));
$app->add(new \Nqphp\Core\Config\FeatureListCommand($kernel));
foreach ($cmdDiscoverer->discover() as $cmd) { $app->add($cmd); }
$app->run();
```

See `nqphp-template` for the full scaffolded `bin/console` and
`public/index.php` ready to extend.

## Available CLI commands (built-in)

```
bin/console routes:list          List all auto-discovered HTTP routes.
bin/console schedule:list       List all #[Schedule]-annotated tasks.
bin/console schedule:run        Run schedules whose cron is due now.
bin/console feature:list        Per-feature inventory: config + routes + middlewares.
bin/console entity:list         List all #[Entity]-discovered domain entities.
bin/console entity:show <name>  Show detailed info (#[Id] + #[Column]) for one entity.
bin/console list                 Show Symfony Console's auto-generated help.
```

(`hello:greet` and any other feature commands are *not* shipped by
the framework — they live in your app's `src/Feature/{Name}/Command/`
and are discovered at boot.)

## Custom ORM

The framework ships a small, custom ORM under `src/Core/Entity/` —
**no Doctrine dependency**. Three attributes + one manager + one
pluggable driver:

```php
namespace App\MyApp\Entity;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;
use Nqphp\Core\Attribute\Where;

// `name` is the canonical handle (used in EntityDiscoverer map, CLI
// introspection). `table` is the physical SQL table name — defaults to
// `name` when omitted. Override `table` for legacy DB integration or
// multi-tenant schemas where handle and physical table differ.
#[Entity(name: 'user', table: 'app_users')]
final class User
{
    #[Id]
    public ?int $id = null;

    // UNIQUE constraint enforced by SqliteDriver (Phase 2 #10 extension):
    // duplicates raise PDOException "UNIQUE constraint failed: user.email".
    #[Column(name: 'email', length: 255, unique: true)]
    public string $email = '';

    #[Column(name: 'created_at')]
    public \DateTimeImmutable $createdAt;

    // #[Where] marks the property as filterable with the named SQL
    // operator; the actual operator at query-time is taken from the
    // criterion shape passed to findBy/findOneBy/count.
    #[Where(operator: 'LIKE')]
    public string $displayName = '';
}
```

Then in your application code:

```php
$em = $kernel->entityManager();

// Persist (Unit-of-Work — flush() is currently a no-op until the SQL
// driver becomes the default; in-memory and Sqlite drivers persist
// immediately on persist()).
$alice = new User();
$alice->email = 'alice@example.com';
$alice->createdAt = new \DateTimeImmutable();
$alice->displayName = 'alice';
$em->persist($alice);

// Symfony-style query DSL + remove (Phase 2 #11)
$em->findAll(User::class);
$em->remove($alice);  // returns true once, false on idempotent re-call
$em->findBy(User::class, ['email' => 'alice@example.com']);
$em->findOneBy(User::class, ['email' => 'alice@example.com']);
$em->count(User::class);

// Operator-aware criteria (Phase 2 #10)
$em->findBy(User::class, ['displayName' => ['LIKE' => 'ali%']]);
$em->findBy(User::class, ['id' => ['IN' => [1, 2, 3]]]);
$em->findBy(User::class, ['email' => ['BETWEEN' => ['a@x.com', 'z@x.com']]]);
$em->findBy(User::class, ['createdAt' => ['>=' => '2024-01-01']]);

// count() accepts the same operator-aware criteria
$em->count(User::class);                                    // total
$em->count(User::class, ['email' => ['LIKE' => '%@x.com']]);
$em->count(User::class, ['id' => ['IN' => [1, 2, 3]]]);
```

### Choosing a driver

The default driver is `SqliteDriver` (in-memory when no
`NQPHP_SQLITE_PATH` is set, file-backed otherwise). Override at
boot:

```php
use Nqphp\Core\Entity\EntityManager;
use Nqphp\Core\Entity\Driver\SqliteDriver;
use Nqphp\Core\Entity\Driver\InMemoryDriver;

// File-backed SQLite
$em = new EntityManager(
    $kernel->entityDiscoverer(),
    new SqliteDriver(new \PDO('sqlite:' . __DIR__ . '/var/data.db'))
);

// In-memory (tests, one-shot CLI runs)
$em = new EntityManager(
    $kernel->entityDiscoverer(),
    new InMemoryDriver()
);
```

Both drivers implement `Nqphp\Core\Entity\Driver\DriverInterface`,
so swapping is a one-line change at the wiring layer.

### Schema constraints

`#[Column]` supports metadata that `SqliteDriver::ensureSchema()`
translates into SQL DDL:

| Column attribute | SQLite DDL |
| --- | --- |
| `#[Column(type: 'string')]` | `TEXT` |
| `#[Column(type: 'string', length: 255)]` | `TEXT` (length is application-side, SQLite ignores) |
| `#[Column(type: 'integer')]` | `INTEGER` |
| `#[Column(type: 'float')]` | `REAL` |
| `#[Column(type: 'boolean')]` | `INTEGER` (0/1) |
| `#[Column(type: 'datetime')]` | `TEXT` (ISO-8601) |
| `#[Column(type: 'json')]` | `TEXT` (JSON-encoded) |
| `#[Column(nullable: true)]` | column allows NULL |
| `#[Column(nullable: false)]` | `NOT NULL` (the default) |
| `#[Column(unique: true)]` | `NOT NULL UNIQUE` (Phase 2 #10 extension) |
| `#[Column(default: 'pending')]` | `DEFAULT 'pending'` (string with quote escape) |
| `#[Column(default: 0)]` | `DEFAULT 0` |
| `#[Column(default: true)]` | `DEFAULT 1` (SQLite has no native bool) |
| `#[Column(default: null)]` | no DEFAULT clause (default behavior) |

Multiple `#[Column(unique: true)]` in the same entity become multiple
UNIQUE constraints (enforced independently by SQLite). Combined with
`#[Id]` as primary key, this gives a complete schema layer without
writing migration files.

String defaults are SQL-escaped per the SQL standard: a single quote
inside a literal renders as `''` (two single quotes). This is the
only safe way to embed user-controlled strings in DDL because DDL
does not go through PDO prepared statements. Phase 2 #10 extension.

`InMemoryDriver` ignores schema entirely (no-op) — only `SqliteDriver`
generates DDL. Schema is dropped and re-created on each
`ensureSchema()` call; real migrations (alter-table, add-column
without data loss) come in a Phase 2 follow-up.

### Why not Doctrine?

Doctrine is a full-featured ORM with bundles, annotations, schema
migrations, and an entity manager optimized for hundreds of mapped
classes. `nqphp` boots a single SQLite file or an in-memory array
and serves a few dozen entities per project. The reflection +
attribute model that already drives routing, middlewares, and
scheduling fits this footprint without Doctrine's overhead. If a
project needs Doctrine's query builder, unit-of-work, or
DBAL-style portability, swap the `EntityManager` wiring for
`Doctrine\ORM\EntityManager` — the framework doesn't lock you in.

## Phase 2 status (framework-side)

* ✅ `#[AsCommand]` CLI command auto-discovery.
* ✅ JS-module runtime helper — framework `csrf()`, `fetchJson()`,
  per-feature `client.js` serving.
* ✅ Middleware pipeline — `#[Middleware]` attribute + auto-discovery
  + Kernel runtime invocation + tests.
* ✅ `#[Schedule]` cron + Symfony Scheduler integration — discover +
  list + run via the real Symfony Scheduler evaluator.
* ✅ Per-feature `config.php` autoloader — typed config reads +
  `Kernel::config(string $feature, string $key, $default)`.
* ✅ `bin/console feature:list` — per-feature inventory.
* ✅ PHP CS Fixer — `composer cs:check` / `cs:fix`, CI step.
* ✅ Custom ORM — `#[Id]`, `#[Column]`, `EntityManager` with
  `findBy` / `findOneBy` / `count` / `persist`, plus `DriverInterface`
  with `InMemoryDriver` (array) + `SqliteDriver` (PDO + SQLite).
* ✅ `#[Column(unique: true)]` — UNIQUE column-constraint в SQLite
  schema (Phase 2 #10 extension).
* ✅ `#[Column(default: ...)]` — DEFAULT clause для SQLite (Phase 2
  #10 extension). String values SQL-escaped (`'` → `''`), booleans →
  1/0, numeric as-is, null/no-arg → no DEFAULT clause.
* ✅ `#[Entity(name: ..., table: ...)]` — custom physical SQL table
  name override (Phase 2 #10+ extension). Defaults to `name` for
  backward compat; useful для legacy DB integration та multi-tenant
  schemas де canonical handle і physical table розходяться.
* ✅ `#[Where]` attribute + operator-aware criteria — `LIKE`, `IN`,
  `BETWEEN`, `>=`, `<=`, `!=`, etc. on top of the exact-match API.
* ✅ `bin/console entity:list` + `entity:show <name>` — entity
  discovery reports and single-entity detail view.
* ✅ Kernel wires `SqliteDriver` as default with `NQPHP_DRIVER=memory`
  env override falling back to `InMemoryDriver`.
* ✅ `EntityManager::remove()` (Phase 2 #11) — idempotent delete by
  `#[Id]`, throws on never-persisted entities. Mirrors `persist()`
  symmetry; cascade is opt-in via explicit calls (callers control
  what gets removed when a parent goes away).

Open Phase 2 follow-ups (not yet shipped, lower priority):

* Full Symfony DI integration (per-feature `services.yaml` with
  actual service definitions; current per-feature `config.php` is the
  data-only half).
* Real schema migrations (the current `SqliteDriver::ensureSchema()`
  drops-and-recreates — fine for Phase 1 but not safe for production
  data).
* `EntityManager::flush()` becomes a real barrier once multiple
  persists per request are common (currently each persist() writes
  immediately).

## License

MIT. See [LICENSE](LICENSE).
