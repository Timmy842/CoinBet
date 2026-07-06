# CoinBet 🪙

Simulador de apuestas deportivas con **moneda 100% virtual**, construido como proyecto de aprendizaje y portfolio. Sin dinero real, sin pasarelas de pago — solo el dominio técnico de una plataforma de apuestas: ledger de movimientos, ciclo de vida de apuestas, settlement idempotente y datos deportivos reales.

> ⚠️ **Proyecto educativo.** Todas las monedas son ficticias. No es un producto de juego real ni pretende serlo.

## Stack

- **API:** PHP 8.3 · Laravel 12 · MySQL 8 · Pest — arquitectura hexagonal / DDD
- **Web:** React 19 · TypeScript · Vite · TanStack Query · Zustand · Tailwind
- **Datos deportivos:** [API-Sports](https://api-sports.io) (fútbol; MMA planificado)
- **Infra:** Docker Compose · GitHub Actions

## Arranque rápido

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
cd web && npm install && npm run dev
```

- API: http://localhost:8000
- Web: http://localhost:5173

## Arquitectura

_(Sección en construcción: bounded contexts, lenguaje ubicuo, decisiones justificadas — ledger inmutable, cuota congelada al apostar, settlement idempotente, anti-corruption layer sobre el proveedor de datos.)_

## Estructura

```
coinbet/
├── api/        # Laravel — dominio, casos de uso, API REST
├── web/        # React SPA
├── docker/     # Configuración nginx
└── .github/    # CI
```

## Estado

🚧 En desarrollo. Roadmap: monedero (ledger) → apuestas 1X2 → settlement → integración API-Sports → SPA.
