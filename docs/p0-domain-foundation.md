# P0 — Fundamentos de dominio (Wallet + Betting + identidad)

Guía de arquitectura para implementar P0. Cubre **solo `api/`** (Laravel). El frontend (`web/`) no se toca hasta P1 — todo se prueba con Pest + Tinker/Postman.

> Este documento es una guía para *cómo construirlo*, no código. Revisa cada sección contra el `CLAUDE.md` si tienes dudas de una regla.

---

## 0. Estado real del repo ahora mismo (verificado)

Antes de tocar nada, esto es lo que ya existe y lo que falta — importante porque cambia el plan que te pasó Fable en algunos puntos:

| Punto | Estado actual | Acción |
|---|---|---|
| `composer.json` autoload | Ya tiene `"Coinbet\\": "src/"` (con *b* minúscula) | **Cambiar a `"CoinBet\\": "src/"`** — decidiste mantener la casing de `CLAUDE.md`. Es un cambio de una línea, pero afecta a todos los `namespace CoinBet\...` de este documento. Hazlo primero, antes de crear ningún archivo en `src/`. |
| `bootstrap/app.php` | Solo registra `web`, `commands` y `health`. No hay `routes/api.php`, no hay mapeo de excepciones de dominio en `withExceptions()`. | Editar para añadir `api: __DIR__.'/../routes/api.php'` en `withRouting()` y el `render()` de `DomainException` en `withExceptions()`. |
| `routes/api.php` | No existe | Crear. |
| `bootstrap/providers.php` | Solo `AppServiceProvider` | Añadir `DomainServiceProvider`. |
| Paquete de autenticación (Sanctum u otro) | **No instalado** — no está en `composer.json`, no hay migración `personal_access_tokens` | Es una decisión pendiente, no asumida. Ver nota en la sección de Identity. |
| `src/` | No existe todavía | Se crea todo desde cero. |

---

## 1. Contexts según CLAUDE.md

El CLAUDE.md nombra explícitamente **tres** contexts: `Wallet`, `Betting`, `Shared`. No menciona un context `Identity`. Esto importa para una decisión de diseño:

**Decisión: NO crear `src/Identity/` como bounded context.** El listener que abre el wallet al registrarse un usuario es *orquestación de framework* (conecta un evento de Laravel con un caso de uso de `Wallet`), no una regla de negocio ni un agregado propio. Ponerlo en `src/Identity/` sugiere un cuarto context que el CLAUDE.md no reconoce. En su lugar:

```
app/Listeners/OpenWalletOnUserRegistered.php
```

vive en `app/` (arranque de framework, tal como dice el CLAUDE.md), escucha `Illuminate\Auth\Events\Registered` (el evento estándar de Laravel, no hace falta uno custom), e invoca `CoinBet\Wallet\Application\OpenWallet\OpenWalletHandler`. Esto es la única línea donde `app/` "conoce" algo de un context — y es aceptable porque no contiene ninguna regla, solo dispara el caso de uso.

Si más adelante `Identity` necesita lógica propia (roles, verificación, KYC simulado, etc.), entonces sí se promociona a bounded context. Hoy no hace falta.

**Nota sobre autenticación:** el CLAUDE.md no especifica qué paquete de auth usar, y `composer.json` no tiene ninguno instalado. Antes de implementar el registro de usuarios necesitas decidir esto (Sanctum es lo estándar para una SPA que consume la API vía token, pero es una dependencia nueva → según CLAUDE.md hay que preguntar antes de instalarla). Este documento asume que ya existe *algún* mecanismo de registro que dispara `Registered`; el "cómo" de la autenticación en sí queda fuera del alcance de P0 tal como lo has descrito (Wallet + Betting + identidad mínima).

---

## 2. Árbol de archivos completo

