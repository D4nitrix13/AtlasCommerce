<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:

- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project keeps committed, area-grouped rules in `.ai/rules` (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>

---

# Project conventions — Panda Estampados / Kitsune Backend

> **Reglas de estilo y arquitectura propias de este repo.** Se aplican
> **encima** de las Laravel Boost Guidelines de arriba. Si hay conflicto,
> estas reglas del proyecto ganan.

## 1. Parámetros nombrados — siempre que sea posible

Usá **argumentos nombrados** en toda llamada a función o método que
reciba 2 o más argumentos donde el orden o el tipo no sea
autoexplicativo. Esto mejora la legibilidad y previene bugs por
argumentos mal ordenados.

```php
// ❌ Mal — el lector tiene que contar la posición para entender qué hace
User::query()->where('email', $email)->where('is_active', true)->first();

// ✅ Bien — el nombre del argumento documenta la intención
User::query()
    ->where(column: 'email', operator: '=', value: $email)
    ->where(column: 'is_active', operator: '=', value: true)
    ->first();
```

### Cuándo SÍ usar named arguments

| Caso                                                                                  | Ejemplo                                                          |
| ------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| Métodos Eloquent con varios parámetros (`where`, `orWhere`, `whereHas`, `orderBy`)    | `->where(column: 'x', operator: '=', value: 1)`                  |
| `Hash::check(value:, hashedValue:)`                                                   | `Hash::check(value: $plain, hashedValue: $hash)`                 |
| `Validator::make(data:, rules:, messages:)`                                           | `Validator::make(data: $req->all(), rules: [...], messages: [...])` |
| `env(key, default)` cuando se quiere distinguir del default                           | `env(key: 'APP_DEBUG', default: false)`                          |
| `config(key, default)` cuando se quiere distinguir del default                        | `config(key: 'app.timezone', default: 'UTC')`                    |
| `DB::transaction(callback, attempts, lockFor)` cuando hay más de 1 arg                | `DB::transaction(callback: fn () => ..., attempts: 3)`           |
| Aserciones PHPUnit multi-argumento que se confunden (`assertSame(expected, actual)`)  | `assertSame(expected: 0, actual: $count)`                        |
| `updateOrCreate(attributes, values)`                                                   | `updateOrCreate(attributes: [...], values: [...])`               |
| `firstOrCreate(attributes, values)`                                                   | `firstOrCreate(attributes: [...], values: [...])`                |
| `findOrFail(id, columns)` cuando se especifican columnas                             | `findOrFail(id: 1, columns: ['id', 'name'])`                     |
| Constructor property promotion con tipos no triviales                                 | `new User(email: $e, password: $p)`                              |

### Cuándo NO usar named arguments

| Caso                                                                                  | Razón                                                       |
| ------------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| Llamadas con un solo argumento                                                        | No agrega valor: `find($id)` es claro                       |
| Arrays asociativos ya auto-documentados                                                | Las keys hacen el trabajo: `updateOrCreate(['email' => $e], [...])` |
| Fluent chains donde el método se llama solo con la closure o array                    | `->with(['relation'])` no necesita `relations:`             |
| Callbacks donde el orden es semántico (e.g. `array_map($fn, $array)`)                 | Cambiar el orden rompería la semántica                      |
| String de SQL crudo en `DB::raw(...)`                                                  | Un solo argumento string                                    |
| Strings de migration: `$table->string('email')`                                        | Un solo argumento                                           |
| Aserciones PHPUnit donde los args son self-evident (`assertSame(2, 1+1)`)              | Contexto trivial                                             |

### Regla de oro

> Si tenés que mirar la firma del método para entender qué representa
> el segundo argumento, usá named args.

## 2. Tipado estricto

- `declare(strict_types=1);` al inicio de **todo** archivo PHP nuevo.
- Type hints en parámetros y return types explícitos en **todo** método.
- Sin `mixed` salvo justificación documentada con PHPDoc.

## 3. Constructor property promotion

- Sin `__construct()` vacío. Si no hay lógica, no declares el constructor.
- Si hay DI, usá promotion: `public function __construct(public Service $svc) {}`.

## 4. Single quotes

- Comillas simples por defecto en strings sin interpolación.
- Double quotes solo cuando hay `\n`, `\t` o interpolación de variables.

## 5. Trailing commas

- En arrays, argumentos y parámetros multilínea.
- Single-line: trailing comma opcional.

## 6. Imports ordenados

- Alfabéticamente por nombre de clase.
- Grupos: `App\*`, luego `Illuminate\*`, luego `Core\*`, luego resto.

## 7. Comentarios

- PHPDoc sobre inline.
- Solo el **por qué**, nunca el **qué**.
- Sin `// TODO` sin ticket linked.

## 8. Tests

- Cobertura mínima: happy path + cada failure path documentado en PHPDoc.
- Nombre del test: `it_does_x_when_y` o `testX`.
- Cada test debe poder correr en isolation (`RefreshDatabase` cuando toca DB).
- Una aserción lógica por test cuando sea posible.
