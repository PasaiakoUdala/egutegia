# 05 — Lógica de negocio

Este documento describe los algoritmos y reglas de negocio críticos que deben portarse al nuevo sistema. Son el núcleo de la aplicación — cualquier error aquí tiene impacto directo en los empleados.

Todos los servicios de dominio viven bajo `src/Domain/` y **no tienen dependencias de Symfony** (sin Request, sin Controller, sin Entity Manager directo — usan interfaces de repositorio).

---

## 1. Contabilidad de horas — `CalendarHourService::addEvent()`

### Ubicación actual
`src/AppBundle/Service/CalendarService::addEvent($datuak)`

### Ubicación destino
`src/Domain/Calendar/Service/CalendarHourService`

### Algoritmo

Dado un `Event` a crear (con `Calendar`, `Type`, `egunorduak`, `hours`):

```
1. Leer Type.related:
   - null / '' → no descuenta horas del Calendar; solo registrar el Event.
   - 'hours_free' → descuenta de Calendar.hoursFree (vacaciones - OPO).
   - 'hours_self' → descuenta de Calendar.hoursSelf o Calendar.hoursSelfHalf.
   - 'hours_compensed' → descuenta de Calendar.hoursCompensed (KON).
   - 'hours_sindikal' → descuenta de Calendar.hoursSindikal (SIN).

2. Si related = 'hours_self':
   a. Si egunorduak = 'egunak' (día completo):
      - descuenta hours_self -= hours
   b. Si egunorduak = 'orduak' (horas sueltas):
      - descuenta hours_self_half -= hours

3. Verificar que el bucket tiene saldo suficiente:
   - Si Calendar.{bucket} < hours → RETURN error: {result: -1, message: 'Ezin da...'}
   - Si OK → Calendar.{bucket} -= hours; RETURN {result: 1}

4. Para Type con labur='NAE' (norberarentzako, legacy type=5):
   - Guardar Event.hoursSelfBefore = Calendar.hoursSelf (antes del descuento)
   - Guardar Event.hoursSelfHalfBefore = Calendar.hoursSelfHalf
   - Guardar Event.nondik = 'hours_self' o 'hours_self_half' según egunorduak

5. Crear el Event y persistir.
6. Crear una entrada en Log con el descuento.
```

### Firma del servicio destino

```php
class CalendarHourService
{
    public function addEvent(Calendar $calendar, Type $type, Event $event): AddEventResult;
    public function removeEvent(Calendar $calendar, Type $type, Event $event): void;
    public function recalculateAfterEdit(Calendar $calendar, Type $type, Event $before, Event $after): AddEventResult;
}

readonly class AddEventResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
    ) {}
}
```

### Tests obligatorios

- Descuento correcto de cada bucket.
- Error cuando saldo insuficiente.
- Comportamiento especial NAE (hoursSelfBefore guardado).
- Sin descuento cuando `Type.related` es null.
- Restauración al eliminar un Event.

---

## 2. Workflow de firmas — `FirmaWorkflowService`

### Descripción
Una `Eskaera` en estado `ABIATUA` tiene una `Firma` asociada con una cadena ordenada de `Sinatzaileakdet`. Cada paso:
1. El firmante actual firma (crea un `Firmadet`).
2. Si hay más firmantes: crear `Notification` para el siguiente, avanzar `Firma.orden`.
3. Si no hay más: completar la `Firma`, resolver la `Eskaera`.

### Ubicación actual
`src/ApiBundle/Controller/ApiController::putFirmaAction()` y `putPostitAction()`
(Son funcionalmente idénticos — el código tiene comentario `// OHARRA: berdin egon behar dira`)

### Ubicación destino
`src/Domain/Firma/Service/FirmaWorkflowService`

### Algoritmo

