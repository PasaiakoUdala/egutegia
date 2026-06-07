# Prompt maestro — Nueva aplicación de gestión de calendarios laborales

> **Cómo usar:** Pega este documento completo como primer mensaje en una sesión Claude Code sobre el repositorio vacío del nuevo proyecto. Trabaja fase a fase — no saltes adelante sin tener la fase anterior funcionando.

---

## Contexto y objetivo

Actúa como analista-programador senior. Vamos a construir desde cero una aplicación web de **gestión de calendarios laborales y solicitudes de permisos** para una organización pública.

La aplicación anterior era un monolito Symfony 3.4 obsoleto. Esta es una aplicación **completamente nueva**, sin migración de código ni de estructura del sistema anterior. Solo se migrarán los datos cuando la nueva aplicación esté operativa.

La aplicación debe funcionar correctamente en **escritorio y en móvil** (diseño responsive mobile-first). El 20% de los usuarios accede exclusivamente desde móvil.

---

## Stack tecnológico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Framework backend | **Symfony** | 7.2+ |
| API | **API Platform** | 4.x |
| PHP | — | 8.3+ |
| Base de datos | **PostgreSQL** | 15+ |
| ORM | **Doctrine ORM** | 3.x + doctrine/migrations (siempre) |
| Autenticación | **SSO OIDC/OAuth2** propio — resource server | — |
| Email | **Symfony Mailer** + plantillas Twig | — |
| Tareas asíncronas | **Symfony Messenger + Scheduler** | — |
| Almacenamiento de ficheros | **League Flysystem** | 3.x |
| PDF | **Gotenberg** (contenedor Docker) | 8.x |
| Tests backend | **PHPUnit** | 11 + ApiTestCase |
| Frontend framework | **Vue 3** | 3.4+ |
| Lenguaje frontend | **TypeScript** | 5.x (strict) |
| Build frontend | **Vite** | 5.x |
| Estado frontend | **Pinia** | 2.x |
| Router frontend | **Vue Router** | 4.x |
| UI components | **PrimeVue** | 4.x |
| Calendario UI | **FullCalendar** | 6.x |
| Validación frontend | **VeeValidate + Zod** | — |
| i18n | **vue-i18n** | 9.x |
| Tests frontend | **Vitest + Vue Test Utils** | — |
| Contenedores | **Docker + Docker Compose** | — |

### Repositorios

El proyecto se divide en **dos repositorios separados**:
- `calendar-api/` — backend Symfony (API Platform)
- `calendar-app/` — frontend Vue 3 SPA

### Infraestructura

- Todos los comandos se ejecutan **dentro de los contenedores Docker**. Nunca en el host.
- Para producción se construyen **imágenes Docker** que se suben a un registry privado y se despliegan en un VPS mediante `docker compose`.

```
# Ejecutar comandos dentro del contenedor:
docker compose exec php bin/console doctrine:migrations:migrate
docker compose exec php bin/phpunit
docker compose exec node npm run build
```

---

## Principios de desarrollo (no negociables)

1. **Migraciones siempre.** Cero `doctrine:schema:update`. Todo cambio de esquema genera una migración.
2. **Lógica fuera de controllers y Processors.** Los servicios de dominio encapsulan las reglas de negocio; los Processors/Providers son adaptadores delgados.
3. **PHP 8.3 idiomático.** Atributos nativos, enums, readonly, tipos estrictos. Sin anotaciones.
4. **Sin IDs hardcodeados.** Las referencias entre entidades usan códigos de negocio estables (campos `code` únicos), nunca IDs numéricos en lógica de negocio.
5. **Tests obligatorios** para todo servicio de dominio y State Processor.
6. **API stateless.** Sin sesiones en el backend. El token OIDC en cada request.
7. **Inglés en el código.** Nombres de entidades, campos, servicios, variables — todo en inglés. La UI es bilingüe (eu/es), el código no.
8. **Mobile-first en el frontend.** Toda vista se diseña primero para móvil y se adapta a escritorio.

---

## Idiomas

- **Euskera** es el idioma predeterminado de la interfaz.
- **Castellano** disponible como alternativa.
- La selección de idioma se guarda en el perfil del usuario.
- Los textos de la UI se gestionan con `vue-i18n`. Ficheros: `src/locales/eu.json` y `src/locales/es.json`.
- Los nombres de entidades, campos y código siempre en **inglés**.

---

## Dominio de negocio

### Concepto de Ejercicio (Exercise)

La aplicación funciona por **ejercicios anuales**. Un ejercicio corresponde a un año natural con una particularidad: **el año laboral no termina el 31 de diciembre sino el 6 de enero del año siguiente**.

```
Exercise 2025:
  Starts: 2025-01-01
  Ends:   2026-01-06
```

Todos los calendarios, solicitudes y horas pertenecen a un ejercicio concreto.

### Concepto de Calendario (Calendar)

Cada empleado tiene un **calendario por ejercicio**, creado por Recursos Humanos (ROLE_BIDERATZAILEA). El calendario define:

| Campo | Descripción |
|---|---|
| `total_hours` | Total de horas a trabajar en el ejercicio |
| `daily_hours` | Jornada laboral diaria en horas (ej: 7.5h) — se usa para convertir días ↔ horas |
| `work_percentage` | Porcentaje de jornada (100%, 66%, 50%...) |
| `vacation_hours` | Horas de vacaciones disponibles |
| `personal_hours` | Horas de libre disposición (asuntos propios) |
| `overtime_hours` | Horas extra acumuladas |
| `union_hours` | Horas sindicales |
| `notes` | Observaciones internas |

**Reglas:**
- Un trabajador puede tener **más de un calendario** en un ejercicio (p.ej. cambio de puesto de trabajo a mitad de año), pero **solo uno activo**.
- Al crear un nuevo calendario, el sistema muestra los datos del calendario anterior del trabajador para facilitar la transferencia de saldos entre ejercicios.
- Puede ser necesario **transferir horas** de un ejercicio al siguiente (vacaciones no disfrutadas, horas extra pendientes, etc.).
- Los calendarios se crean a partir de **plantillas** (Templates). La plantilla importa los días festivos, puentes y el esquema de horas base.
- Los empleados pueden consultar calendarios históricos (ejercicios anteriores) en modo solo lectura.

### Concepto de Plantilla (Template)

Una plantilla define el esquema base para crear calendarios. Contiene:

- Los valores base de horas (total anual, vacaciones, libre disposición, etc.) para una categoría de empleados.
- Los días festivos y puentes del año (como `TemplateDay` entries con tipo de permiso).

Al crear un calendario para un trabajador, se elige una plantilla y se importan automáticamente sus días festivos. El gestor puede después ajustar los valores individuales del trabajador.

### Concepto de Solicitud (Request)

Un empleado presenta una **solicitud** para pedir un permiso, licencia, curso, etc. Flujo:

```
Empleado crea solicitud
        ↓
Sistema detecta incompatibilidades (marca la solicitud, no la bloquea)
        ↓
RRHH (ROLE_BIDERATZAILEA) revisa: comprueba tipo, saldo de horas, etc.
        ↓
RRHH asigna un circuito de firmas
        ↓
Primer firmante recibe notificación → firma
        ↓
Siguiente firmante recibe notificación → firma
        ↓ (o rechaza → fin del circuito con estado RECHAZADA)
Circuito completo → solicitud APROBADA
        ↓
RRHH añade los días al calendario del empleado
```

### Tipos de solicitud (RequestType)

Existe un mantenimiento de tipos de solicitud. Cada tipo define:

| Campo | Descripción |
|---|---|
| `name` | Nombre (multilingüe: eu/es) |
| `code` | Código estable único (ej: `VACATION`, `PERSONAL`, `EXAM`, `COURSE_PERMIT`, `COURSE_HOURS`, `OVERTIME`, `UNION`, `LICENSE`) |
| `color` | Color hexadecimal para mostrar en el calendario |
| `deducts_from` | De qué bucket de horas descuenta (`vacation_hours`, `personal_hours`, `overtime_hours`, `union_hours`, `none`) |
| `show_in_calendar` | Si se muestra en el calendario del empleado |
| `show_in_requests` | Si está disponible para crear solicitudes |
| `requires_hours` | Si la solicitud especifica horas (vs días completos) |
| `requires_document` | Si requiere justificante adjunto |
| `requires_cost` | Si tiene coste asociado (cursos con matrícula) |
| `requires_signature` | Si pasa por circuito de firmas |
| `active` | Habilitado/deshabilitado |

**Tipos predefinidos (seeds iniciales):**

| Code | Nombre EU | Nombre ES |
|---|---|---|
| `VACATION` | Oporrak | Vacaciones |
| `PERSONAL` | Norberarentzako | Asuntos propios |
| `EXAM` | Azterketa | Examen |
| `LICENSE` | Lizentzia | Licencia |
| `COURSE_PERMIT` | Ikastaro baimena | Permiso para curso |
| `COURSE_HOURS` | Ikastaro orduak | Horas de asistencia a curso |
| `OVERTIME` | Ordu gehigarriak | Horas extra |
| `UNION` | Sindikal | Sindicales |

### Incompatibilidades (Incompatibility)

RRHH define grupos de empleados que **no pueden coincidir** en vacaciones al mismo tiempo. Ejemplo: en el departamento de tesorería, dos personas concretas no pueden estar ausentes el mismo día.

- Se define un grupo con nombre y un porcentaje máximo de ausencia simultánea permitida.
- El sistema verifica estas reglas al crear una solicitud.
- Si hay conflicto, la solicitud se marca con `has_conflict = true` y una nota explicativa. **No se bloquea la solicitud** — RRHH decide.

### Circuito de firmas (SignatureCircuit / Signer)

RRHH configura **circuitos de firmas reutilizables**: una lista ordenada de firmantes. Cada solicitud puede tener asignado un circuito diferente según el tipo o el departamento.

