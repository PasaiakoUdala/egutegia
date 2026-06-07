# 09 — Roadmap de implementación por fases

## Principios del roadmap

- Cada fase debe dejar la aplicación en un **estado funcional y testable** antes de pasar a la siguiente.
- Las fases F0–F4 generan la nueva API desde cero (sin base de datos real de producción).
- La fase F5 es la migración de datos y puesta en producción.
- La fase F6 (SPA) es independiente y puede solaparse con F3–F4.

---

## F0 — Bootstrap del proyecto (≈ 1 semana)

### Objetivo
Repositorio nuevo, entorno Docker operativo, primera migración Doctrine, CI básico.

### Tareas

- [ ] `composer create-project symfony/skeleton egutegia-api`
- [ ] Instalar dependencias core: `api-platform/api-platform`, `doctrine/orm`, `doctrine/doctrine-bundle`, `doctrine/doctrine-migrations-bundle`, `nelmio/cors-bundle`, `symfony/mailer`, `symfony/messenger`, `symfony/scheduler`
- [ ] Configurar `compose.yaml` con php, nginx, postgres, redis, mailpit, gotenberg
- [ ] Configurar `.env` con todas las variables (DATABASE_URL, MAILER_DSN, CORS_ALLOW_ORIGIN, etc.)
- [ ] Crear `Makefile` con targets: `up`, `down`, `sh`, `migrate`, `test`, `lint`, `cs-fix`
- [ ] Configurar PHPUnit 11
- [ ] Configurar PHP CS Fixer + PHPStan nivel 8
- [ ] Crear la primera migración vacía (baseline) y ejecutarla
- [ ] CI/CD: workflow básico (GitHub Actions o GitLab CI) que corra lint + tests

### Criterios de aceptación F0

- [ ] `make up` levanta todos los servicios sin errores.
- [ ] `make migrate` ejecuta sin errores (migración vacía baseline).
- [ ] `GET http://localhost:8080/api` devuelve el JSON-LD de API Platform.
- [ ] `make test` pasa (sin tests todavía, pero PHPUnit arranca).
- [ ] `make lint` pasa sin warnings.

---

## F1 — Entidades Doctrine + autenticación OIDC (≈ 2 semanas)

### Objetivo
Todas las entidades del dominio creadas con atributos PHP 8, relaciones correctas, migraciones generadas, y el SSO OIDC funcionando.

### Tareas

**Entidades (en orden de dependencias):**
- [ ] `Saila`, `Taldea` (sin relaciones aún)
- [ ] `User` (con relaciones a Saila/Taldea, roles, flags)
- [ ] `Type` (con campo `labur` único)
- [ ] `Lizentziamota` (con campo `kodea` único)
- [ ] `Template`, `TemplateEvent`
- [ ] `Calendar` (con relación a User y Template)
- [ ] `Event`, `Hour`
- [ ] `Sinatzaileak`, `Sinatzaileakdet`
- [ ] `Gutxienekoak`, `Gutxienekoakdet`
- [ ] `EskaeraState` enum PHP 8
- [ ] `Eskaera` (con todas las relaciones y el campo `state`)
- [ ] `Firma`, `Firmadet`
- [ ] `Notification`, `Document`, `Message`, `Log`, `Ikastaroa`
- [ ] `Kuadrantea`, `KuadranteaEskaerekin`
- [ ] Corregir `User ↔ Kuadrantea` inversedBy (bug del legacy)
- [ ] Corregir `User::$zinegotziSailak` ManyToMany con Saila

**Migraciones:**
- [ ] Generar migración completa desde las entidades
- [ ] Ejecutar y verificar esquema en Postgres

**Auth OIDC:**
- [ ] Instalar `[PLACEHOLDER_SSO_BUNDLE]`
- [ ] Implementar `OidcAuthenticator`
- [ ] Implementar `OidcUserProvider` con `loadOrCreateUser`
- [ ] Configurar `security.yaml` (firewall `api` stateless, role_hierarchy)
- [ ] Implementar `CalendarVoter`, `EskaeraVoter`, `NotificationVoter` (versiones iniciales)

**Fixtures:**
- [ ] Seed de `Type` con los 7 tipos base (labur: OPO, NAE, KON, SIN, IKA, AZT, MUN)
- [ ] Seed de `Template` base

### Criterios de aceptación F1

- [ ] `make migrate` ejecuta las ~20 migraciones de entidades sin errores.
- [ ] `GET /api` lista todos los recursos API Platform registrados.
- [ ] Un request con token OIDC válido devuelve 200; sin token devuelve 401.
- [ ] `bin/console doctrine:schema:validate` no reporta errores.
- [ ] Tests unitarios de `EskaeraState` enum pasan.

---

## F2 — CRUD core API Platform (≈ 2 semanas)

### Objetivo
Recursos API Platform funcionales para el master data y los calendarios.

### Tareas