```
ENTRADA: eskaera: Eskaera, firmatzaile: User, postit?: string, autofirma: bool = false

1. Cargar Firma de la Eskaera. Si no existe → error 400.

2. Cargar Sinatzaileakdet[] de Firma.sinatzaileak, ordenados por orden ASC.

3. Determinar el paso actual: Firmadet ya creados = Firma.orden - 1 (base 0).

4. Obtener el Sinatzaileakdet esperado (paso actual).
   Verificar que firmatzaile === sinatzaileakdet.user → si no, 403 Forbidden.

5. Crear Firmadet:
   firmadet.firma = Firma
   firmadet.sinatzaileakdet = sinatzaileakdet actual
   firmadet.firmatzaile = firmatzaile (usuario autenticado)
   firmadet.firmatua = true
   firmadet.noiz = now()
   firmadet.postit = postit (si se proporcionó)
   firmadet.autofirma = autofirma
   firmadet.orden = Firma.orden

6. ¿Hay más firmantes? (Firma.orden + 1 < count(sinatzaileakdets))
   SI:
     Firma.orden += 1
     Crear Notification para sinatzaileakdets[Firma.orden].user:
       notification.firma = Firma
       notification.eskaera = Eskaera
       notification.user = siguiente firmante
       notification.sinatzeprozesua = true
       notification.orden = Firma.orden
       notification.name = 'Sinatzeko eskaera: ' + Eskaera.name
   NO:
     Firma.completed = true
     Eskaera.state = EskaeraState::ONARTUA
     Marcar todas las Notifications de la Firma como completed=true

7. Persistir Firmadet, Firma, Eskaera, Notification.

8. Devolver Eskaera actualizada.
```

### Autofirma (para IKA/ikastaroa)

En el legacy, `EskaeraController` (al gestionar un curso completado) llama internamente a `PUT /api/firma/{id}` vía Guzzle para auto-firmar. En el nuevo sistema:

```php
$firmaWorkflowService->procesarFirma(
    eskaera: $eskaera,
    firmatzaile: $currentUser,
    postit: null,
    autofirma: true
);
```
Sin HTTP intermedio.

---

## 3. Procesado de jakinarazpena (aprobación/rechazo desde bandeja)

### Ubicación actual
`ApiController::putJakinarazpenaAction()`

### Algoritmo

```
ENTRADA: notification: Notification, result: 'onartua'|'ukatua', user: User, postit?: string

1. Verificar notification.user === user → si no, 403.

2. Marcar notification.completed = true, notification.result = result, notification.readed = true.

3. Si result = 'ukatua':
   a. Eskaera.state = EskaeraState::UKATUA
   b. Firmadet con firmatua=false, postit=postit, autofirma=false
   c. Despachar RejectionEmailMessage → email a bideratzaileak
   d. Marcar todas las Notifications de la Firma como completed=true

4. Si result = 'onartua':
   a. Llamar FirmaWorkflowService::procesarFirma(eskaera, user, postit, autofirma=false)
   b. (El servicio crea el Firmadet y avanza la cadena, ver §2)
```

---

## 4. Detección de conflictos — `ConflictDetectorService`

### Ubicación actual
`EskaeraController::newAction()` + `EskaeraRepository::checkErabiltzaileaBateraezinZerrendan()` + `EskaeraRepository::checkCollision()` + `EventRepository::checkCollision()`

### Ubicación destino
`src/Domain/Eskaera/Service/ConflictDetectorService`

### Algoritmo

```
ENTRADA: eskaera: Eskaera (con hasi, amaitu, user, calendar)

RESULTADO: ConflictResult { hasConflict: bool, messages: string[] }

PASO 1 — Colisión con Events existentes:
  Buscar Events del mismo Calendar que se solapen con [hasi, amaitu]:
    WHERE event.calendar = :calendar
      AND event.startDate <= :amaitu
      AND (event.endDate >= :hasi OR event.startDate >= :hasi)
  Si hay resultados → messages[] += 'Badago gatazka egutegian: {event.name}'

PASO 2 — Colisión con Eskaerak existentes (del mismo usuario):
  Buscar Eskaerak del mismo User (mismo Calendar.year) que se solapen:
    WHERE eskaera.user = :user
      AND eskaera.state NOT IN (BERTANBEHERA, UKATUA)
      AND eskaera.hasi <= :amaitu
      AND eskaera.amaitu >= :hasi
      AND eskaera.id != :current_id
  Si hay resultados → messages[] += 'Badago gatazka eskaerarekin'

PASO 3 — Reglas de mínimos de plantilla (Gutxienekoak):
  Para cada Gutxienekoak activo:
    Cargar sus Gutxienekoakdet.users
    Si :user está en ese grupo:
      Contar cuántos miembros del grupo tienen permisos aprobados/pendientes
      en el rango [hasi, amaitu] (Eskaerak state IN (ABIATUA, ONARTUA))
      Si count / total_members * 100 > Gutxienekoak.portzentaia:
        messages[] += 'Gutxienekoak: ezin da {Gutxienekoak.name}'

SI messages no vacío:
  eskaera.konfliktoa = true
  eskaera.oharra = join('\n', messages) + (oharra previo si había)
RETORNAR ConflictResult
```