- Los firmantes se recorren en orden.
- Cuando un firmante aprueba, se notifica al siguiente.
- Si un firmante rechaza, el circuito termina con la solicitud en estado `REJECTED`.
- Cada paso de firma queda registrado (quién firmó, cuándo, nota opcional).

### Notificaciones y mensajes obligatorios

- El sistema envía **notificaciones** a los firmantes cuando les toca firmar.
- RRHH puede enviar un **mensaje obligatorio** a un trabajador concreto. Cuando ese trabajador hace login, el mensaje aparece en primer plano (modal bloqueante). El trabajador **debe leerlo y confirmar** antes de poder usar la aplicación.

---

## Modelo de datos (entidades — en inglés)

### Entidades principales

```
User
  - id, username, email, displayname, employee_id
  - department: string
  - job_title: string
  - work_percentage: decimal
  - active: bool
  - locale: enum(eu, es)
  - roles: json
  - ManyToOne → Department
  - OneToMany → Calendar, Request, Notification, Message

Department
  - id, name, code
  - OneToMany → User

Exercise
  - id, year (int, unique)
  - start_date: 01/01/year
  - end_date: 06/01/year+1
  - active: bool
  - OneToMany → Calendar

Template
  - id, name, code (unique), active
  - total_hours, daily_hours, work_percentage
  - vacation_hours, personal_hours, overtime_hours, union_hours
  - OneToMany → TemplateDay

TemplateDay
  - id, date, name
  - ManyToOne → Template
  - ManyToOne → RequestType

Calendar
  - id, name, active: bool
  - total_hours, daily_hours, work_percentage
  - vacation_hours, personal_hours, overtime_hours, union_hours
  - notes: text (nullable)
  - ManyToOne → User
  - ManyToOne → Exercise
  - ManyToOne → Template (nullable, referencia a la plantilla de origen)
  - OneToMany → CalendarDay (días marcados), Request, HourTransfer

CalendarDay  ← representa un día concreto en el calendario de un empleado
  - id, date
  - ManyToOne → Calendar
  - ManyToOne → RequestType

HourTransfer  ← transferencia de horas entre ejercicios
  - id, hours, type (qué bucket), from_exercise, to_exercise, notes
  - ManyToOne → Calendar (destino)
  - ManyToOne → User
  - created_at

RequestType
  - id, code (unique), color
  - name_eu, name_es
  - deducts_from: enum(vacation_hours, personal_hours, overtime_hours, union_hours, none)
  - show_in_calendar: bool
  - show_in_requests: bool
  - requires_hours: bool
  - requires_document: bool
  - requires_cost: bool
  - requires_signature: bool
  - active: bool
  - order: int

Request  ← la solicitud/instancia
  - id
  - state: enum(DRAFT, PENDING_REVIEW, PENDING_SIGNATURES, APPROVED, REJECTED, CANCELLED, IN_CALENDAR)
  - has_conflict: bool
  - conflict_notes: text (nullable)
  - start_date, end_date
  - total_hours: decimal
  - total_days: decimal (calculado: total_hours / calendar.daily_hours)
  - notes: text (nullable)
  - submitted_at: datetime
  - cost: decimal (nullable)  ← para cursos con matrícula
  - ManyToOne → User
  - ManyToOne → Calendar
  - ManyToOne → RequestType
  - ManyToOne → SignatureCircuit (nullable, asignado por RRHH)
  - OneToMany → RequestDocument, SignatureStep, Notification

RequestDocument  ← ficheros adjuntos
  - id, file_path, filename, file_size
  - document_type: enum(JUSTIFICATION, COURSE_CERT, PAYMENT, OTHER)
  - ManyToOne → Request
  - uploaded_at

SignatureCircuit  ← circuito de firmas reutilizable
  - id, name, active
  - OneToMany → CircuitSigner (ordenados)

CircuitSigner  ← firmante dentro de un circuito
  - id, order
  - ManyToOne → SignatureCircuit
  - ManyToOne → User

SignatureStep  ← registro de cada paso de firma para una solicitud concreta
  - id, order
  - state: enum(PENDING, APPROVED, REJECTED)
  - signed_at: datetime (nullable)
  - notes: text (nullable)   ← nota del firmante
  - auto_signed: bool
  - ManyToOne → Request
  - ManyToOne → User (expected_signer)  ← quién debe firmar
  - ManyToOne → User (actual_signer, nullable)  ← quién firmó realmente

Incompatibility  ← grupo de incompatibilidad
  - id, name, max_absence_percentage: decimal
  - OneToMany → IncompatibilityMember

IncompatibilityMember
  - id
  - ManyToOne → Incompatibility
  - ManyToOne → User

Notification
  - id, title, body
  - type: enum(SIGNATURE_REQUIRED, REQUEST_APPROVED, REQUEST_REJECTED, SYSTEM)
  - read: bool, read_at: datetime (nullable)
  - completed: bool
  - ManyToOne → User (recipient)
  - ManyToOne → Request (nullable)
  - ManyToOne → SignatureStep (nullable)
  - created_at

Message  ← mensaje obligatorio de RRHH a un trabajador
  - id, subject, body
  - mandatory: bool  ← si true, bloquea la UI hasta que el usuario lo confirme
  - read: bool, read_at: datetime (nullable)
  - ManyToOne → User (recipient)
  - ManyToOne → User (sender)
  - sent_at
```

