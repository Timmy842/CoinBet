# CLAUDE.md — CoinBet

Guía del proyecto para agentes de código. Léela entera antes de tocar nada. Si una instrucción del usuario contradice la arquitectura descrita aquí, avisa antes de implementar.

## Qué es CoinBet

Simulador de apuestas deportivas con **moneda 100% virtual** (sin dinero real, sin pagos). Proyecto de portfolio cuyo objetivo es demostrar DDD/arquitectura hexagonal en Laravel y un frontend React moderno. **La calidad arquitectónica es el producto**: un atajo que rompa las capas destruye el valor del proyecto aunque "funcione".

- Deportes: fútbol primero (datos reales de API-Sports), MMA después.
- Mercado inicial: 1X2. Nada de mercados complejos hasta que el core esté completo.
- Idioma del código: **inglés** (clases, métodos, tests, commits). Comunicación con el usuario: español.

## Estructura del monorepo

```
coinbet/
├── api/            # Laravel 12 · PHP 8.3 · MySQL 8 · Pest
│   ├── app/        # Solo arranque del framework (providers, kernel). NO lógica de negocio.
│   ├── src/        # TODO el código de dominio y aplicación vive aquí (namespace CoinBet\)
│   └── tests/
├── web/            # React 19 · TypeScript · Vite · TanStack Query · Zustand · Tailwind
├── docker/         # nginx config
└── .github/        # CI: Pest + lint/build en cada push
```

Comandos:
- API: `docker compose exec app php artisan ...` · Tests: `cd api && ./vendor/bin/pest`
- Web: `cd web && npm run dev` · Lint: `npm run lint` (Oxlint)
- La API corre en `localhost:8000`, el front en `localhost:5173`.

## Arquitectura backend (INNEGOCIABLE)

Bounded contexts bajo `api/src/`, cada uno con tres capas:

```
src/<Context>/
├── Domain/          # PHP puro. PROHIBIDO importar Laravel, Eloquent o Illuminate\*
│   ├── Model/       # Agregados y entidades
│   ├── ValueObject/
│   ├── Event/       # Domain events
│   ├── Exception/   # Excepciones de dominio (extienden DomainException)
│   └── Repository/  # SOLO interfaces
├── Application/     # Casos de uso (Handlers) + Commands/DTOs. Orquesta, no decide reglas.
│   └── <UseCase>/   # p.ej. PlaceBet/ → PlaceBetCommand + PlaceBetHandler
└── Infrastructure/
    ├── Persistence/ # Implementaciones Eloquent de los repositorios
    └── Http/        # Controllers, FormRequests, API Resources
```

Contexts: `Wallet` (monedero/ledger), `Betting` (eventos, apuestas, settlement), `Shared` (base común).

**Regla de dependencias:** Domain no depende de nada. Application depende solo de Domain. Infrastructure depende de ambas. Los controllers son finos: validan (FormRequest) → construyen Command → invocan Handler → devuelven Resource. Nunca lógica de negocio en un controller ni en un modelo Eloquent.

**Bindings:** interfaz → implementación en un ServiceProvider de Infrastructure.

**Excepciones de dominio** se mapean a HTTP en un handler central (409/422). Nunca try/catch de dominio dentro de controllers.

## Invariantes del dominio (el corazón del proyecto)

Estos invariantes se protegen EN EL DOMINIO y se demuestran con tests. No se validan solo en FormRequests.

**Wallet:**
1. El saldo NUNCA puede ser negativo → `InsufficientFunds`.
2. El saldo es la SUMA de movimientos inmutables (`LedgerEntry`), jamás una columna que se sobreescribe. Las correcciones son nuevos movimientos (reversal), nunca updates ni deletes.
3. Dinero = enteros (`Coins` value object). PROHIBIDO usar float para cantidades.

**Betting:**
4. La cuota (odds) se CONGELA en el momento de apostar: la apuesta guarda su propia cuota, no referencia la actual.
5. No se puede apostar en un evento ya empezado o finalizado.
6. Estados de la apuesta: `placed → won | lost | void`. Las transiciones inválidas lanzan excepción de dominio.
7. El settlement es IDEMPOTENTE: resolver dos veces el mismo evento no paga dos veces. Cada pago referencia la apuesta que lo origina.
8. Apostar = debitar el monedero + crear la apuesta EN LA MISMA transacción, con lock (`lockForUpdate`) contra condiciones de carrera.

**Integración externa (API-Sports):**
9. El dominio NO conoce API-Sports. Existe `SportsDataProviderInterface` (Domain/Application) y adapters en Infrastructure que traducen su formato al modelo propio (`SportEvent`, `Market`, `Selection` con `external_id`). Anti-corruption layer estricto.
10. Los datos externos se cachean en BD vía comandos programados (scheduler). El front y los casos de uso leen SIEMPRE de la BD propia, jamás de la API externa en caliente.

## Cómo programar en este repo

### Backend (PHP)
- `declare(strict_types=1);` en todos los ficheros.
- Clases `final` por defecto; `readonly` para value objects y DTOs.
- Constructores privados + named constructors (`Coins::of()`, `Wallet::open()`).
- Enums nativos de PHP para estados y tipos.
- **Test-first en el dominio:** toda regla de negocio nueva empieza con un test Pest en `tests/Unit/<Context>/`. Los tests de dominio NO tocan Laravel ni BD.
- Tests de Feature (HTTP + BD) para los endpoints, incluidos los de concurrencia e idempotencia.
- Estilo: Laravel Pint (`./vendor/bin/pint`) antes de commitear.
- No añadir paquetes sin justificación. Preguntar antes de instalar dependencias nuevas.

### Frontend (React)
- Estructura por features: `src/features/<feature>/{api,components,pages}` + `src/shared/{ui,api,lib}`.
- TanStack Query para TODO estado de servidor (queries + mutations con `invalidateQueries`). Zustand SOLO para sesión/UI global. Nunca duplicar estado de servidor en Zustand.
- TypeScript estricto: nada de `any`. Tipos de la API en `src/shared/api/types.ts`.
- Componentes función + hooks. Sin clases. Tailwind para estilos.
- El front consume SOLO la API propia (`VITE_API_URL`), nunca API-Sports.

### Git
- Conventional Commits: `feat(wallet): ...`, `fix(betting): ...`, `chore: ...`, `test(wallet): ...`
- Commits pequeños y atómicos. La CI (Pest + build) debe pasar; no commitear con tests rojos.
- No commitear `.env`, keys de API-Sports (`API_SPORTS_KEY` solo en `.env`), ni ficheros generados.

## Qué NO hacer (errores que destruyen el proyecto)

- ❌ Lógica de negocio en controllers, modelos Eloquent o FormRequests.
- ❌ Importar `Illuminate\*` o facades dentro de `Domain/`.
- ❌ Floats para dinero. Columnas de saldo que se sobreescriben. Updates/deletes de ledger entries.
- ❌ Llamar a API-Sports desde casos de uso o desde el front.
- ❌ Saltarse los tests de dominio "para ir más rápido".
- ❌ Añadir features fuera del roadmap (mercados nuevos, pagos, social) sin que el usuario lo pida.
