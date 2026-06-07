# 01 — Análisis del proyecto actual (legacy)

Este documento es una **fotografía técnica** del sistema legado `egutegia`. Sirve como referencia para entender la API existente (formas de request/response), la lógica de negocio a portar, y los riesgos a evitar.

---

## Stack real (a fecha de análisis)

| Dimensión | Valor |
|-----------|-------|
| Symfony | **3.4** (monolito `symfony/symfony`, sin Flex) |
| PHP | ≥ 7.0 (no compatible con PHP 8.1+ sin trabajo mayor) |
| Layout | Legacy `app/config/` + `src/AppBundle/` + `src/ApiBundle/` |
| ORM | Doctrine ORM 2.7, **auto_mapping**, anotaciones (no atributos PHP 8) |
| BD | Config hardcoded a **MySQL** (`pdo_mysql`), pero `.env` tiene DSN Postgres sin usar |
| Auth | Dual: **FOSUserBundle 2.1** (DB local) + **fr3d/ldap-bundle** (Active Directory) |
| API | **FOSRestBundle 2.8** + **JMSSerializerBundle 2.x** + **NelmioApiDocBundle 2.13** |
| Frontend | **Twig 2** + **Gulp 3** + **Bower** + Bootstrap 3 |
| Email | **SwiftMailer** (abandonado) |
| Ficheros | **VichUploaderBundle 1.8** |
| PDF | **KnpSnappyBundle** (wkhtmltopdf) |
| Cron | Sin Messenger; commands `bin/console app:*` en crontab del servidor |
| Cache/Sesión | **Redis** (snc/redis-bundle) |
| Despliegue | Deployer + Ansistrano, Docker Compose |

---

## Bundles / paquetes en fin de vida

| Bundle actual | Estado | Reemplazo |
|---|---|---|
| `symfony/symfony` (monolito) | EOL Nov 2021 | Componentes individuales Symfony 7 |
| `friendsofsymfony/user-bundle` 2.1 | Abandonado | Entidad User propia + Security nativa |
| `friendsofsymfony/rest-bundle` | Deprecado | API Platform 4 |
| `jms/serializer-bundle` | Sustituible | Serializer Symfony + API Platform |
| `nelmio/api-doc-bundle` v2 | Muy obsoleto (v2 = Swagger 1) | API Platform genera OpenAPI 3 |
| `sensio/framework-extra-bundle` | Deprecado | Atributos nativos PHP 8 |
| `sensio/distribution-bundle` | Abandonado | Flex |
| `sensio/generator-bundle` | Abandonado | `make:` commands de MakerBundle |
| `fr3d/ldap-bundle` + `ldaptools/ldaptools-bundle` | Específico Symfony 3 | Bundle SSO propio (OIDC) |
| `symfony/swiftmailer-bundle` | Abandonado | `symfony/mailer` |
| `mopa/bootstrap-bundle` dev-master | Inestable | Sin equivalente (SPA asume su propia UI) |
| `fos/js-routing-bundle` | — | Innecesario con API headless |
| `fos/ckeditor-bundle` | — | Innecesario |
| `eightpoints/guzzle-bundle` | — | `symfony/http-client` (o eliminar autollamada) |
| `composer/package-versions-deprecated` | Deprecado | `symfony/runtime` |
| Frontend: Gulp 3, Bower, node-sass | Todo EOL | Vue 3 + Vite (SPA separada) |

---

## Estructura de controllers (24 controllers en `src/AppBundle/Controller/`)

### Dominio principal

| Controller | Prefijo ruta | Responsabilidad clave |
|---|---|---|
| `EskaeraController` | `/eskaera` | CRUD solicitudes, detección conflictos, addToCalendar, cancel, PDF, transfer, IKA sub-gestión, kuadrantea-eskaerekin |
| `CalendarController` | `/admin/calendar` | CRUD calendarios anuales por empleado |
| `EgutegiaController` | `/egutegia/{username}` | Vista de calendario por usuario |
| `EventController` | `/admin/event` | CRUD de eventos/días del calendario |
| `DefaultController` | `/`, `/mycalendar`, `/saila/*` | Dashboard usuario, documentos, comparativas, vistas jefe de dept |
| `AdminController` | `/admin` | Dashboard admin, kuadrantea maestro, balance anual, print |

### Workflow de firmas

| Controller | Prefijo | Responsabilidad |
|---|---|---|
| `NotificationController` | `/notification` | Bandeja de firmas, transfer, signing, list |
| `FirmaController` | `/admin/firm` | CRUD proceso firma |
| `FirmadetController` | `/erantzunak` | CRUD línea de firma individual |
| `SinatzaileakController` | `/admin/sinatzaileak` | CRUD grupos de firmantes |
| `SinatzaileakdetController` | `/admin/sinatzaileakdet` | Miembros del grupo, reordenar (up/down AJAX) |

### Master data / configuración

