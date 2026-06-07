# 04 — Diseño de endpoints API Platform 4

## Convenciones

- Todos los recursos bajo `/api/`.
- Formato primario: `application/json` (también `application/ld+json` para JSON-LD).
- Paginación por defecto: 30 items. Filtros de API Platform en GET collections.
- Seguridad declarada por operación con `security` en `#[ApiResource]`.
- Los DTOs de entrada/salida se indican con `input`/`output` en las operaciones que no mapean 1:1.
- Los grupos de serialización: `read` para GET, `write` para POST/PUT/PATCH.

---

## 1. `User` — `/api/users`

| Operación | Método | URI | Rol mínimo | Notas |
|---|---|---|---|---|
| Listar usuarios activos | GET | `/api/users` | `ROLE_ADMIN` | filtros: `saila`, `taldea`, `aktibo`, `username` |
| Ver usuario | GET | `/api/users/{id}` | `ROLE_USER` | Voter: solo propio o admin |
| Actualizar usuario | PATCH | `/api/users/{id}` | `ROLE_ADMIN` | actualizar `saila`, `taldea`, `roles`, `aktibo` |
| Dar de alta | PATCH | `/api/users/{id}/alta` | `ROLE_ADMIN` | custom operation: `aktibo=true` |
| Dar de baja | PATCH | `/api/users/{id}/baja` | `ROLE_ADMIN` | custom operation: `aktibo=false` |
| Asignar sailburua | PATCH | `/api/users/{id}/sailburua` | `ROLE_ADMIN` | input: `{sailaId: int, esSailburua: bool}` |
| Eskaerak del usuario | GET | `/api/users/{id}/eskaerak` | `ROLE_ADMIN` \| propio | shortcut al filtro de Eskaera |

**Campos excluidos de la salida:** password hash, dn completo (sólo en ROLE_ADMIN).

---

## 2. `Saila` (Departamento) — `/api/sailak`

| Operación | Método | URI | Rol | Notas |
|---|---|---|---|---|
| Listar | GET | `/api/sailak` | `ROLE_USER` | |
| Ver | GET | `/api/sailak/{id}` | `ROLE_USER` | incluye `users` en grupo `saila:read:detail` |
| Crear | POST | `/api/sailak` | `ROLE_ADMIN` | |
| Editar | PATCH | `/api/sailak/{id}` | `ROLE_ADMIN` | |
| Quitar usuario | DELETE | `/api/sailak/{id}/users/{userId}` | `ROLE_ADMIN` | custom operation |
| Asignar zinegotzi | POST | `/api/sailak/{id}/zinegotziak/{userId}` | `ROLE_ADMIN` | |

---

## 3. `Taldea` (Equipo) — `/api/taldeak`

Similar a Saila. Operaciones: CRUD + asignar/quitar usuarios + zinegotziak.

---

## 4. `Calendar` — `/api/calendars`

| Operación | Método | URI | Rol | Notas |
|---|---|---|---|---|
| Listar | GET | `/api/calendars` | `ROLE_ADMIN` | filtros: `user`, `year` |
| Calendarios de un usuario | GET | `/api/calendars?user={username}` | `ROLE_USER` | Voter: solo propio o admin |
| Ver | GET | `/api/calendars/{id}` | Voter | |
| Crear | POST | `/api/calendars` | `ROLE_ADMIN` | input: puede incluir `templateId` para pre-rellenar |
| Editar | PATCH | `/api/calendars/{id}` | `ROLE_ADMIN` | |
| Eliminar | DELETE | `/api/calendars/{id}` | `ROLE_SUPER_ADMIN` | |
| Comparar calendarios | POST | `/api/calendars/compare` | `ROLE_ADMIN` | custom, input: `[calendarId1, calendarId2]` |
| PDF/imprimir | GET | `/api/calendars/{id}/pdf` | Voter | genera PDF con KnpSnappy |

---

## 5. `Event` — `/api/events`