```
api/
├── composer.json                          # EDITAR: "CoinBet\\": "src/"
├── bootstrap/
│   ├── app.php                            # EDITAR: routing api.php + withExceptions()
│   └── providers.php                      # EDITAR: registrar DomainServiceProvider
├── routes/
│   └── api.php                            # NUEVO
├── database/migrations/
│   ├── xxxx_create_wallets_table.php      # NUEVO
│   ├── xxxx_create_ledger_entries_table.php
│   ├── xxxx_create_sport_events_table.php
│   ├── xxxx_create_markets_table.php
│   ├── xxxx_create_selections_table.php
│   └── xxxx_create_bets_table.php
├── app/
│   ├── Listeners/
│   │   └── OpenWalletOnUserRegistered.php # NUEVO (ver sección 1)
│   ├── Models/User.php                    # ya existe, sin cambios de lógica
│   └── Providers/
│       ├── AppServiceProvider.php         # ya existe
│       └── DomainServiceProvider.php      # NUEVO: bindings interfaz → Eloquent
├── src/
│   ├── Shared/
│   │   ├── Domain/
│   │   │   ├── ValueObject/Id.php
│   │   │   ├── Exception/DomainException.php
│   │   │   └── Event/DomainEventPublisher.php        (interfaz)
│   │   └── Infrastructure/
│   │       └── Event/LaravelDomainEventPublisher.php
│   ├── Wallet/
│   │   ├── Domain/
│   │   │   ├── Model/Wallet.php
│   │   │   ├── Model/LedgerEntry.php
│   │   │   ├── ValueObject/Coins.php
│   │   │   ├── ValueObject/LedgerEntryType.php        (enum)
│   │   │   ├── Event/WalletOpened.php
│   │   │   ├── Event/WalletCredited.php
│   │   │   ├── Event/WalletDebited.php
│   │   │   ├── Exception/InsufficientFunds.php
│   │   │   └── Repository/WalletRepository.php        (interfaz)
│   │   ├── Application/
│   │   │   ├── OpenWallet/OpenWalletCommand.php
│   │   │   ├── OpenWallet/OpenWalletHandler.php
│   │   │   ├── CreditWallet/CreditWalletCommand.php
│   │   │   ├── CreditWallet/CreditWalletHandler.php
│   │   │   ├── DebitWallet/DebitWalletCommand.php
│   │   │   ├── DebitWallet/DebitWalletHandler.php
│   │   │   ├── GetBalance/GetBalanceQuery.php
│   │   │   ├── GetBalance/GetBalanceHandler.php
│   │   │   └── GetLedgerHistory/GetLedgerHistoryQuery.php + Handler.php
│   │   └── Infrastructure/
│   │       ├── Persistence/EloquentWalletRepository.php
│   │       ├── Persistence/WalletModel.php
│   │       ├── Persistence/LedgerEntryModel.php
│   │       └── Http/WalletController.php
│   │       └── Http/Resources/WalletResource.php
│   │       └── Http/Resources/LedgerEntryResource.php
│   └── Betting/
│       ├── Domain/
│       │   ├── Model/SportEvent.php
│       │   ├── Model/Market.php
│       │   ├── Model/Selection.php
│       │   ├── Model/Bet.php
│       │   ├── ValueObject/Odds.php
│       │   ├── ValueObject/BetStatus.php              (enum)
│       │   ├── ValueObject/MarketType.php              (enum)
│       │   ├── Event/BetPlaced.php
│       │   ├── Event/EventSettled.php
│       │   ├── Exception/InvalidBetTransition.php
│       │   ├── Exception/EventAlreadyStarted.php
│       │   ├── Exception/EventAlreadySettled.php
│       │   └── Repository/BetRepository.php            (interfaz)
│       │   └── Repository/SportEventRepository.php      (interfaz)
│       ├── Application/
│       │   ├── PlaceBet/PlaceBetCommand.php
│       │   ├── PlaceBet/PlaceBetHandler.php
│       │   ├── SettleEvent/SettleEventCommand.php
│       │   ├── SettleEvent/SettleEventHandler.php
│       │   ├── VoidBet/VoidBetCommand.php + Handler.php
│       │   └── GetUserBets/GetUserBetsQuery.php + Handler.php
│       └── Infrastructure/
│           ├── Persistence/EloquentBetRepository.php
│           ├── Persistence/EloquentSportEventRepository.php
│           ├── Persistence/BetModel.php
│           ├── Persistence/SportEventModel.php
│           ├── Persistence/MarketModel.php
│           ├── Persistence/SelectionModel.php
│           └── Http/BetController.php
│           └── Http/Requests/PlaceBetRequest.php
│           └── Http/Resources/BetResource.php
└── tests/
    ├── Unit/Wallet/...      # dominio puro: sin RefreshDatabase, sin Illuminate
    ├── Unit/Betting/...
    ├── Feature/Wallet/...   # HTTP + BD
    └── Feature/Betting/...  # incluye concurrencia (PlaceBet) e idempotencia (SettleEvent)
```