**Nota:** el conflicto NO bloquea la creación de la solicitud. Solo marca `eskaera.konfliktoa=true` y añade información en `oharra`. Es el bideratzailea quien decide.

---

## 5. Email "maika" — aprobación de cursos IKA

### Cuándo se envía
Cuando una `Eskaera` con `type.labur === 'IKA'` alcanza `state = ONARTUA` (todas las firmas completas).

### Ubicación actual
`ApiController::putFirmaAction()` y `putPostitAction()` — con guard explícito `if ($eskaera->getType()->getLabur() === 'IKA')`.

### Ubicación destino
`src/Application/Eskaera/IkastaroaApprovedHandler` (Messenger Handler)

### Mensaje Messenger
```php
readonly class IkastaroaApprovedMessage
{
    public function __construct(public readonly int $eskaeraId) {}
}
```

### Handler
```php
class IkastaroaApprovedHandler implements MessageHandlerInterface
{
    public function __invoke(IkastaroaApprovedMessage $message): void
    {
        $eskaera = $this->eskaeraRepository->find($message->eskaeraId);

        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to('maika@pasaia.net')
            ->subject('[Egutegia] Ikastaro berria: ' . $eskaera->getName())
            ->htmlTemplate('emails/ikastaroa_onartua.html.twig')
            ->context([
                'eskaera' => $eskaera,
                'user'    => $eskaera->getUser(),
            ]);

        // Adjuntar ordainketaFile si existe
        if ($eskaera->getOrdainketaFilePath()) {
            $fileContent = $this->storageService->read($eskaera->getOrdainketaFilePath());
            $email->attach($fileContent, $eskaera->getOrdainketaFilePath());
        }

        $this->mailer->send($email);
    }
}
```

### Plantilla de email (`templates/emails/ikastaroa_onartua.html.twig`)
Debe incluir:
- Nombre del curso (`eskaera.name`)
- Empleado (`user.displayname`)
- Fechas (`eskaera.hasi` – `eskaera.amaitu`)
- Coste total (`eskaera.kostua`)
- Pagado por empleado (`eskaera.ordainduta`)
- Pagado por el Ayuntamiento (`eskaera.udalakordainduta`)
- Institución (`eskaera.erakundea`)
- Lugar (`eskaera.non`) / online (`eskaera.sareko`)

---

## 6. Email de rechazo — `RejectionEmailHandler`

### Cuándo se envía
Cuando una `Eskaera` es rechazada (`result = 'ukatua'` en `putJakinarazpena`).

### Ubicación actual
`ApiController::putJakinarazpenaAction()` — envía a los usuarios con `ROLE_BIDERATZAILEA`.

### Ubicación destino
`src/Application/Firma/RejectionEmailHandler`

### Handler
```php
class RejectionEmailHandler implements MessageHandlerInterface
{
    public function __invoke(RejectionEmailMessage $message): void
    {
        $eskaera = $this->eskaeraRepository->find($message->eskaeraId);
        $bideratzaileak = $this->userRepository->findByRole('ROLE_BIDERATZAILEA');

        foreach ($bideratzaileak as $bideratzailea) {
            $email = (new TemplatedEmail())
                ->from($this->mailerFrom)
                ->to($bideratzailea->getEmail())
                ->subject('[Egutegia][Jakinarazpen berria][EZ Onartua!!] ' . $eskaera->getName())
                ->htmlTemplate('emails/eskaera_ukatua.html.twig')
                ->context(['eskaera' => $eskaera]);

            $this->mailer->send($email);
        }
    }
}
```

---

## 7. Emails de notificación de firma pendiente — `NotifyPendingSignaturesHandler`

### Ubicación actual
`src/AppBundle/Command/NotifyCommand` (`app:notify`)

### Lógica
```
Cargar todas las Notifications con:
  notified = false AND sinatzeprozesua = true AND completed = false

Agrupar por notification.user.email

Para cada grupo (un firmante):
  Enviar un único email con la lista de eskaerak pendientes de firma
  Marcar todas notification.notified = true

NOTA LEGACY: hay un redirect hardcodeado igomez@pasaia.net → atorrado@pasaia.net.
En el nuevo sistema esto debe ser configurable (parámetro en services.yaml).
```

### Frecuencia en el nuevo sistema
Registrar como tarea en Symfony Scheduler (`RecurringMessage::cron('0 8 * * 1-5', ...)`).

---