| Operación | Método | URI | Rol | Notas |
|---|---|---|---|---|
| Listar por calendario | GET | `/api/events?calendar={id}` | Voter | |
| Ver | GET | `/api/events/{id}` | Voter | |
| Crear | POST | `/api/events` | `ROLE_ADMIN` | Processor: llama `CalendarHourService::addEvent()` |
| Editar | PATCH | `/api/events/{id}` | `ROLE_ADMIN` | Processor: recalcula horas |
| Eliminar | DELETE | `/api/events/{id}` | `ROLE_ADMIN` | Processor: restaura horas |

**Input DTO para POST/PATCH:**
```json
{
  "calendar": "/api/calendars/42",
  "type": "/api/types/3",
  "startDate": "2025-08-01",
  "endDate": "2025-08-15",
  "egunorduak": "egunak",
  "hours": null,
  "name": "Oporrak"
}
```

---

## 6. `Type` (Tipo) — `/api/types`

| Operación | Método | URI | Rol |
|---|---|---|---|
| Listar | GET | `/api/types` | `ROLE_USER` |
| Ver | GET | `/api/types/{id}` | `ROLE_USER` |
| Crear | POST | `/api/types` | `ROLE_ADMIN` |
| Editar | PATCH | `/api/types/{id}` | `ROLE_ADMIN` |
| Tipos de un template | GET | `/api/types?template={id}` | `ROLE_USER` |

---

## 7. `Template` — `/api/templates`

| Operación | Método | URI | Rol |
|---|---|---|---|
| Listar | GET | `/api/templates` | `ROLE_ADMIN` |
| Ver | GET | `/api/templates/{id}` | `ROLE_ADMIN` |
| Crear | POST | `/api/templates` | `ROLE_ADMIN` |
| Editar | PATCH | `/api/templates/{id}` | `ROLE_ADMIN` |
| Copiar | POST | `/api/templates/{id}/copy` | `ROLE_ADMIN` |

---

## 8. `TemplateEvent` — `/api/template-events`

| Operación | Método | URI | Rol |
|---|---|---|---|
| Listar por template | GET | `/api/template-events?template={id}` | `ROLE_ADMIN` |
| Crear | POST | `/api/template-events` | `ROLE_ADMIN` |
| Editar | PATCH | `/api/template-events/{id}` | `ROLE_ADMIN` |
| Eliminar | DELETE | `/api/template-events/{id}` | `ROLE_ADMIN` |

---

## 9. `Lizentziamota` — `/api/lizentziamota`

| Operación | Método | URI | Rol |
|---|---|---|---|
| Listar | GET | `/api/lizentziamota` | `ROLE_USER` |
| Ver | GET | `/api/lizentziamota/{id}` | `ROLE_USER` |
| Crear | POST | `/api/lizentziamota` | `ROLE_ADMIN` |
| Editar | PATCH | `/api/lizentziamota/{id}` | `ROLE_ADMIN` |

---

## 10. `Eskaera` (Solicitud) — `/api/eskaerak` ⭐ recurso principal

### Operaciones CRUD estándar

| Operación | Método | URI | Rol | Notas |
|---|---|---|---|---|
| Listar (admin) | GET | `/api/eskaerak` | `ROLE_ADMIN` | filtros abajo |
| Mis solicitudes | GET | `/api/eskaerak?user={username}` | `ROLE_USER` | Voter: solo propio |
| Ver | GET | `/api/eskaerak/{id}` | Voter | |
| Crear solicitud | POST | `/api/eskaerak` | `ROLE_USER` | Processor: conflict detection, crear Firma |
| Editar | PATCH | `/api/eskaerak/{id}` | `ROLE_ADMIN` | |
| Cancelar | PATCH | `/api/eskaerak/{id}/bertan-behera` | `ROLE_ADMIN` \| propietario | state → BERTANBEHERA |
| Eliminar | DELETE | `/api/eskaerak/{id}` | `ROLE_SUPER_ADMIN` | |
| PDF solicitud | GET | `/api/eskaerak/{id}/pdf` | Voter | KnpSnappy |
| PDF ikastaroa | GET | `/api/eskaerak/{id}/ikastaroa-pdf` | Voter | |
| PDF ordainketa | GET | `/api/eskaerak/{id}/ordainketa-pdf` | Voter | |
| Instancias imprimibles | GET | `/api/eskaerak/instantziak` | `ROLE_ADMIN` | |