### Enum `RequestState`

```php
enum RequestState: string
{
    case DRAFT              = 'draft';             // creada, no enviada
    case PENDING_REVIEW     = 'pending_review';    // enviada, pendiente de revisión de RRHH
    case PENDING_SIGNATURES = 'pending_signatures'; // en circuito de firmas
    case APPROVED           = 'approved';          // todas las firmas OK
    case REJECTED           = 'rejected';          // rechazada por un firmante o RRHH
    case CANCELLED          = 'cancelled';         // anulada por el empleado o RRHH
    case IN_CALENDAR        = 'in_calendar';       // aprobada y añadida al calendario
}
```

---

## Roles y permisos

### `ROLE_USER` — Empleado

Todo usuario autenticado recibe este rol.

**Puede:**
- Ver su calendario anual (meses con días marcados por tipo de solicitud + leyenda de colores).
- Imprimir el calendario en A4.
- Crear solicitudes de los tipos disponibles.
- Ver sus solicitudes y su estado.
- Ver sus notificaciones.
- Leer y confirmar mensajes obligatorios de RRHH.
- Ver calendarios de ejercicios anteriores (solo lectura).

**No puede:**
- Ver calendarios ni solicitudes de otros empleados.
- Asignar circuitos de firmas.
- Acceder al panel de administración.

### `ROLE_BIDERATZAILEA` — Recursos Humanos / Gestión

**Incluye todos los permisos de `ROLE_USER`, más:**

- **Dashboard** de todos los empleados con filtros y acciones.
- **Gestión de instancias/solicitudes:** ver todas, asignar circuito de firmas, enviar mensaje obligatorio, eliminar.
- **Gestión de calendarios:** crear, editar, activar/desactivar, imprimir.
- **Añadir solicitudes aprobadas al calendario** del empleado.
- **Mantenimientos:**
  - Tipos de solicitud (`RequestType`)
  - Plantillas (`Template`) y sus días festivos
  - Incompatibilidades (`Incompatibility`)
  - Circuitos de firmas (`SignatureCircuit`)
  - Gestión de asistencia a cursos
- Ver y gestionar todas las notificaciones del sistema.

### `ROLE_ADMIN` — Administrador técnico

**Incluye todos los permisos anteriores, más:**

- Gestión de usuarios (activar/desactivar, cambiar departamento, asignar roles).
- Gestión de departamentos.
- Gestión de ejercicios.
- Logs de auditoría.
- Operaciones de mantenimiento del sistema.

### `ROLE_SIGNER` — Firmante

Rol adicional para usuarios que participan en circuitos de firma. Se asigna automáticamente si el usuario está incluido en algún `SignatureCircuit`.

**Puede además de `ROLE_USER`:**
- Ver las solicitudes pendientes de su firma (bandeja de firmas).
- Firmar o rechazar solicitudes asignadas.
- Añadir nota al firmar/rechazar.

---

## Funcionalidades detalladas

### F1. Vista principal del empleado (calendario anual)

Al hacer login, el empleado ve su calendario anual del ejercicio activo:
- Los 12 meses en una cuadrícula.
- Los días de las solicitudes aprobadas/en proceso marcados con el color del tipo.
- Leyenda de colores en la parte inferior o lateral.
- Resumen de saldos: "Vacaciones: 120h disponibles / 40h usadas / 80h restantes".
- Botón "Imprimir" que genera una vista A4 optimizada para impresión (CSS `@media print`).
- El calendario es **responsive**: en móvil se muestra un mes a la vez con scroll.

### F2. Crear solicitud

El empleado accede a "Nueva solicitud":

1. Se muestran los tipos de solicitud disponibles (donde `show_in_requests = true` y `active = true`) como tarjetas o lista.
2. El empleado selecciona un tipo.
3. Aparece el formulario correspondiente. Campos comunes a todos los tipos:
   - Fecha inicio
   - Fecha fin
   - Horas totales (o días, con conversión automática usando `daily_hours`)
   - Nota/observaciones
4. Según el tipo, aparecen campos adicionales:
   - **COURSE_PERMIT / COURSE_HOURS:** institución, lugar, modalidad (presencial/online), coste, fichero de matrícula.
   - **EXAM:** institución, lugar, hora de inicio, duración estimada.
   - **LICENSE, OVERTIME, UNION:** sin campos adicionales por defecto.
   - **Tipos con `requires_document = true`:** campo de adjunto de fichero.