| Controller | Prefijo | Responsabilidad |
|---|---|---|
| `TypeController` | `/admin/type` | CRUD tipos de evento/permiso |
| `TemplateController` | `/admin/template` | CRUD plantillas de calendario + copy |
| `LizentziamotaController` | `/admin/lizentziamota` | CRUD tipos de licencia |
| `GutxienekoakController` | `/admin/gutxienekoak` | CRUD grupos de mínimos (incompatibilidad) |
| `GutxienekoakdetController` | `/admin/gutxienekoakdet` | Miembros del grupo de mínimos |
| `SailaController` | `/admin/saila` | CRUD departamentos, quitar usuario |
| `TaldeaController` | `/admin/taldea` | CRUD equipos, concejalías, asignaciones |
| `UserController` | `/admin/user` | Alta/baja, set-sailburua, toggle-active, lista eskaerak |
| `HourController` | `/admin/hour` | CRUD bloques de horas extra |
| `DocumentController` | `/admin/document` | CRUD documentos adjuntos, reordenar |
| `LogController` | `/admin/log` | Visor log de auditoría |
| `MessageController` | `/admin/message` | Mensajes broadcast, delete |
| `ZerrendaController` | `/zerrendak` | Informes: absentismo, konpentsatuak |

### ApiBundle (FOSRest JSON — `/api/`)

> **Este bundle es la referencia canónica para formas de request/response.** El nuevo sistema debe ofrecer operaciones equivalentes.

| Endpoint | Método | Acción |
|---|---|---|
| `/api/template/{id}` | GET | Devuelve plantilla + templateevents |
| `/api/templateevents/{templateid}` | GET | Eventos de una plantilla |
| `/api/templateevents` | POST/DELETE | Añadir/quitar template event |
| `/api/events/{calendarid}` | GET | Eventos de un calendario |
| `/api/events/{id}` | PUT/POST/DELETE | Gestión de evento |
| `/api/notes/{calendarid}` | POST | Nota en calendario |
| `/api/usernotes/{username}` | POST | Nota de usuario |
| `/api/postit/{id}/{userid}` | PUT | Firma vía post-it (== `/api/firma/{id}` funcionalmente) |
| `/api/firma/{id}` | PUT | Firma/aprobación de un paso de workflow |
| `/api/eskaeraegutegian/{id}` | PUT | Marcar eskaera como añadida al calendario |
| `/api/jakinarazpenareaded/{id}` | PUT | Marcar notificación como leída |
| `/api/jakinarazpena/{id}` | PUT | Procesar notificación (aprobar/rechazar) |
| `/api/firmatzaileak/{eskaeraid}` | GET | Firmantes de una eskaera |
| `/api/firmatzaileakfromjakinarazpena/{id}` | GET | Firmantes desde notificación |
| `/api/lizentziamota/{id}` | GET | Tipo de licencia |
| `/api/calendars/{username}` | GET | Calendarios de un usuario |
| `/api/messages` | GET/POST/DELETE | Mensajes (MessageController) |

**Nota clave:** `putFirmaAction` y `putPostitAction` son funcionalmente idénticos (el código tiene un comentario `// OHARRA: berdin egon behar dira`) — se unifican en un único endpoint en el sistema nuevo.

**Autollamada Guzzle interna:** `EskaeraController` llama a `PUT /api/firma/{id}` vía Guzzle HTTP (cliente `api_put_firma`, `api_url = http://localhost/app_dev.php/api`). En el sistema nuevo esta llamada desaparece — el controller llama directamente al servicio de dominio `FirmaService::procesarFirma()`.

---

## Entidades (28 en `src/AppBundle/Entity/`)

Ver `03-modelo-datos.md` para el catálogo completo con relaciones y mejoras propuestas.

Lista: `User`, `Calendar`, `Event`, `EventHistory`, `Type`, `Template`, `TemplateEvent`, `Eskaera`, `Firma`, `Firmadet`, `Sinatzaileak`, `Sinatzaileakdet`, `Notification`, `Lizentziamota`, `Saila`, `Taldea`, `Hour`, `Document`, `Message`, `Log`, `Ikastaroa`, `Gutxienekoak`, `Gutxienekoakdet`, `Kuadrantea`, `KuadranteaEskaerekin`, `TempEskaerakEgutegian`.

---

## Riesgos detectados (a no reproducir)

### 🔴 Críticos

**1. IDs hardcodeados en repositorios y servicios**
```php
// EskaeraRepository.php
$query->andWhere('t.id = 5'); // "orduak" type hardcoded

// EventRepository.php
->where('e.type = 5')  // type=5 significa "norberarentzako" 

// KuadranteaEskaerekinRepository.php (~2024-07-08)
// Hack: fuerza username='acuevas' al departamento 3
```
➡ **Solución:** usar `Type.labur` como código estable (`'OPO'`, `'NAE'`, `'IKA'`, etc.). Jamás comparar por ID numérico.

**2. Autollamada HTTP Guzzle**
- `EskaeraController` abre una conexión HTTP a sí mismo para ejecutar la lógica de firma.
- Causa: lógica de negocio atrapada en el `ApiController` en vez de en un servicio.
- ➡ **Solución:** `FirmaService::procesarFirma($eskaera, $user)` llamado directamente.