No hay `src/Identity/` — ver decisión en sección 1.

---

## 3. Qué va en cada archivo, y por qué

### `composer.json`
Añadir/corregir `"CoinBet\\": "src/"` en `autoload.psr-4`. Sin esto nada de `src/` se autocarga. Ejecutar `composer dump-autoload` después.

### `bootstrap/app.php`
Laravel 12 no tiene `Kernel.php`; todo se configura aquí:
- `withRouting()`: añadir `api: __DIR__.'/../routes/api.php'` (y normalmente `apiPrefix: 'api'`, aunque ya viene por defecto).
- `withExceptions()`: capturar `CoinBet\Shared\Domain\Exception\DomainException` (y sus subclases) y mapearlas a HTTP (409 para conflictos como `InsufficientFunds`/`EventAlreadyStarted`, 422 para validación de reglas). **Este es el único sitio del proyecto donde se hace ese mapeo** — nunca un try/catch de dominio dentro de un controller.

### `routes/api.php`
Rutas de `Wallet` y `Betting`, agrupadas bajo middleware de auth (`auth:sanctum` o el que se decida). Fino: solo define método HTTP + acción de controller, cero lógica.

### `bootstrap/providers.php`
Registrar `App\Providers\DomainServiceProvider`.

### `app/Providers/DomainServiceProvider.php`
El único lugar donde se hace el binding interfaz → implementación:
```php
$this->app->bind(WalletRepository::class, EloquentWalletRepository::class);
$this->app->bind(BetRepository::class, EloquentBetRepository::class);
$this->app->bind(SportEventRepository::class, EloquentSportEventRepository::class);
```

### `app/Listeners/OpenWalletOnUserRegistered.php`
Escucha `Illuminate\Auth\Events\Registered`, construye un `OpenWalletCommand` con el id del usuario recién creado, y lo pasa a `OpenWalletHandler`. Ver sección 1 para el porqué de su ubicación.

### `app/Models/User.php`
Sin cambios de lógica. Sigue siendo el modelo Eloquent estándar de auth.

---

### `src/Shared/Domain/`
- **`ValueObject/Id.php`** — value object sobre `Ulid` (o `uuid`, a elegir). `Id::generate()`, `Id::fromString()`. Lo usan `Wallet`, `Bet`, `SportEvent`, etc. como su identidad. Constructor privado.
- **`Exception/DomainException.php`** — clase abstracta. Todas las excepciones de dominio (`InsufficientFunds`, `InvalidBetTransition`, ...) heredan de aquí. Es lo que captura `withExceptions()`.
- **`Event/DomainEventPublisher.php`** — interfaz en `Domain`. Método `publish(object $event): void`. Permite que el dominio "publique" eventos sin conocer Laravel.

### `src/Shared/Infrastructure/Event/LaravelDomainEventPublisher.php`
Implementa la interfaz anterior envolviendo el `Illuminate\Contracts\Events\Dispatcher` de Laravel. Se bindea en `DomainServiceProvider`.

---

### `src/Wallet/Domain/`
- **`ValueObject/Coins.php`** — `final readonly class`, constructor privado, named constructor `Coins::of(int $amount)`. Métodos `add()`, `subtract()`. **`subtract()` es donde vive el invariante 1**: si el resultado sería negativo, lanza `InsufficientFunds`. Aquí también vive el invariante 3 (todo es `int`, nunca `float`).
- **`ValueObject/LedgerEntryType.php`** — enum nativo: `Credit`, `Debit` (y quizá `Bonus`/`Correction` si quieres distinguir motivos, pero para P0 con dos basta).
- **`Model/LedgerEntry.php`** — `final readonly`: `id`, `walletId`, `type: LedgerEntryType`, `amount: Coins`, `reference` (id de la apuesta/operación que originó el movimiento), `createdAt`. Sin setters — es el invariante 2 en código: un movimiento nunca se modifica, solo se crean nuevos (incluidas las correcciones, como *reversal*).
- **`Model/Wallet.php`** — el agregado. **No tiene una propiedad `balance` persistida.** Se reconstruye a partir de sus `LedgerEntry` (o el repo entrega la suma ya calculada, según cómo optimices la lectura) y expone `balance(): Coins`. `credit()`/`debit()` añaden un `LedgerEntry` nuevo — nunca tocan uno existente. Esto es el invariante 2 en su forma más estricta.
- **`Event/WalletOpened.php`, `WalletCredited.php`, `WalletDebited.php`** — DTOs inmutables que representa cada evento de dominio; se publican vía `DomainEventPublisher` desde los Handlers (no desde el agregado directamente, para no acoplar Domain a un publisher).
- **`Exception/InsufficientFunds.php`** — extiende `DomainException`. Se mapea a 409 en `withExceptions()`.
- **`Repository/WalletRepository.php`** — interfaz: `findByUserId(Id $userId): Wallet`, `save(Wallet $wallet): void`, y **`findByUserIdForUpdate(Id $userId): Wallet`** — esta última es la que usa `PlaceBetHandler` para el lock pesimista (invariante 8).