5. Al enviar, el sistema:
   - Verifica que el saldo de horas es suficiente. Si no lo es: **advertencia**, no bloqueo (RRHH decide).
   - Verifica las incompatibilidades. Si hay conflicto: `has_conflict = true` + nota automática en `conflict_notes`.
   - Cambia el estado a `PENDING_REVIEW`.
   - Notifica a RRHH de la nueva solicitud.

### F3. Panel de solicitudes del empleado

Lista de sus solicitudes con columnas: tipo, fechas, horas, estado (badge de color), nota de conflicto (icono si existe), acciones (ver detalle, cancelar si `DRAFT`/`PENDING_REVIEW`, descargar PDF).

### F4. Bandeja de notificaciones

Lista de notificaciones del usuario. Las de tipo `SIGNATURE_REQUIRED` son accionables — llevan al formulario de firma.

### F5. Mensaje obligatorio (modal bloqueante)

Si el usuario tiene mensajes con `mandatory = true` y `read = false`:
- Al hacer login (antes de cualquier otra acción), se muestra un modal que ocupa toda la pantalla.
- El usuario lee el mensaje, hace clic en "He leído y entiendo este mensaje" y el modal se cierra.
- Solo entonces puede usar la aplicación con normalidad.
- Si hay varios mensajes pendientes, se muestran uno a uno.

### F6. Dashboard de RRHH

Vista de tabla de todos los empleados. Columnas:
- Nombre y apellidos
- Departamento
- Ejercicio activo
- Notas del calendario
- Plantilla usada
- Horas totales / Vacaciones / Libre disposición / Horas extra / Sindicales
- Estado del calendario (activo/inactivo)
- Acciones: nuevo calendario, editar, imprimir, ver solicitudes del empleado

Filtros disponibles: departamento, ejercicio, plantilla, búsqueda por nombre.
Ordenación por columna.

### F7. Gestión de solicitudes (RRHH)

Lista de todas las solicitudes del sistema. Columnas:
- ID
- Empleado (nombre + departamento)
- Tipo
- Fechas
- Horas totales
- Estado
- Circuito de firmas asignado
- Icono de conflicto (si `has_conflict = true`)
- Notas

Filtros: estado, tipo, empleado, departamento, rango de fechas, con/sin conflicto.

**Acciones por solicitud:**
- Ver detalle completo.
- Asignar circuito de firmas (solo si `PENDING_REVIEW`). Al asignar: estado → `PENDING_SIGNATURES`, se crean los `SignatureStep`s y se notifica al primer firmante.
- Enviar mensaje obligatorio al empleado.
- Añadir al calendario (solo si `APPROVED`). Al añadir: crea `CalendarDay`s, descuenta horas del `Calendar`, estado → `IN_CALENDAR`.
- Eliminar solicitud (con confirmación).

### F8. Workflow de firmas

Cuando se asigna un circuito a una solicitud, el sistema:
1. Crea un `SignatureStep` por cada firmante del circuito, con `state = PENDING` y el `expected_signer` correspondiente.
2. Envía una notificación al primer firmante.

El firmante, desde su **bandeja de firmas** (notificaciones con `type = SIGNATURE_REQUIRED`):
1. Ve el detalle de la solicitud: empleado, tipo, fechas, horas, notas.
2. Elige **Aprobar** o **Rechazar**, con nota opcional.
3. Al aprobar:
   - El `SignatureStep` actual pasa a `APPROVED`, se registra `actual_signer` y `signed_at`.
   - Si hay más pasos pendientes: se notifica al siguiente firmante.
   - Si era el último paso: `Request.state → APPROVED`, se notifica al empleado y a RRHH.
4. Al rechazar:
   - El `SignatureStep` pasa a `REJECTED`.
   - `Request.state → REJECTED`.
   - Se notifica al empleado y a RRHH.
   - El circuito termina (los pasos restantes no se procesan).

**Idempotencia:** antes de crear un `SignatureStep`, verificar que no existe ya uno para el mismo `expected_signer` en esa solicitud para evitar duplicados por error de red.

### F9. Gestión de asistencia a cursos

RRHH lleva un registro de los cursos a los que asistirán los empleados:
- Ver listado de solicitudes de tipo `COURSE_PERMIT` y `COURSE_HOURS` aprobadas.
- Marcar si el curso se completó (`completed: bool`).
- Registrar si se pagó la matrícula y quién la pagó (empleado / organización).
- Subir certificado de asistencia.

Esto es esencialmente un filtro y acciones extra sobre las solicitudes de tipo curso.

### F10. Mantenimiento de plantillas

CRUD de plantillas con gestión de días festivos:
- Crear/editar plantilla con los valores base de horas.
- Añadir/quitar días festivos y puentes con su tipo de solicitud asociado.
- Copiar una plantilla existente para el ejercicio siguiente.

### F11. Mantenimiento de circuitos de firmas

CRUD de circuitos con gestión de firmantes:
- Crear/editar circuito con nombre.
- Añadir firmantes en orden (un usuario por slot).
- Reordenar firmantes (drag & drop o botones arriba/abajo).
- Activar/desactivar circuitos.

### F12. Mantenimiento de incompatibilidades