**3. Sin migraciones Doctrine**
- El esquema se gestiona vía `doctrine:schema:update` o a mano.
- ➡ **Solución:** toda la nueva app usa `doctrine/migrations`; primera migración generada desde la entidad base.

### 🟡 Importantes

**4. Bug de mapping ORM en `User ↔ Kuadrantea`**
- `Kuadrantea.user` y `KuadranteaEskaerekin.user` declaran `inversedBy="kuadranteak"` pero `User` no tiene `$kuadranteak`. Hay un mapping warning suprimido silenciosamente.
- ➡ **Solución:** añadir `#[ORM\OneToMany(targetEntity: Kuadrantea::class, mappedBy: 'user')]` en `User`, o reconsiderar si estas tablas deben ser entidades Doctrine (son denormalizadas/cachés).

**5. `User::$zinegotziSailak` sin anotación ORM**
- La relación ManyToMany `User ↔ Saila` (concejalías) está referenciada en métodos pero la anotación ORM está incompleta/ausente.
- ➡ **Solución:** mapear explícitamente en el nuevo modelo o eliminar si no se usa.

**6. Mismatch MySQL / PostgreSQL**
- `app/config/config.yml` tiene `driver: pdo_mysql` hardcoded. El `.env` tiene un DSN Postgres que nunca fue cableado.
- ➡ **Solución:** nueva app usa Postgres desde el primer commit; ver `07-migracion-datos.md`.

**7. Estado de `Eskaera` como 8 flags booleanos**
- `abiatua`, `bertanbehera`, `bideratua`, `amaitua`, `egutegian`, `konfliktoa`, `emaitza`, `justifikatua` son flags independientes que en realidad modelan una máquina de estados.
- Combinaciones incoherentes son posibles (e.g., `amaitua=true` y `bertanbehera=true` simultáneamente).
- ➡ **Solución:** `EskaeraState` enum PHP 8 + columna `state`. Ver `03-modelo-datos.md`.

### 🟢 Menor

**8. Ficheros `.php~` y `.cache` en el repositorio**
- Hay archivos de editor (`*.php~`) en `src/AppBundle/Entity/` y artefactos (`.php_cs.cache`, `yarn-error.log`) en raíz.
- ➡ **Solución:** no heredar en el nuevo repo; `.gitignore` correcto desde el inicio.

**9. Rol con typo**
- El config de seguridad define `ROLE_BIDERATZAILE` en la jerarquía pero `access_control` requiere `ROLE_BIDERATZAILEA` (con 'A' final).
- ➡ **Solución:** estandarizar como `ROLE_BIDERATZAILEA` en el nuevo sistema.

**10. NotifyCommand con redirección hardcodeada**
- `igomez@pasaia.net` → `atorrado@pasaia.net` está en el código fuente.
- ➡ **Solución:** configuración en `services.yaml` / parámetros de entorno.

---

## Lógica de negocio a portar (resumen)

Ver `05-logica-negocio.md` para detalle completo.

| Servicio actual | Dónde está | A mover en el nuevo sistema |
|---|---|---|
| Contabilidad de horas | `CalendarService::addEvent` | `Domain/Calendar/CalendarHourService` |
| Workflow de firmas | `ApiController::putFirmaAction` | `Domain/Firma/FirmaWorkflowService` |
| Email maika/IKA | `ApiController::putFirmaAction` / `putPostitAction` | `Application/Eskaera/IkastaroaApprovedHandler` |
| Email rechazo | `ApiController::putJakinarazpenaAction` | `Application/Firma/RejectionEmailHandler` |
| Detección conflictos | `EskaeraController::newAction` | `Domain/Eskaera/ConflictDetectorService` |
| Rebuild kuadrantea | Commands `app:kuadrantea*` | `Scheduler/KuadranteaRebuildHandler` |
| Sincronizar eskaerak calendario | `AppCheckEskaerakEgutegianCommand` | `Scheduler/EskaerakEgutegianSyncHandler` |

---

## Configuración de roles (referencia)

```
ROLE_SUPER_ADMIN → ROLE_ADMIN, ROLE_BIDERATZAILEA
ROLE_ADMIN → ROLE_USER
ROLE_SAILBURUA → ROLE_ALLOWED_TO_SWITCH
ROLE_BIDERATZAILEA → ROLE_ALLOWED_TO_SWITCH
ROLE_ARDURADUNA → ROLE_ALLOWED_TO_SWITCH
Roles adicionales: ROLE_UDALTZAINA, ROLE_UDALTZAINGOA, ROLE_IKUSI_SAILBURUEN_KUADRANTEA
```

---

## Tests existentes

- `src/AppBundle/Tests/` y `src/ApiBundle/Tests/` — carpetas presentes pero con cobertura mínima o nula (PHP 7 / PHPUnit 6.1).
- No hay tests funcionales de la API.
- ➡ **El nuevo sistema empieza con tests desde F1.**
