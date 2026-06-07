# 10 — SPA Frontend (Vue 3 — Fase 6)

## Contexto

La SPA es la **segunda fase** del proyecto. El backend API (fases F0–F5) debe estar operativo antes de iniciar la SPA. La SPA es un cliente de la API — no comparte código con el backend Symfony.

---

## Stack tecnológico

| Capa | Tecnología | Notas |
|---|---|---|
| Framework | **Vue 3** | Composition API + `<script setup>` exclusivamente |
| Lenguaje | **TypeScript** | Strict mode; sin `any` explícitos |
| Build | **Vite 5** | HMR en dev; build optimizado para prod |
| Estado | **Pinia** | Stores por dominio (calendar, eskaera, firma, user) |
| Router | **Vue Router 4** | History mode; lazy loading de rutas |
| HTTP | **axios** o **ofetch** | Interceptor de Bearer token OIDC |
| Auth | **OIDC client JS** (`oidc-client-ts` o equivalente del bundle SSO) | Flujo PKCE |
| UI | **PrimeVue 4** o **Headless UI + Tailwind CSS** | Definir con el cliente |
| Calendario | **FullCalendar v6** | Para la vista de calendario mensual/anual |
| Tablas | **TanStack Table v8** | Para grids de kuadrantea y listas paginadas |
| Formularios | **VeeValidate + Zod** | Validación client-side |
| PDF | Abrir la URL del API en nueva pestaña | El backend genera el PDF |
| Tests | **Vitest + Vue Test Utils** | Unit/component tests |
| E2E | **Playwright** | Tests end-to-end |
| Linting | **ESLint + Prettier** | |

---

## Organización del repositorio

```
egutegia-app/
├── public/
├── src/
│   ├── api/                    # clientes API tipados (generados o manuales)
│   │   ├── client.ts           # axios base con interceptor Bearer
│   │   ├── calendars.api.ts
│   │   ├── eskaerak.api.ts
│   │   ├── firmak.api.ts
│   │   └── ...
│   ├── stores/                 # Pinia stores
│   │   ├── auth.store.ts       # usuario autenticado, token OIDC
│   │   ├── calendar.store.ts
│   │   ├── eskaera.store.ts
│   │   ├── notification.store.ts
│   │   └── ui.store.ts
│   ├── router/
│   │   └── index.ts            # rutas + guards de autenticación
│   ├── views/                  # páginas (una por ruta principal)
│   │   ├── CalendarView.vue
│   │   ├── EskaeraCreateView.vue
│   │   ├── EskaeraListView.vue
│   │   ├── NotificationView.vue
│   │   ├── KuadranteaView.vue
│   │   ├── AdminView.vue
│   │   └── ...
│   ├── components/             # componentes reutilizables
│   │   ├── calendar/
│   │   ├── eskaera/
│   │   ├── firma/
│   │   ├── kuadrantea/
│   │   └── shared/
│   ├── composables/            # Vue composables
│   │   ├── useEskaera.ts
│   │   ├── useFirma.ts
│   │   └── usePagination.ts
│   ├── types/                  # TypeScript interfaces (basadas en la API)
│   │   ├── calendar.types.ts
│   │   ├── eskaera.types.ts
│   │   └── ...
│   ├── utils/
│   └── App.vue
├── tests/
│   ├── unit/
│   └── e2e/
├── index.html
├── vite.config.ts
├── tsconfig.json
└── package.json
```

---

## Autenticación OIDC en la SPA

```typescript
// src/stores/auth.store.ts
import { defineStore } from 'pinia'
import { UserManager, type User } from 'oidc-client-ts'

const userManager = new UserManager({
    authority: import.meta.env.VITE_OIDC_AUTHORITY,
    client_id: import.meta.env.VITE_OIDC_CLIENT_ID,
    redirect_uri: window.location.origin + '/callback',
    scope: 'openid profile email',
    response_type: 'code',     // PKCE flow
})

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null)

    async function login() {
        await userManager.signinRedirect()
    }

    async function handleCallback() {
        user.value = await userManager.signinRedirectCallback()
    }

    async function logout() {
        await userManager.signoutRedirect()
    }

    const isAuthenticated = computed(() => !!user.value && !user.value.expired)
    const accessToken = computed(() => user.value?.access_token ?? null)
    const userRoles = computed(() => user.value?.profile?.roles as string[] ?? [])

    return { user, login, handleCallback, logout, isAuthenticated, accessToken, userRoles }
})
```