CRUD de grupos:
- Nombre del grupo.
- Porcentaje máximo de ausencia simultánea.
- Lista de empleados del grupo.

### F13. Historial de calendarios

El empleado (y RRHH) pueden consultar calendarios de ejercicios anteriores. En modo solo lectura: misma vista de calendario anual pero sin posibilidad de crear solicitudes.

### F14. Generación de PDFs

- **Calendario del empleado:** vista anual en A4, con todos los días marcados, leyenda y resumen de saldos. También generado en el backend vía Gotenberg.
- **Detalle de solicitud:** datos completos de la solicitud como documento formal.

### F15. Tareas automatizadas (Scheduler)

| Tarea | Frecuencia | Descripción |
|---|---|---|
| Notificar firmantes pendientes | Lun-Vie 8:00 | Email a firmantes con solicitudes pendientes de firma desde hace >24h |
| Verificar solicitudes aprobadas | Diaria 7:00 | Detectar solicitudes `APPROVED` no añadidas al calendario; alerta a RRHH |
| Cleanup notificaciones | Semanal | Borrar notificaciones leídas con más de 90 días |

---

## Arquitectura técnica — Backend

### Estructura de directorios

```
calendar-api/
├── compose.yaml                     # servicios: php, nginx, postgres, redis, mailpit, gotenberg
├── compose.override.yaml
├── Makefile
├── config/
│   ├── packages/
│   │   ├── api_platform.yaml
│   │   ├── security.yaml
│   │   ├── doctrine.yaml
│   │   ├── messenger.yaml
│   │   ├── scheduler.yaml
│   │   ├── mailer.yaml
│   │   └── flysystem.yaml
│   └── services.yaml
├── migrations/
├── src/
│   ├── Api/                   # API Platform resources, DTOs, Processors, Providers, Filters, Voters
│   ├── Domain/                # Entidades, Enums, interfaces de repositorio, servicios de dominio
│   │   ├── Calendar/
│   │   ├── Request/
│   │   ├── Signature/
│   │   ├── User/
│   │   └── Shared/
│   ├── Application/           # Casos de uso, orquestación entre servicios
│   ├── Infrastructure/        # Doctrine repos (implementaciones), Mailer, Storage, SSO
│   └── Scheduler/             # Handlers de Symfony Scheduler
├── templates/
│   ├── emails/
│   └── pdf/
└── tests/
    ├── Api/
    ├── Domain/
    └── Application/
```

### Docker Compose (dev)

```yaml
services:
  php:
    build: docker/php
    volumes: [".:/var/www/html"]
    environment:
      DATABASE_URL: postgresql://calendar:calendar@postgres:5432/calendar?serverVersion=15
      MAILER_DSN: smtp://mailpit:1025
      MESSENGER_TRANSPORT_DSN: redis://redis:6379/messages
      GOTENBERG_URL: http://gotenberg:3000
      APP_ENV: dev

  nginx:
    image: nginx:alpine
    ports: ["8080:80"]

  postgres:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: calendar
      POSTGRES_USER: calendar
      POSTGRES_PASSWORD: calendar
    volumes: [postgres_data:/var/lib/postgresql/data]
    ports: ["5432:5432"]

  redis:
    image: redis:7-alpine

  mailpit:
    image: axllent/mailpit
    ports: ["1025:1025", "8025:8025"]

  gotenberg:
    image: gotenberg/gotenberg:8
    ports: ["3000:3000"]

volumes:
  postgres_data:
```

### Autenticación OIDC

La API actúa como **resource server** OIDC. No gestiona usuarios directamente — delega en el IdP corporativo.

- Cada request incluye `Authorization: Bearer <access_token>`.
- Un `OidcAuthenticator` custom valida el token con el bundle SSO corporativo `[SSO_BUNDLE_PLACEHOLDER]`.
- El `UserProvider` carga o crea el `User` en la BD a partir de los claims del token.
- Los roles se mapean desde los grupos/claims del token a `ROLE_USER`, `ROLE_BIDERATZAILEA`, `ROLE_ADMIN`, `ROLE_SIGNER`.

```yaml
# config/packages/security.yaml
security:
    providers:
        oidc: { id: App\Infrastructure\Sso\OidcUserProvider }
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            custom_authenticators: [App\Infrastructure\Sso\OidcAuthenticator]
    role_hierarchy:
        ROLE_ADMIN:         [ROLE_BIDERATZAILEA]
        ROLE_BIDERATZAILEA: [ROLE_SIGNER, ROLE_USER]
        ROLE_SIGNER:        [ROLE_USER]
    access_control:
        - { path: ^/api/docs, roles: PUBLIC_ACCESS }
        - { path: ^/api,      roles: ROLE_USER }
```

### Servicios de dominio clave