### `src/Wallet/Application/`
Cada caso de uso es una carpeta con un `Command` (DTO `readonly`, sin lógica) y un `Handler` (`__invoke` u otro método público único). El Handler orquesta: pide el agregado al repo, llama a sus métodos, guarda, publica el evento. **El Handler no decide reglas** — la regla vive en `Wallet`/`Coins`. Si un Handler empieza a tener `if` de negocio, esa lógica se ha escapado del dominio y hay que moverla.

### `src/Wallet/Infrastructure/`
- **`Persistence/WalletModel.php`, `LedgerEntryModel.php`** — Eloquent. Viven aquí, no en `app/Models/`, porque son detalle de infraestructura *del context Wallet*, no del framework en general.
- **`Persistence/EloquentWalletRepository.php`** — implementa `WalletRepository`. Traduce entre `WalletModel`/`LedgerEntryModel` (filas) y el agregado `Wallet` (objeto de dominio con su lista de `LedgerEntry`). Aquí es donde `findByUserIdForUpdate()` hace el `lockForUpdate()` de Eloquent.
- **`Http/WalletController.php`** — fino: `GetBalance`/`GetLedgerHistory` vía sus Handlers, devuelve `WalletResource`/`LedgerEntryResource`. Sin lógica de negocio, sin try/catch de dominio.

---

### `src/Betting/Domain/`
- **`Model/SportEvent.php`, `Market.php`, `Selection.php`** — el catálogo. En P0, `Market` solo necesita soportar 1X2 (el enum `MarketType` puede tener un único caso hoy, ya pensado para crecer). `SportEvent` guarda `externalId`, `startsAt`, `status` — el `status`/`startsAt` es lo que permite comprobar el invariante 5 (no apostar en evento ya empezado).
- **`ValueObject/Odds.php`** — igual patrón que `Coins`: entero escalado (`1850` en vez de `1.85`) para no meter floats en dinero/cuotas.
- **`ValueObject/BetStatus.php`** — enum: `Placed`, `Won`, `Lost`, `Void`.
- **`Model/Bet.php`** — el agregado central. Guarda su propia `Odds` (congelada al crear — invariante 4, nunca referencia la cuota actual de la `Selection`). Método `settle(bool $won): void` que valida la transición de estado (invariante 6: solo `placed → won|lost`) y lanza `InvalidBetTransition` si el estado ya no es `placed` — **esto es lo que hace que `SettleEvent` sea idempotente (invariante 7)**: la segunda vez que se intenta liquidar la misma apuesta, `settle()` falla porque ya no está en `placed`.
- **Excepciones**: `InvalidBetTransition`, `EventAlreadyStarted` (invariante 5), `EventAlreadySettled` (comprobación a nivel de evento, complementa la idempotencia del invariante 7 a nivel de apuesta individual).
- **`Repository/BetRepository.php`, `SportEventRepository.php`** — interfaces. `BetRepository` necesita algo como `findPlacedByEventId(Id $eventId): array` para que `SettleEventHandler` pueda recorrer las apuestas pendientes.