```typescript
// src/api/client.ts
import axios from 'axios'
import { useAuthStore } from '@/stores/auth.store'

const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
})

apiClient.interceptors.request.use(config => {
    const auth = useAuthStore()
    if (auth.accessToken) {
        config.headers.Authorization = `Bearer ${auth.accessToken}`
    }
    return config
})

export default apiClient
```

---

## Pantallas principales

### 1. Dashboard personal (`/mycalendar`)

**Equivale a:** `DefaultController::userHomepageAction`

- Vista de calendario mensual/anual (FullCalendar) con los Events del usuario.
- Muestra el presupuesto de horas del año: vacaciones restantes, norberarentzako, compensadas.
- Botón "Nueva solicitud" con desplegable por tipo de permiso.
- Panel lateral de notificaciones pendientes de firma.

```vue
<!-- src/views/CalendarView.vue -->
<script setup lang="ts">
import FullCalendar from '@fullcalendar/vue3'
import { useCalendarStore } from '@/stores/calendar.store'

const calendarStore = useCalendarStore()
const { calendar, events } = storeToRefs(calendarStore)

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: 'eu',    // euskera
    events: events.value.map(e => ({
        title: e.type.labur,
        start: e.startDate,
        end: e.endDate,
        color: e.type.color,
    })),
}))
</script>
```

### 2. Crear solicitud (`/eskaerak/new`)

**Equivale a:** `EskaeraController::newAction` (múltiples variantes por tipo)

La pantalla **adapta el formulario dinámicamente** según el tipo de permiso seleccionado:

| Tipo (`labur`) | Campos adicionales |
|---|---|
| `OPO`, `NAE`, `KON` | Estándar: fechas, días, horas, nota, justificante |
| `IKA` | + institución, lugar, online, coste, pagado, curso-file, idioma |
| `AZT` | + institución, lugar, hora esperada, duración |
| `MUN` | Formulario específico policía municipal |

```vue
<!-- src/components/eskaera/EskaeraForm.vue -->
<script setup lang="ts">
const typeLabur = ref<string>('')
const isIkastaroa = computed(() => typeLabur.value === 'IKA')
const isAzterketa = computed(() => typeLabur.value === 'AZT')
const isMunipa    = computed(() => typeLabur.value === 'MUN')
</script>

<template>
  <form @submit.prevent="submit">
    <!-- Campos comunes -->
    <TypeSelector v-model="typeLabur" />
    <DateRangePicker v-model:start="hasi" v-model:end="amaitu" />

    <!-- Campos IKA -->
    <template v-if="isIkastaroa">
      <input v-model="erakundea" placeholder="Erakundea" />
      <CostFields v-model:kostua="kostua" v-model:ordainduta="ordainduta" />
      <FileUpload v-model="ikastaroaFile" label="Ziurtagiria" />
    </template>

    <!-- Aviso de conflicto (si la API devuelve konfliktoa=true) -->
    <ConflictAlert v-if="conflictDetected" :message="oharra" />
  </form>
</template>
```

### 3. Mis solicitudes (`/eskaerak`)

Lista paginada de las solicitudes del usuario con filtros: estado, tipo, año. Columnas: tipo, fechas, estado (badge de color), acciones (ver, PDF, cancelar).

### 4. Bandeja de firmas (`/notifications`)

**Equivale a:** `NotificationController::indexAction`

- Lista de notificaciones pendientes de firma.
- Para cada una: nombre de la solicitud, solicitante, tipo, fechas.
- Botón "Firmar" abre un modal con el detalle de la solicitud + campo de postit.
- Botón "Rechazar" con campo de nota obligatorio.
- Opción de transfer a otro usuario.

