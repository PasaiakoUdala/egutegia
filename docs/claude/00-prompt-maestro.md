# Prompt maestro — Egutegia API (Symfony 7 + API Platform 4)

> **Instrucciones de uso:** Pega este fichero entero como primer mensaje en una sesión Claude Code sobre el repositorio nuevo. Adjunta los ficheros numerados (01–10) de este directorio como contexto adicional según la fase. Trabaja fase a fase según `09-plan-fases.md`.

---

## Contexto del proyecto

Estás construyendo **Egutegia**, el sistema de gestión de calendarios laborales y solicitudes de permisos del Ayuntamiento de Pasaia (Gipuzkoa). La aplicación original es un monolito Symfony 3.4 / PHP 7 en fin de vida. Tu tarea es desarrollar la versión moderna desde cero como una **API REST** que otras aplicaciones internas pueden consumir y escribir.

Todo el dominio está en **euskera** (Basque). Mantén los nombres de entidades, campos y rutas tal como aparecen en la documentación — son términos institucionales, no variables de ejemplo.

### Stack destino

| Capa | Tecnología |
|------|-----------|
| Framework | **Symfony 7.2+** (Flex, sin AppBundle) |
| API | **API Platform 4** (recursos declarativos, DTOs, State Processors/Providers) |
| PHP | **8.3+** (atributos nativos, enums, readonly, named args) |
| Base de datos | **PostgreSQL 15+** vía Doctrine ORM 3.x + **doctrine/migrations** (siempre) |
| Auth | **OIDC/OAuth2 resource server** — bundle SSO propio `[PLACEHOLDER_SSO_BUNDLE]` |
| Email | **Symfony Mailer** + plantillas Twig |
| Async | **Symfony Messenger + Scheduler** (sustituye cron) |
| Ficheros | **FlySystem** o servicio propio (sin VichUploader) |
| PDF | **KnpSnappyBundle** o **Gotenberg** (evaluar) |
| Tests | **PHPUnit 11 + ApiTestCase** de API Platform |
| Docker | `compose.yaml` con servicios: php-fpm, nginx, postgres, redis, mailer (MailHog) |
| Código | Namespace `App\`, estructura `src/{Domain,Application,Infrastructure,Api}` |

---

## Principios de desarrollo

1. **Lógica fuera de controllers y State Processors.** Toda la lógica de negocio vive en servicios de dominio bajo `src/Domain/` o `src/Application/`. Los Processors/Providers de API Platform son adaptadores delgados.

2. **Migraciones siempre.** Ningún cambio de esquema se aplica con `doctrine:schema:update`. Cada cambio genera una migración en `migrations/`.

3. **PHP 8.3 idiomático.** Usa atributos nativos (`#[ORM\Entity]`, `#[Route]`, `#[ApiResource]`, `#[Assert\*]`), enums para estados (`EskaeraState`), readonly para DTOs y value objects, tipos de unión cuando proceda.

4. **API Platform declarativa.** Preferir configuración por atributos en los API Resources. Usar DTOs de entrada/salida para operaciones que no mapean 1:1 a la entidad. Usar filtros de API Platform para paginación y filtrado antes de añadir lógica custom.

5. **Tests obligatorios.** Cada State Processor y servicio de dominio lleva tests. Los endpoints llevan tests funcionales con `ApiTestCase`.

6. **Sin IDs hardcodeados en el código.** Los identificadores de negocio (`Type.labur`, `Lizentziamota.kodea`) se usan como claves estables; jamás se compara `entity.id === 5` en lógica de negocio.

7. **Seguridad por capas.** OIDC valida el token; `security.yaml` configura el firewall; Voters controlan acceso por recurso/operación.

---

## Referencia rápida de dominio

Ver `03-modelo-datos.md` para el catálogo completo y el glosario euskera ↔ dominio.  
Entidades principales: `User`, `Calendar`, `Event`, `Type`, `Template`, `TemplateEvent`, `Eskaera`, `Firma`, `Firmadet`, `Sinatzaileak`, `Sinatzaileakdet`, `Notification`, `Lizentziamota`, `Saila`, `Taldea`, `Gutxienekoak`, `Gutxienekoakdet`, `Kuadrantea`, `Document`, `Hour`, `Message`, `Log`, `Ikastaroa`.

---

## Operaciones de negocio críticas (no olvidar)

Estas operaciones existen en el sistema actual y deben migrar. Ver `04-endpoints-api.md` y `05-logica-negocio.md` para detalle.