```
CalendarHourService
  - addRequestToCalendar(Request, Calendar): void
    Descuenta horas del bucket correcto según RequestType.deducts_from
    Verifica que hay saldo suficiente (warning si no, no bloqueo)
    Crea los CalendarDay correspondientes

ConflictDetectorService
  - check(Request): ConflictResult
    Verifica incompatibilidades (grupos con % de ausencia simultánea)
    Verifica solapamiento con otras solicitudes del mismo usuario
    Retorna {hasConflict, messages[]} — no bloquea la creación

SignatureWorkflowService
  - initiate(Request, SignatureCircuit): void
    Crea los SignatureStep para cada firmante del circuito
    Notifica al primer firmante
  - approve(SignatureStep, User, ?string $note): void
    Registra la firma, avanza al siguiente paso o cierra el circuito
  - reject(SignatureStep, User, string $note): void
    Cierra el circuito como REJECTED

HourTransferService
  - transfer(Calendar $from, Calendar $to, string $bucket, float $hours): void
    Registra la transferencia de horas entre ejercicios
```

---

## Arquitectura técnica — Frontend (Vue 3)

### Estructura de directorios

```
calendar-app/
├── src/
│   ├── api/               # clientes HTTP tipados
│   ├── stores/            # Pinia stores (auth, calendar, request, notification, ui)
│   ├── router/            # Vue Router con guards de auth
│   ├── views/             # páginas
│   │   ├── employee/      # vistas del empleado
│   │   ├── hr/            # vistas de RRHH
│   │   └── shared/
│   ├── components/        # componentes reutilizables
│   │   ├── calendar/
│   │   ├── request/
│   │   ├── signature/
│   │   └── shared/
│   ├── composables/
│   ├── types/             # TypeScript interfaces
│   ├── locales/           # eu.json, es.json
│   └── utils/
├── vite.config.ts
└── tsconfig.json
```

### Vistas principales

| Ruta | Vista | Rol |
|---|---|---|
| `/` | Calendario anual del empleado | USER |
| `/requests` | Mis solicitudes | USER |
| `/requests/new` | Crear solicitud | USER |
| `/notifications` | Mis notificaciones / bandeja de firmas | USER |
| `/calendar/history` | Histórico de calendarios | USER |
| `/hr/dashboard` | Dashboard de empleados | BIDERATZAILEA |
| `/hr/requests` | Gestión de solicitudes | BIDERATZAILEA |
| `/hr/calendars/:userId` | Gestión de calendario de un empleado | BIDERATZAILEA |
| `/hr/maintenance/request-types` | Tipos de solicitud | BIDERATZAILEA |
| `/hr/maintenance/templates` | Plantillas | BIDERATZAILEA |
| `/hr/maintenance/incompatibilities` | Incompatibilidades | BIDERATZAILEA |
| `/hr/maintenance/signature-circuits` | Circuitos de firmas | BIDERATZAILEA |
| `/hr/courses` | Gestión de asistencia a cursos | BIDERATZAILEA |
| `/admin/users` | Gestión de usuarios | ADMIN |

### Guards de autenticación

```typescript
// router/index.ts
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.isAuthenticated) return '/login'
  if (to.meta.role && !auth.hasRole(to.meta.role)) return '/403'

  // Verificar mensajes obligatorios
  const ui = useUiStore()
  if (!ui.mandatoryMessagesChecked) {
    await ui.checkMandatoryMessages()
  }
})
```

### Formulario de solicitud dinámico

```vue
<!-- components/request/RequestForm.vue -->
<script setup lang="ts">
const typeCode = ref<string>('')

const isCourse = computed(() =>
  ['COURSE_PERMIT', 'COURSE_HOURS'].includes(typeCode.value))
const isExam = computed(() =>
  typeCode.value === 'EXAM')
</script>

<template>
  <form @submit.prevent="submit">
    <RequestTypeSelector v-model="typeCode" />
    <DateRangePicker v-model:start="startDate" v-model:end="endDate" />
    <HoursInput v-model="totalHours" :daily-hours="calendar.dailyHours" />

    <CourseFields v-if="isCourse"
      v-model:institution="institution"
      v-model:cost="cost"
      v-model:online="isOnline" />

    <ExamFields v-if="isExam"
      v-model:institution="institution"
      v-model:start-time="startTime" />

    <FileUpload v-if="requiresDocument" v-model="document" />

    <ConflictWarning v-if="conflict" :messages="conflict.messages" />
    <HoursWarning v-if="insufficientHours" />
  </form>
</template>
```

---

## Despliegue en producción

### Build de imágenes

```dockerfile
# docker/php/Dockerfile.prod
FROM php:8.3-fpm-alpine AS builder
COPY . /var/www/html
RUN composer install --no-dev --optimize-autoloader
RUN bin/console asset-map:compile

FROM php:8.3-fpm-alpine
COPY --from=builder /var/www/html /var/www/html
```

```bash
# Build y push al registry
docker build -f docker/php/Dockerfile.prod -t registry.example.com/calendar-api:latest .
docker push registry.example.com/calendar-api:latest
```

### Docker Compose en VPS (producción)