### Operaciones de negocio custom ⭐

| Operación | Método | URI | Rol | Processor |
|---|---|---|---|---|
| **Firmar / aprobar paso** | PATCH | `/api/eskaerak/{id}/firmar` | firmante autorizado | `FirmarEskaeraProcessor` — unifica `putFirma`+`putPostit` |
| **Procesar jakinarazpena** | PATCH | `/api/eskaerak/{id}/jakinarazpena` | firmante / bideratzailea | `ProcessarJakinarazpenaProcessor` |
| **Añadir al calendario** | PATCH | `/api/eskaerak/{id}/egutegira-gehitu` | `ROLE_BIDERATZAILEA` | `AddToCalendarProcessor` — crea Events via `CalendarHourService` |
| **Subir justificante** | POST | `/api/eskaerak/{id}/justifikantea` | propietario | upload de fichero via Flysystem |
| **Transfer** | POST | `/api/eskaerak/{id}/transfer` | `ROLE_BIDERATZAILEA` | `TransferEskaeraProcessor` — mueve notificaciones y signer |
| **Asignar sinatzaileak** | PATCH | `/api/eskaerak/{id}/sinatzaileak` | `ROLE_BIDERATZAILEA` | input: `{sinatzaileakId}` |

**Input DTO para `POST /api/eskaerak`:**
```json
{
  "typeLabur": "OPO",
  "calendar": "/api/calendars/42",
  "user": "/api/users/5",
  "hasi": "2025-08-01",
  "amaitu": "2025-08-15",
  "egunak": 11,
  "orduak": null,
  "oharra": "Udako oporrak",
  "lizentziamota": "/api/lizentziamota/3",
  "sinatzaileak": "/api/sinatzaileak/1"
}
```
Para `typeLabur=IKA` se añaden los campos de ikastaroa:
```json
{
  "erakundea": "IVAP",
  "non": "Vitoria-Gasteiz",
  "sareko": false,
  "kostua": 150.00,
  "ordaindubeharda": true,
  "ordainduta": 150.00,
  "udalakordainduta": 0,
  "ikastaroaHizkuntza": "eu"
}
```

**Filtros disponibles en `GET /api/eskaerak`:**
- `state` (`draft|pending|approved|rejected|cancelled|in_calendar|justified`)
- `user` (IRI o username)
- `type` (IRI o `labur`)
- `lizentziamota` (IRI)
- `calendar.year`
- `bideratua` (bool)
- `konfliktoa` (bool)
- `hasi[after]`, `hasi[before]` (rango de fechas)
- `page`, `itemsPerPage`

---

## 11. `Sinatzaileak` — `/api/sinatzaileak`

| Operación | Método | URI | Rol |
|---|---|---|---|
| Listar | GET | `/api/sinatzaileak` | `ROLE_ADMIN` |
| Ver (con miembros) | GET | `/api/sinatzaileak/{id}` | `ROLE_ADMIN` |
| Crear | POST | `/api/sinatzaileak` | `ROLE_ADMIN` |
| Editar | PATCH | `/api/sinatzaileak/{id}` | `ROLE_ADMIN` |
| Eliminar | DELETE | `/api/sinatzaileak/{id}` | `ROLE_ADMIN` |

---

## 12. `Sinatzaileakdet` — `/api/sinatzaileakdet`

| Operación | Método | URI | Rol | Notas |
|---|---|---|---|---|
| Listar por grupo | GET | `/api/sinatzaileakdet?sinatzaileak={id}` | `ROLE_ADMIN` | |
| Crear | POST | `/api/sinatzaileakdet` | `ROLE_ADMIN` | |
| Editar (incluye orden) | PATCH | `/api/sinatzaileakdet/{id}` | `ROLE_ADMIN` | |
| Reordenar arriba | PATCH | `/api/sinatzaileakdet/{id}/up` | `ROLE_ADMIN` | custom |
| Reordenar abajo | PATCH | `/api/sinatzaileakdet/{id}/down` | `ROLE_ADMIN` | custom |
| Eliminar | DELETE | `/api/sinatzaileakdet/{id}` | `ROLE_ADMIN` | |