| Operación | Descripción |
|-----------|-------------|
| `PATCH /eskaerak/{id}/firmar` | Firma/aprueba un paso del workflow (unifica `putFirma` + `putPostit`) |
| `PATCH /eskaerak/{id}/jakinarazpena` | Procesa notificación/aprobación, avanza la cadena de firmas |
| `PATCH /eskaerak/{id}/egutegira-gehitu` | Marca eskaera como añadida al calendario y crea los Events correspondientes |
| `PATCH /eskaerak/{id}/bertan-behera` | Cancela una solicitud |
| `POST /eskaerak/{id}/transfer` | Reasigna solicitud/notificaciones a otro usuario |
| `GET /kuadrantea/{year}/{month}` | Grid mensual por departamento/equipo |
| `GET /reports/absentismo` | Informe de absentismo |
| `GET /reports/konpentsatuak` | Informe de horas compensadas |
| `GET /reports/balance-anual` | Balance anual por calendario |
| `GET /eskaerak/{id}/pdf` | Generación de PDF de una solicitud |

---

## Workflows de negocio a preservar

1. **Contabilidad de horas** (`CalendarService::addEvent`): al crear un `Event`, descontar horas del bucket correcto del `Calendar` (`hours_free`, `hours_self`, `hours_self_half`, `hours_compensed`). Ver `05-logica-negocio.md`.

2. **Workflow de firmas**: cadena ordenada de firmantes (`Sinatzaileakdet`). Cada firma crea un `Firmadet` y avanza al siguiente firmante con `Notification`. Al completarse: `Eskaera.state → AMAITUA`, email "maika" si `Type.labur === 'IKA'`, email de rechazo a bideratzaileak si no aprobado.

3. **Detección de conflictos**: al crear `Eskaera`, verificar solapamiento de fechas contra `Event`s y `Eskaera`s existentes, y las reglas de `Gutxienekoak` (mínimos de plantilla). Marcar `konfliktoa = true` + nota en `oharra`, no bloquear.

4. **Email maika/IKA**: cuando `Eskaera` con `Type.labur === 'IKA'` es completamente aprobada, enviar email a `maika@pasaia.net` con datos del curso y adjunto `ordainketaFile`.

---

## Riesgos del legacy a no reproducir

Ver `01-analisis-proyecto-actual.md` para contexto completo.

- ❌ No usar IDs numéricos hardcodeados en lógica de negocio.
- ❌ No incrustar SQL nativo con nombres de tabla hardcodeados en repositorios.
- ❌ No llamar a la propia API vía HTTP (la autollamada Guzzle desaparece; usar el servicio directamente).
- ❌ No tener lógica de cálculo de horas en controllers.
- ❌ No olvidar la inversión del mapping en `User ↔ Kuadrantea` (el legacy tenía un bug aquí).

---

## Estructura de directorios objetivo

```
src/
├── Api/                          # API Platform resources, DTOs, Processors, Providers, Filters
│   ├── Resource/                 # #[ApiResource] classes
│   ├── Dto/                      # Input/Output DTOs
│   ├── Processor/                # State Processors
│   ├── Provider/                 # State Providers
│   └── Filter/                   # Custom API Platform filters
├── Domain/                       # Entidades, repos (interfaces), value objects, enums, domain services
│   ├── Calendar/
│   ├── Eskaera/
│   ├── Firma/
│   ├── User/
│   └── ...
├── Application/                  # Application services, commands de negocio (no Symfony Console)
├── Infrastructure/               # Doctrine repos (implementaciones), Mailer, Storage, SSO bridge
│   ├── Doctrine/
│   ├── Mailer/
│   ├── Storage/
│   └── Sso/
└── Scheduler/                    # Symfony Scheduler + Messenger handlers
migrations/
config/
tests/
```

---

## Cómo trabajar por fases

Ejecuta las fases en orden. Antes de pasar a la siguiente, los criterios de aceptación de `09-plan-fases.md` deben estar en verde.

- **F0** — Bootstrap: composer create-project, Docker, Postgres, migraciones base.
- **F1** — Entidades Doctrine + SSO OIDC (firewall, roles, Voter base).
- **F2** — CRUD core API Platform: `Calendar`, `Event`, `Type`, `Template`.
- **F3** — Workflow `Eskaera` + firmas + notificaciones + emails.
- **F4** — Grids `Kuadrantea`, reports, PDFs, commands/Scheduler.
- **F5** — Migración de datos MySQL → Postgres + verificación.
- **F6** — SPA Vue 3 (repo/carpeta separado, ver `10-spa-frontend.md`).

---

## Ficheros de contexto adicionales

Adjunta estos ficheros a la sesión de Claude Code según la fase:

- `01-analisis-proyecto-actual.md` — siempre útil como referencia del legacy.
- `02-arquitectura-objetivo.md` — F0, F1.
- `03-modelo-datos.md` — F1.
- `04-endpoints-api.md` — F2, F3, F4.
- `05-logica-negocio.md` — F3.
- `06-autenticacion-seguridad.md` — F1.
- `07-migracion-datos.md` — F5.
- `08-comandos-cron-emails-ficheros.md` — F4.
- `09-plan-fases.md` — control de progreso.
- `10-spa-frontend.md` — F6.