- [ ] `UserResource` — GET list, GET item, PATCH (admin), alta/baja custom operations
- [ ] `SailaResource`, `TaldeaResource` — CRUD completo
- [ ] `TypeResource` — CRUD + filtro por template
- [ ] `LizentziamotaResource` — CRUD
- [ ] `TemplateResource` + custom operation `/copy`
- [ ] `TemplateEventResource` — CRUD
- [ ] `CalendarResource` — CRUD + Voter + filtro por user/year
- [ ] `CalendarHourService` — implementación completa con tests
- [ ] `EventResource` — CRUD + State Processor que llama `CalendarHourService`
- [ ] `HourResource` — CRUD
- [ ] `DocumentResource` — CRUD + upload (Flysystem) + reorder
- [ ] `MessageResource` — CRUD

**Serialization:**
- [ ] Definir grupos `read`/`write` para todos los resources
- [ ] Asegurar que las IRIs son coherentes (no exponer datos sensibles)

**Tests:**
- [ ] Tests funcionales `ApiTestCase` para Calendar: list, create, edit, delete
- [ ] Tests de `CalendarHourService` (todos los buckets, errores de saldo)

### Criterios de aceptación F2

- [ ] CRUD de `Calendar`, `Event`, `Type`, `Template` testado y pasando.
- [ ] El descuento de horas al crear un `Event` funciona correctamente para los 5 tipos de bucket.
- [ ] La documentación OpenAPI en `/api/docs` es completa y correcta.
- [ ] Filtros de paginación y campos funcionan en todos los list endpoints.
- [ ] Los Voters bloquean acceso no autorizado (tests de seguridad incluidos).

---

## F3 — Workflow Eskaera + firmas + notificaciones + emails (≈ 3 semanas)

### Objetivo
El flujo completo de solicitud → firma → aprobación/rechazo, con emails.

### Tareas

**Eskaera:**
- [ ] `EskaeraResource` — GET list/item, POST (con Input DTO, con `ConflictDetectorService`)
- [ ] Filtros de Eskaera: state, user, type, lizentziamota, fechas, bideratua, konfliktoa
- [ ] Custom operation `bertan-behera` (cancelar)
- [ ] Custom operation `egutegira-gehitu` + `AddToCalendarUseCase`
- [ ] Upload de justifikante (`POST /api/eskaerak/{id}/justifikantea`)
- [ ] Custom operation `transfer`

**Firma workflow:**
- [ ] `SinatzaileakResource`, `SinatzaileakdetResource` (con reorder up/down)
- [ ] `FirmaResource` (solo lectura + ver firmantes)
- [ ] `FirmaWorkflowService` — implementación completa con tests
- [ ] Custom operation `firmar` en EskaeraResource (State Processor)
- [ ] `ConflictDetectorService` — implementación completa con tests

**Notificaciones:**
- [ ] `NotificationResource` — list, view, marcar-leída, transfer
- [ ] Custom operation `jakinarazpena` (procesar aprobación/rechazo)
- [ ] `ProcessarJakinarazpenaProcessor`

**Emails y Messenger:**
- [ ] Configurar Messenger transports (async + dead_letter)
- [ ] `IkastaroaApprovedMessage` + `IkastaroaApprovedHandler`
- [ ] `RejectionEmailMessage` + `RejectionEmailHandler`
- [ ] Plantillas Twig: `ikastaroa_onartua.html.twig`, `eskaera_ukatua.html.twig`
- [ ] Workers en Docker (messenger_worker service)

**PDFs:**
- [ ] Integrar Gotenberg (o KnpSnappy)
- [ ] Custom operations: `/eskaerak/{id}/pdf`, `/ikastaroa-pdf`, `/ordainketa-pdf`
- [ ] Plantillas PDF: `templates/pdf/eskaera.html.twig`, `ikastaroa.html.twig`, `ordainketa.html.twig`

### Criterios de aceptación F3

- [ ] Test end-to-end del workflow completo:
  1. POST /api/eskaerak → crea Eskaera en state=ABIATUA con Firma
  2. GET /api/notifications → el primer firmante ve la notificación
  3. PATCH /api/eskaerak/{id}/firmar → firma el paso 1
  4. (Si hay más firmantes) → notificación al siguiente
  5. PATCH /api/eskaerak/{id}/firmar → firma el paso final → state=ONARTUA
  6. Si type.labur='IKA' → mensaje en cola + email enviado a maika@
  7. PATCH /api/eskaerak/{id}/egutegira-gehitu → state=EGUTEGIAN, Events creados
- [ ] Test de rechazo: state=UKATUA, email a bideratzaileak.
- [ ] Test de detección de conflictos: eskaera con confliktoa=true.
- [ ] PDF generado correctamente para Eskaera de prueba.
- [ ] Emails capturados en Mailpit (dev).

---

## F4 — Grids, reports, commands y Scheduler (≈ 2 semanas)

### Objetivo
Kuadrantea, informes, y tareas automatizadas.