```yaml
# compose.prod.yaml
services:
  php:
    image: registry.example.com/calendar-api:${VERSION:-latest}
    env_file: .env.prod
    restart: unless-stopped

  nginx:
    image: registry.example.com/calendar-nginx:${VERSION:-latest}
    ports: ["80:80", "443:443"]
    restart: unless-stopped

  messenger_worker:
    image: registry.example.com/calendar-api:${VERSION:-latest}
    command: bin/console messenger:consume async --time-limit=3600
    restart: unless-stopped

  scheduler_worker:
    image: registry.example.com/calendar-api:${VERSION:-latest}
    command: bin/console messenger:consume scheduler_default --time-limit=3600
    restart: unless-stopped

  postgres:
    image: postgres:15-alpine
    volumes: [postgres_data:/var/lib/postgresql/data]
    env_file: .env.prod
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    restart: unless-stopped

  gotenberg:
    image: gotenberg/gotenberg:8
    restart: unless-stopped

volumes:
  postgres_data:
```

### Makefile de despliegue

```makefile
VERSION ?= latest

build:
    docker build -f docker/php/Dockerfile.prod -t registry.example.com/calendar-api:$(VERSION) .
    docker build -f docker/nginx/Dockerfile -t registry.example.com/calendar-nginx:$(VERSION) .

push: build
    docker push registry.example.com/calendar-api:$(VERSION)
    docker push registry.example.com/calendar-nginx:$(VERSION)

deploy:
    ssh vps "cd /opt/calendar && VERSION=$(VERSION) docker compose -f compose.prod.yaml pull && docker compose -f compose.prod.yaml up -d && docker compose -f compose.prod.yaml exec php bin/console doctrine:migrations:migrate --no-interaction"
```

---

## Roadmap de implementación

### Fase 0 — Bootstrap (1 semana)
- Crear repositorios `calendar-api` y `calendar-app`.
- Configurar Docker Compose (dev + prod).
- Symfony instalado, API Platform configurado, primera migración.
- Vue 3 + Vite + PrimeVue bootstrapeados.
- CI/CD básico (lint + tests en cada push).
- **Criterio:** `GET /api` devuelve JSON-LD. `npm run dev` arranca la SPA.

### Fase 1 — Entidades + Auth (1-2 semanas)
- Todas las entidades con atributos PHP 8, migraciones.
- OIDC authenticator funcionando.
- Seeds: RequestType (8 tipos), Ejercicio actual.
- **Criterio:** `GET /api` con token válido → 200. Sin token → 401.

### Fase 2 — CRUD base y calendarios (2 semanas)
- API resources: User, Department, Exercise, Template, Calendar, RequestType.
- Lógica de `CalendarHourService`.
- Vista del dashboard de RRHH y gestión de calendarios en la SPA.
- **Criterio:** RRHH puede crear un calendario para un empleado desde una plantilla.

### Fase 3 — Solicitudes y conflictos (2 semanas)
- API resources: Request, RequestDocument.
- `ConflictDetectorService` con tests.
- Formulario de solicitud dinámico en la SPA.
- Vista del calendario anual del empleado.
- **Criterio:** Empleado puede crear una solicitud, el sistema detecta conflictos.

### Fase 4 — Workflow de firmas (2 semanas)
- API resources: SignatureCircuit, CircuitSigner, SignatureStep.
- `SignatureWorkflowService` con tests.
- RRHH puede asignar circuito y las firmas avanzan correctamente.
- Bandeja de firmas en la SPA.
- **Criterio:** Flujo completo creación → revisión RRHH → firmas → aprobación.

### Fase 5 — Notificaciones, mensajes y PDF (1-2 semanas)
- Sistema de notificaciones completo.
- Mensajes obligatorios con modal bloqueante.
- Generación de PDFs (Gotenberg).
- Scheduler (emails de recordatorio, alertas).
- **Criterio:** Firmante recibe email de recordatorio. El empleado ve el modal al login si hay mensaje pendiente.

### Fase 6 — Pulido, mantenimientos y producción (2 semanas)
- Mantenimientos: Tipos de solicitud, Plantillas, Incompatibilidades, Circuitos.
- Impresión A4 del calendario.
- Historial de calendarios.
- Gestión de asistencia a cursos.
- Build de imágenes Docker y despliegue en VPS.
- **Criterio:** La aplicación está operativa en producción para los primeros usuarios piloto.

---

## Checklist de inicio (Fase 0)

Antes de escribir una línea de código de negocio, verificar que:

- [ ] `docker compose up -d` levanta todos los servicios sin errores.
- [ ] `docker compose exec php bin/console doctrine:migrations:migrate` ejecuta sin errores.
- [ ] `GET http://localhost:8080/api` devuelve el índice JSON-LD de API Platform.
- [ ] `docker compose exec php bin/phpunit` arranca (sin tests aún, pero el runner funciona).
- [ ] `docker compose exec node npm run dev` arranca la SPA en `http://localhost:5173`.
- [ ] `docker compose exec php vendor/bin/phpstan analyse` pasa nivel 8.
- [ ] El CI/CD ejecuta lint y tests en cada push a `main`.