## 8. Sincronización de eskaerak → calendario — `EskaerakEgutegianSyncHandler`

### Ubicación actual
`AppCheckEskaerakEgutegianCommand` (`app:check-eskaerak-egutegian`)

### Lógica
```
1. Cargar todos los Calendars del año actual.
2. Para cada Calendar:
   a. Cargar Eskaerak con state=ONARTUA y NOT en TempEskaerakEgutegian
   b. Para cada Eskaera: intentar AddToCalendarUseCase
   c. Si OK: insertar en TempEskaerakEgutegian (marca de proceso)
3. Si hay eskaerak no procesadas: enviar email de aviso a :admin_email
   (legacy: rgonzalez@pasaia.net → parámetro configurable)
```

---

## 9. Rebuild de cuadrantes — `KuadranteaRebuildHandler`

### Ubicación actual
`KuadranteaCommand` (`app:kuadrantea`) + `KuadranteaEskaerekinCommand` (`app:kuadrantea-eskaerekin`)

### Lógica
Para un `year` y `month` dados:
```
1. Para cada User activo:
   a. Cargar Events del Calendar del usuario en ese month/year
   b. Para cada día del mes: si hay Event ese día → day_XX = Type.labur
   c. Guardar/actualizar Kuadrantea(user, year, month, day01..day31)

2. Para KuadranteaEskaerekin (versión con solicitudes):
   a. Igual que arriba pero incluir también Eskaerak con state IN (ABIATUA, ONARTUA)
   b. Calcular columnas de resumen: jardunaldia, oporrak, nae, konpentsatuak
```

**Hack legacy a eliminar:** en `KuadranteaEskaerekinRepository`, hay un bloque que fuerza `username='acuevas'` al departamento id=3 para un caso especial de plantilla. Investigar con el cliente si este caso especial sigue siendo necesario; si sí, modelarlo de forma explícita (campo en User o Saila).

---

## 10. Completado automático de firma — `EskaeraCompletedCommand`

### Ubicación actual
`EskaeraCompletedCommand` (`app:eskaera_completed`)

### Lógica
```
Buscar Firmas con completed=true cuya Eskaera todavía NO tiene state=ONARTUA/UKATUA
→ actualizar Eskaera.state = ONARTUA

(Este command existe como seguridad ante condiciones de carrera o fallos del webhook.
En el nuevo sistema puede ser un Messenger retry o un Scheduler nightly.)
```

---

## 11. Gestión de ficheros (Flysystem)

### Tipos de fichero en Eskaera

| Campo | Descripción | Slot |
|---|---|---|
| `justifikanteFilePath` | Justificante de la solicitud | único |
| `ikastaroaFilePath` | Certificado/material del curso | 1 de 3 |
| `ikastaroaFile2Path` | Certificado 2 | |
| `ikastaroaFile3Path` | Certificado 3 | |
| `ordainketaFilePath` | Justificante de pago | único |

### Servicio de almacenamiento

```php
interface StorageServiceInterface
{
    public function upload(UploadedFile $file, string $context): string; // returns path
    public function read(string $path): string;
    public function delete(string $path): void;
    public function getUrl(string $path): string;
}
```

Implementar con `league/flysystem-bundle` usando el adaptador local en dev y S3/sftp en prod (configurable).

---

## 12. Generación de PDF

### Servicios actuales
- `knp_snappy.pdf` (wkhtmltopdf) — vía `KnpSnappyBundle`.
- Plantillas: `eskaera/show.html.twig`, `eskaera/ikastaroapdf.html.twig`, `eskaera/ordainketapdf.html.twig`.

### Opciones para el nuevo sistema

| Opción | Pros | Contras |
|---|---|---|
| Mantener KnpSnappyBundle | Sin cambios en plantillas | wkhtmltopdf EOL, difícil de contenedorizar |
| **Gotenberg** | Contenedorizable, activo | Requiere servicio Docker adicional |
| Puppeteer/Chrome headless | Moderno | Pesado |

**Recomendación:** usar `gotenberg/gotenberg-php` + servicio Docker `gotenberg/gotenberg`. Las plantillas Twig de PDF se convierten en HTML servido al contenedor Gotenberg.

```yaml
# compose.yaml (añadir)
  gotenberg:
    image: gotenberg/gotenberg:8
    ports: ["3000:3000"]
```

### Endpoints PDF
Ver `04-endpoints-api.md` §10. Devolver `Response` con `Content-Type: application/pdf`.