### Tareas

**Kuadrantea:**
- [ ] `KuadranteaResource` — Provider custom para grids
- [ ] Endpoints: `/{year}/{month}`, `/{year}/{month}/eskaerekin`, `/{year}/{month}/sailburuarentzat`
- [ ] `KuadranteaVoter`
- [ ] `RebuildKuadranteaCommand` (manual)
- [ ] `KuadranteaRebuildHandler` (Scheduler + Messenger)

**Reports:**
- [ ] `GET /api/reports/absentismo` — Provider custom
- [ ] `GET /api/reports/konpentsatuak` — Provider custom
- [ ] `GET /api/reports/balance-anual` — Provider custom

**Commands y Scheduler:**
- [ ] `NotifyPendingSignaturesHandler` + Scheduler task
- [ ] `CheckEskaerakEgutegianHandler` + Scheduler task
- [ ] `EskaeraCompletedCheckHandler` + Scheduler task
- [ ] `TransferNotificationsCommand` (manual)
- [ ] `AssignRoleCommand` / `RemoveRoleCommand` (manual)
- [ ] `DeleteDuplicateNotificationsCommand` (manual)
- [ ] Plantilla email: `firma_pendiente.html.twig`, `eskaerak_egutegian_alerta.html.twig`
- [ ] Configurar redirects de email como parámetro (no hardcodeados)

**Log:**
- [ ] `LogResource` (solo GET, ROLE_ADMIN)
- [ ] Crear entradas de Log en operaciones críticas (firma, cancelación, egutegira-gehitu)

### Criterios de aceptación F4

- [ ] Grid del kuadrantea devuelve la estructura correcta para un mes con datos.
- [ ] Los 3 reports devuelven datos coherentes con los datos seed.
- [ ] `bin/console app:kuadrantea:rebuild --year=2025 --month=8` completa sin errores.
- [ ] El Scheduler arranca y ejecuta las tareas programadas (verificar logs).
- [ ] Los workers de Messenger procesan la cola sin errores.
- [ ] `bin/console messenger:stats` muestra 0 mensajes en dead_letter.

---

## F5 — Migración de datos y puesta en producción (≈ 1-2 semanas)

### Objetivo
Datos reales migrados de MySQL a PostgreSQL, servicio en producción.

### Tareas

- [ ] Preparar entorno de staging con datos reales (subset o completo)
- [ ] Ejecutar el script ETL (ver `07-migracion-datos.md`)
- [ ] Verificar integridad: recuentos, orphans, state consistency
- [ ] Migrar ficheros adjuntos a Flysystem (`storage/uploads/`)
- [ ] Smoke test funcional completo sobre datos reales
- [ ] Configurar entorno de producción (secrets, SSL, Nginx, workers)
- [ ] Ventana de mantenimiento: detener el legacy, migrar datos finales, activar el nuevo
- [ ] Monitorizar primeras 48h con logs y alertas

### Criterios de aceptación F5

- [ ] Recuentos de tablas: MySQL == PostgreSQL (tolerancia 0 filas de diferencia).
- [ ] `doctrine:schema:validate` sin errores en producción.
- [ ] 10 usuarios reales pueden autenticarse y ver sus calendarios.
- [ ] Una solicitud de prueba puede crearse, firmarse y añadirse al calendario end-to-end.
- [ ] Los Schedulers están corriendo y no hay mensajes en dead_letter.
- [ ] Los PDFs se generan correctamente con datos reales.

---

## F6 — SPA Vue 3 (segunda fase, ≈ 4-6 semanas)

### Objetivo
Interfaz de usuario moderna que consume la API.

Ver `10-spa-frontend.md` para especificación completa.

### Organización
La SPA vive en un **repositorio/carpeta separado**: `egutegia-app/` (o repo propio). No comparte código con el backend excepto los tipos TypeScript generados desde el OpenAPI.

### Tareas de alto nivel

- [ ] Bootstrap Vue 3 + TypeScript + Vite + Pinia + Vue Router
- [ ] Integración OIDC (autenticación desde el SPA)
- [ ] Cliente HTTP con interceptor de Bearer token
- [ ] Pantallas: ver `10-spa-frontend.md` §3

### Criterios de aceptación F6

Ver `10-spa-frontend.md` §6.

---

## Resumen de tiempos

| Fase | Descripción | Estimación |
|---|---|---|
| F0 | Bootstrap + Docker | 1 semana |
| F1 | Entidades + Auth OIDC | 2 semanas |
| F2 | CRUD core API Platform | 2 semanas |
| F3 | Eskaera + firma + emails | 3 semanas |
| F4 | Kuadrantea + reports + Scheduler | 2 semanas |
| F5 | Migración de datos + producción | 1-2 semanas |
| F6 | SPA Vue 3 | 4-6 semanas |
| **Total** | | **~15-18 semanas** |

Estimación para un desarrollador. Con dos puede paralelizarse F6 desde F3.