### `src/Betting/Application/`
- **`PlaceBet/PlaceBetHandler.php`** — dentro de una **transacción de BD**: `WalletRepository::findByUserIdForUpdate()` → `wallet->debit()` → crear `Bet` con la odds *actual* de la `Selection` (se congela en ese instante) → guardar wallet y bet. Si algo falla, rollback total. Esto es el invariante 8 completo: débito + creación de apuesta atómicos y con lock.
- **`SettleEvent/SettleEventHandler.php`** — primero comprueba que el `SportEvent` no esté ya liquidado (invariante 7 a nivel de evento); si no, recorre los `Bet` en estado `placed` de ese evento, llama a `settle()` en cada uno, y si ganó, acredita el wallet correspondiente vía `CreditWallet`. Cada acreditación debe referenciar el `Bet` que la originó (para trazabilidad en el ledger).

### `src/Betting/Infrastructure/`
Mismo patrón que `Wallet`: modelos Eloquent propios del context, repos que implementan las interfaces de `Domain`, `BetController` fino con `PlaceBetRequest` (validación de forma — que el body tenga `selection_id` y sea un UUID/ULID válido, **no** validación de reglas de negocio) y `BetResource`.

**Nota sobre API-Sports:** el invariante 9 (anti-corruption layer, `SportsDataProviderInterface`) y el invariante 10 (cache vía scheduler) son para cuando se integre el proveedor externo real. En P0, sin datos reales todavía, `SportEvent`/`Market`/`Selection` se pueden poblar con **seeders/factories** para poder probar `PlaceBet` y `SettleEvent` de extremo a extremo. La interfaz `SportsDataProviderInterface` no hace falta definirla aún si no vas a implementar ningún adapter en este P0 — créala cuando toque la integración real, no antes (evitar abstracciones sin un segundo caso de uso real).

---

## 4. Mapa invariante → archivo

| # | Invariante | Dónde se protege |
|---|---|---|
| 1 | Saldo nunca negativo | `Coins::subtract()` lanza `InsufficientFunds` |
| 2 | Saldo = suma de movimientos inmutables | `Wallet` (sin propiedad `balance` mutable) + `LedgerEntry` sin setters |
| 3 | Dinero = enteros | `Coins` (constructor solo acepta `int`) |
| 4 | Odds congelada al apostar | `Bet` guarda su propia `Odds`, no referencia `Selection` |
| 5 | No apostar en evento empezado/finalizado | Comprobación en `PlaceBetHandler` contra `SportEvent::status`/`startsAt`, lanza `EventAlreadyStarted` |
| 6 | Transiciones válidas de `Bet` | `Bet::settle()` valida `placed → won|lost`, lanza `InvalidBetTransition` |
| 7 | Settlement idempotente | `Bet::settle()` falla si ya no está `placed` + comprobación de `SportEvent` ya liquidado en `SettleEventHandler` |
| 8 | Débito + apuesta atómicos con lock | `PlaceBetHandler`: transacción + `WalletRepository::findByUserIdForUpdate()` |
| 9 | Dominio no conoce API-Sports | `SportsDataProviderInterface` (cuando se implemente) + adapters en Infrastructure — fuera de alcance en P0 |
| 10 | Front/casos de uso leen de BD propia | Ya se cumple por diseño: `Application` solo habla con repos de `Domain`, nunca con HTTP externo |

---

## 5. Orden recomendado de implementación (test-first)

Siguiendo la regla de CLAUDE.md de test-first en el dominio:

1. `Shared`: `Id`, `DomainException` (sin tests propios, son la base).
2. `Wallet/Domain`: test de `Coins` (invariantes 1 y 3) → implementar `Coins` → test de `Wallet`/`LedgerEntry` (invariante 2) → implementar.
3. `Wallet/Application` + `Infrastructure` + migraciones → test Feature de abrir wallet y consultar saldo.
4. `Betting/Domain`: test de `Odds` → test de `Bet::settle()` (invariantes 4, 6) → implementar.
5. `Betting/Application` (`PlaceBet`) + migraciones + seeders de `SportEvent`/`Market`/`Selection` → test Feature de `PlaceBet`, incluida la concurrencia (invariante 8).
6. `SettleEvent` → test Feature de idempotencia (invariante 7).
7. `app/Listeners/OpenWalletOnUserRegistered` al final, una vez `OpenWalletHandler` ya tiene tests — es solo cablear el evento de Laravel al caso de uso.

Con esto tienes vía libre para ir implementando P0 archivo a archivo; si quieres, luego seguimos con el primer paso (composer.json + `Id`/`DomainException`) cuando estés listo.