---

## 13. `Firma` — `/api/firmak`

| Operación | Método | URI | Rol |
|---|---|---|---|
| Ver | GET | `/api/firmak/{id}` | Voter |
| Ver firmantes de eskaera | GET | `/api/firmak?eskaera={id}` | Voter |
| Ver firmantes de notificación | GET | `/api/firmak?notification={id}` | Voter |

*(La creación de Firma es automática al crear Eskaera en el Processor; no hay POST público.)*

---

## 14. `Notification` (Jakinarazpena) — `/api/notifications`

| Operación | Método | URI | Rol | Notas |
|---|---|---|---|---|
| Mis notificaciones | GET | `/api/notifications?user={id}` | propietario | Voter |
| Ver | GET | `/api/notifications/{id}` | propietario | |
| Marcar leída | PATCH | `/api/notifications/{id}/irakurrita` | propietario | `readed=true` |
| Transfer notificación | POST | `/api/notifications/{id}/transfer` | `ROLE_BIDERATZAILEA` | input: `{targetUserId}` |
| Ver para firma | GET | `/api/notifications?sinatzeprozesua=true&user={id}` | propietario | bandeja de firmas |
| Eliminar | DELETE | `/api/notifications/{id}` | `ROLE_ADMIN` | |

---

## 15. `Gutxienekoak` — `/api/gutxienekoak`

| Operación | Método | URI | Rol |
|---|---|---|---|
| Listar | GET | `/api/gutxienekoak` | `ROLE_ADMIN` |
| Ver (con miembros) | GET | `/api/gutxienekoak/{id}` | `ROLE_ADMIN` |
| Crear | POST | `/api/gutxienekoak` | `ROLE_ADMIN` |
| Editar | PATCH | `/api/gutxienekoak/{id}` | `ROLE_ADMIN` |

---

## 16. `Kuadrantea` (Grid mensual) — `/api/kuadrantea` ⭐

| Operación | Método | URI | Rol | Notas |
|---|---|---|---|---|
| Grid departamento | GET | `/api/kuadrantea/{year}/{month}` | `ROLE_ADMIN` | filtro `saila` opcional |
| Grid con eskaerak | GET | `/api/kuadrantea/{year}/{month}/eskaerekin` | `ROLE_ADMIN` | incluye solicitudes |
| Grid sailburua | GET | `/api/kuadrantea/{year}/{month}/sailburuarentzat` | `ROLE_SAILBURUA` | solo su departamento |
| Rebuild manual | POST | `/api/kuadrantea/rebuild` | `ROLE_ADMIN` | dispara `KuadranteaRebuildMessage` |

**Output DTO (grid por usuario):**
```json
{
  "year": 2025,
  "month": 8,
  "users": [
    {
      "username": "jperez",
      "displayname": "Josu Pérez",
      "days": {
        "1": "OPO", "2": "OPO", "3": "", ...
      }
    }
  ]
}
```

---

## 17. `Document` — `/api/documents`

| Operación | Método | URI | Rol | Notas |
|---|---|---|---|---|
| Listar por calendario | GET | `/api/documents?calendar={id}` | Voter | |
| Subir | POST | `/api/documents` | `ROLE_ADMIN` | multipart/form-data, Flysystem |
| Reordenar up/down | PATCH | `/api/documents/{id}/up` \| `/down` | `ROLE_ADMIN` | |
| Eliminar | DELETE | `/api/documents/{id}` | `ROLE_ADMIN` | |

---

## 18. `Message` (Broadcast) — `/api/messages`