```vue
<!-- src/components/firma/FirmaModal.vue -->
<script setup lang="ts">
const { eskaera, notification } = defineProps<{
  eskaera: EskaeraDto
  notification: NotificationDto
}>()

const postit = ref('')
const isSubmitting = ref(false)

async function firmar() {
  isSubmitting.value = true
  await eskaeraApi.firmar(eskaera.id, { postit: postit.value })
  emit('firma-completed')
}

async function ukatu() {
  if (!postit.value) return  // nota obligatoria al rechazar
  await eskaeraApi.procesarJakinarazpena(eskaera.id, {
    result: 'ukatua',
    postit: postit.value
  })
  emit('firma-completed')
}
</script>
```

### 5. Kuadrantea — Grid mensual (`/kuadrantea`)

**Equivale a:** `AdminController::kuadranteaAction` + vistas de departamento

- Tabla con usuarios en filas y días del mes en columnas.
- Celda coloreada según `Type.color` + letra del tipo (`OPO`, `NAE`, etc.).
- Filtros: año, mes, departamento (saila), equipo (taldea).
- Vista especial para `ROLE_SAILBURUA`: solo su departamento.
- Botón de rebuild manual (ROLE_ADMIN).

### 6. Admin — Master data (`/admin/*`)

Vistas de CRUD para: Types, Templates, Lizentziamota, Sinatzaileak, Gutxienekoak, Saila, Taldea, Users.

Todas con: tabla paginada + búsqueda + formulario modal de creación/edición + confirmación de borrado.

### 7. Informes (`/reports`)

- **Absentismo**: filtros año/saila/tipo → tabla + posible export CSV.
- **Konpentsatuak**: horas compensadas por usuario.
- **Balance anual**: resumen de horas por calendario.

### 8. Admin eskaerak (`/admin/eskaerak`)

Vista de todas las solicitudes (ROLE_ADMIN y ROLE_BIDERATZAILEA):
- Filtros: estado, tipo, usuario, año, confliktoa, bideratua.
- Acciones: ver detalle, tramitar (asignar sinatzaileak), añadir al calendario, cancelar.
- Columnas clave: usuario, tipo, fechas, estado, confliktoa (icono), acciones.

---

## Variables de entorno de la SPA

```
# .env.local
VITE_API_URL=http://localhost:8080/api
VITE_OIDC_AUTHORITY=https://sso.pasaia.net/.well-known/openid-configuration
VITE_OIDC_CLIENT_ID=egutegia-spa
```

---

## Tipos TypeScript base

Generar automáticamente desde el OpenAPI de la API Platform:

```bash
npx openapi-typescript http://localhost:8080/api/docs.json -o src/types/api.generated.ts
```

O mantener los tipos manualmente si hay mucha personalización:

```typescript
// src/types/eskaera.types.ts
export type EskaeraState =
  | 'draft' | 'pending' | 'approved' | 'rejected'
  | 'cancelled' | 'in_calendar' | 'justified'

export interface EskaeraDto {
  id: number
  '@id': string          // IRI
  name: string | null
  hasi: string           // ISO 8601 date
  amaitu: string | null
  egunak: number | null
  orduak: number | null
  state: EskaeraState
  konfliktoa: boolean
  oharra: string | null
  type: TypeDto
  user: UserDto
  calendar: CalendarDto
  firma: FirmaDto | null
}
```

---

## Criterios de aceptación F6

- [ ] Login/logout con el SSO OIDC funciona correctamente.
- [ ] Un usuario puede ver su calendario con sus eventos coloreados.
- [ ] El flujo completo "crear solicitud → firmar → ver en calendario" funciona en la SPA.
- [ ] El kuadrantea muestra el grid correcto para el mes actual.
- [ ] Los roles controlan la visibilidad de menús y acciones (admin vs user vs sailburua).
- [ ] Los formularios validan client-side antes de enviar a la API.
- [ ] La SPA funciona en Chrome, Firefox y Safari modernos.
- [ ] Accesibilidad básica: contraste WCAG AA, navegación por teclado en formularios críticos.
- [ ] `vitest` pasa todos los tests de componentes.
- [ ] `playwright` pasa el test e2e del flujo de creación de solicitud.

---

## Internacionalización (i18n)

La UI está en **euskera** con soporte opcional de castellano. Usar `vue-i18n`:

```bash
npm install vue-i18n
```

```
src/locales/
├── eu.json    # euskera (principal)
└── es.json    # castellano (secundario)
```

Las etiquetas del dominio (nombres de entidades en euskera) no se traducen — son términos institucionales.