| Operación | Método | URI | Rol | Notas |
|---|---|---|---|---|
| Listar | GET | `/api/messages` | `ROLE_ADMIN` | |
| Mis mensajes | GET | `/api/messages?user={id}` | propietario | |
| Ver | GET | `/api/messages/{id}` | Voter | |
| Crear (broadcast) | POST | `/api/messages` | `ROLE_ADMIN` | enviar a todos o a un usuario |
| Eliminar | DELETE | `/api/messages/{id}` | `ROLE_ADMIN` | |

---

## 19. Reports — `/api/reports`

Endpoints de sólo lectura que devuelven datos de informe. Implementados como Custom State Providers.

| Endpoint | Rol | Descripción |
|---|---|---|
| `GET /api/reports/absentismo` | `ROLE_ADMIN` | Informe de absentismo (filtros: `year`, `saila`, `type`) |
| `GET /api/reports/konpentsatuak` | `ROLE_ADMIN` | Horas compensadas por usuario y año (filtros: `year`, `saila`, `calendar`) |
| `GET /api/reports/balance-anual` | `ROLE_ADMIN` | Balance anual de horas por calendario |

---

## 20. `Log` — `/api/logs`

| Operación | Método | URI | Rol |
|---|---|---|---|
| Listar | GET | `/api/logs` | `ROLE_ADMIN` |
| Ver | GET | `/api/logs/{id}` | `ROLE_ADMIN` |

---

## 21. `Hour` — `/api/hours`

| Operación | Método | URI | Rol | Notas |
|---|---|---|---|---|
| Listar por calendario | GET | `/api/hours?calendar={id}` | `ROLE_ADMIN` | |
| Crear | POST | `/api/hours` | `ROLE_ADMIN` | |
| Editar | PATCH | `/api/hours/{id}` | `ROLE_ADMIN` | |
| Eliminar | DELETE | `/api/hours/{id}` | `ROLE_ADMIN` | |

---

## Operaciones custom — State Processors de referencia

### `FirmarEskaeraProcessor`

Unifica `putFirmaAction` y `putPostitAction` del legacy. Debe:
1. Verificar que el usuario autenticado es el firmante esperado (siguiente en la cadena).
2. Crear un `Firmadet` con `firmatua=true`, `noiz=now()`, `postit` (opcional), `firmatzaile`.
3. Comprobar si quedan firmantes pendientes (leer `Sinatzaileakdet` ordenados por `orden`).
4. Si quedan: crear `Notification` para el siguiente; `Firma.orden++`.
5. Si no quedan: `Firma.completed=true`, `Eskaera.state=ONARTUA`.
6. Si `Eskaera.type.labur === 'IKA'` y state → ONARTUA: despachar `IkastaroaApprovedMessage` (Messenger).
7. Guardar y devolver la `Eskaera` actualizada.

### `ProcessarJakinarazpenaProcessor`

Procesa aprobación/rechazo desde la bandeja de notificaciones:
1. Input: `{result: 'onartua'|'ukatua', postit?: string}`.
2. Marcar `Notification.completed=true`, `Notification.result`.
3. Si `ukatua`: `Eskaera.state=UKATUA`, despachar `RejectionEmailMessage`.
4. Si `onartua`: avanzar en la cadena de firmas (igual que `FirmarEskaeraProcessor` desde paso 3).

### `AddToCalendarProcessor`

1. Verificar `Eskaera.state === ONARTUA`.
2. Calcular días laborables entre `hasi` y `amaitu` (excluyendo festivos del calendario del usuario).
3. Para cada día (o para el rango completo si es `egunorduak='egunak'`): llamar `CalendarHourService::addEvent()`.
4. `Eskaera.state = EGUTEGIAN`.
5. Si falla el descuento de horas: devolver error 422 con detalle.

---

## Notas de seguridad por recurso

| Recurso | Voter clave |
|---|---|
| Calendar | solo el propietario o ROLE_ADMIN puede ver/editar |
| Eskaera | propietario (ver), ROLE_BIDERATZAILEA (tramitar), firmante autorizado (firmar) |
| Notification | solo el usuario asignado |
| Kuadrantea sailburua | solo ve su propio departamento (saila) |
| Reports | ROLE_ADMIN o ROLE_SAILBURUA (solo su saila) |
