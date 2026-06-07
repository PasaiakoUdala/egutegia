# 08 — Commands, Scheduler, Mailer y ficheros

## 1. Commands de consola (portar a Symfony 7)

El legacy tiene 10 commands en `src/AppBundle/Command/`. En el nuevo sistema, la mayoría de su lógica se mueve a **Application Services** y se exponen vía dos mecanismos: `bin/console` para operaciones manuales y **Symfony Scheduler** para las tareas automáticas.

### Mapa de migración

| Command legacy | Clase nueva | Mecanismo | Frecuencia sugerida |
|---|---|---|---|
| `app:notify` | `NotifyPendingSignaturesCommand` | Scheduler | Lunes-viernes 8:00 |
| `app:check-eskaerak-egutegian` | `CheckEskaerakEgutegianCommand` | Scheduler | Diaria 7:00 |
| `app:eskaera_completed` | `EskaeraCompletedCommand` | Scheduler | Cada hora |
| `app:kuadrantea` | `RebuildKuadranteaCommand` | Scheduler | Diaria 6:00 + manual |
| `app:kuadrantea-eskaerekin` | `RebuildKuadranteaEskaerekinCommand` | Scheduler | Diaria 6:30 + manual |
| `app:jakinarazpen_aldaketa` | `TransferNotificationsCommand` | Solo CLI | Manual |
| `app:sinatzaile_aldaketa` | `ChangeSinatzaileCommand` | Solo CLI | Manual |
| `app:delete_duplicates` | `DeleteDuplicateNotificationsCommand` | Solo CLI | Manual/ocasional |
| `app:user:add-role-udaltzaina` | `AssignRoleCommand` | Solo CLI | Manual |
| `app:user:remove-role` | `RemoveRoleCommand` | Solo CLI | Manual |

---

## 2. Symfony Scheduler

Usar `symfony/scheduler` (disponible desde Symfony 6.3+).

```php
// src/Scheduler/MainSchedule.php
#[AsSchedule('main')]
class MainSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            // Notificar firmantes pendientes: lunes-viernes a las 8:00
            ->add(RecurringMessage::cron('0 8 * * 1-5',
                new NotifyPendingSignaturesMessage()))

            // Verificar eskaerak no añadidas al calendario: diaria 7:00
            ->add(RecurringMessage::cron('0 7 * * *',
                new CheckEskaerakEgutegianMessage()))

            // Completar firmas cerradas: cada hora
            ->add(RecurringMessage::cron('0 * * * *',
                new EskaeraCompletedCheckMessage()))

            // Rebuild cuadrante: diaria 6:00 y 6:30
            ->add(RecurringMessage::cron('0 6 * * *',
                new RebuildKuadranteaMessage()))
            ->add(RecurringMessage::cron('30 6 * * *',
                new RebuildKuadranteaEskaerekinMessage()));
    }
}
```

```yaml
# config/packages/scheduler.yaml
framework:
    scheduler:
        schedules:
            main:
                transport: '%env(SCHEDULER_TRANSPORT_DSN)%'
                # SCHEDULER_TRANSPORT_DSN=redis://redis:6379/scheduler
```

### Ejecutar el Scheduler en producción

```yaml
# compose.yaml (workers)
  scheduler:
    build: docker/php
    command: bin/console messenger:consume scheduler_main --time-limit=3600
    restart: unless-stopped
    depends_on: [postgres, redis]
```

---

## 3. Symfony Mailer

### Configuración

```yaml
# config/packages/mailer.yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
        envelope:
            sender: '%env(MAILER_FROM)%'
        headers:
            From: '%env(MAILER_FROM_NAME)%  <%env(MAILER_FROM)%>'
```

```
# .env
MAILER_DSN=smtp://mailpit:1025        # dev
# MAILER_DSN=smtp://smtp.pasaia.net:587?encryption=tls&username=...&password=...  # prod
MAILER_FROM=egutegia@pasaia.net
MAILER_FROM_NAME="Egutegia - Pasaiako Udala"
MAILER_ADMIN_EMAIL=rgonzalez@pasaia.net    # destinatario de alertas de sistema
MAILER_MAIKA_EMAIL=maika@pasaia.net        # destinatario de cursos IKA
```

### Plantillas de email

Todas en `templates/emails/`. Heredar de `base_email.html.twig` para consistencia.

| Plantilla | Cuándo se envía |
|---|---|
| `firma_pendiente.html.twig` | Notificación de firma pendiente (batch por usuario) |
| `ikastaroa_onartua.html.twig` | Curso IKA aprobado → maika@pasaia.net |
| `eskaera_ukatua.html.twig` | Solicitud rechazada → bideratzaileak |
| `eskaerak_egutegian_alerta.html.twig` | Eskaerak no añadidas al calendario → admin |
| `eskaera_onartua.html.twig` | Solicitud aprobada → solicitante (preparada pero opcional) |

```twig
{# templates/emails/base_email.html.twig #}
<!DOCTYPE html>
<html lang="eu">
<head>
    <meta charset="UTF-8">
    <style>/* estilos básicos inline para compatibilidad de clientes de email */</style>
</head>
<body>
    <div class="header">
        <h2>Egutegia — Pasaiako Udala</h2>
    </div>
    {% block content %}{% endblock %}
    <div class="footer">
        <p>Mezu hau automatikoki bidalita da. Ez erantzun.</p>
    </div>
</body>
</html>
```

### Redirects de email configurables

El legacy tenía `igomez@pasaia.net → atorrado@pasaia.net` hardcodeado en el código. En el nuevo sistema, configurar como parámetro:

```yaml
# config/services.yaml
parameters:
    mailer.notify_redirects:
        'igomez@pasaia.net': 'atorrado@pasaia.net'
        # añadir más según necesidad
```

```php
// En el servicio de email, aplicar el redirect:
$email = $this->applyRedirect($recipient);
```

---

## 4. Gestión de ficheros (Flysystem)

### Instalación

```bash
composer require league/flysystem-bundle
```

```yaml
# config/packages/flysystem.yaml
flysystem:
    storages:
        uploads.storage:
            adapter: 'local'
            options:
                directory: '%kernel.project_dir%/storage/uploads'
            # En producción: usar S3, SFTP, etc.
            # adapter: 'asyncaws'
            # options: { client: ... bucket: 'egutegia-uploads' ... }
```

### Estructura de directorios de uploads

```
storage/uploads/
├── justifikanteak/
│   └── {year}/{username}/{filename}
├── ikastaroak/
│   └── {year}/{username}/{filename}
├── ordainketak/
│   └── {year}/{username}/{filename}
└── documents/
    └── {calendarId}/{filename}
```

### Servicio de storage

```php
// src/Infrastructure/Storage/FlysystemStorageService.php
class FlysystemStorageService implements StorageServiceInterface
{
    public function __construct(
        private readonly FilesystemOperator $uploadsStorage
    ) {}

    public function upload(UploadedFile $file, string $context, string $subpath): string
    {
        $filename = sprintf('%s_%s.%s',
            uniqid(),
            $this->sanitize($file->getClientOriginalName()),
            $file->getClientOriginalExtension()
        );
        $path = $context . '/' . $subpath . '/' . $filename;

        $stream = fopen($file->getRealPath(), 'r');
        $this->uploadsStorage->writeStream($path, $stream);
        fclose($stream);

        return $path;
    }

    public function read(string $path): string
    {
        return $this->uploadsStorage->read($path);
    }

    public function delete(string $path): void
    {
        $this->uploadsStorage->delete($path);
    }

    public function getUrl(string $path): string
    {
        // En dev: URL del servidor propio; en prod: URL del CDN/S3
        return '/storage/' . ltrim($path, '/');
    }

    private function sanitize(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
    }
}
```

### Endpoint de descarga de ficheros

```php
// src/Api/Controller/FileController.php
#[Route('/api/files/{path}', name: 'file_download', requirements: ['path' => '.+'])]
#[IsGranted('ROLE_USER')]
public function download(string $path, StorageServiceInterface $storage): Response
{
    // Verificar que el usuario tiene acceso al fichero (Voter)
    $content = $storage->read($path);
    return new Response($content, 200, [
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
    ]);
}
```

---

## 5. Generación de PDFs (Gotenberg)

### Instalación y configuración

```bash
composer require gotenberg/gotenberg-php
```

```yaml
# compose.yaml
  gotenberg:
    image: gotenberg/gotenberg:8
    ports: ["3000:3000"]
    command:
      - "gotenberg"
      - "--chromium-disable-javascript=true"
      - "--chromium-allow-list=file:///tmp/.*"
```

```
# .env
GOTENBERG_URL=http://gotenberg:3000
```

### Servicio PDF

```php
// src/Infrastructure/Pdf/GotenbergPdfService.php
class GotenbergPdfService implements PdfServiceInterface
{
    public function __construct(
        private readonly string $gotenbergUrl,
        private readonly Environment $twig,
        private readonly HttpClientInterface $httpClient
    ) {}

    public function generateFromTemplate(string $template, array $context): string
    {
        $html = $this->twig->render($template, $context);

        $response = $this->httpClient->request('POST', $this->gotenbergUrl . '/forms/chromium/convert/html', [
            'body' => ['files' => ['index.html' => $html]],
        ]);

        return $response->getContent();
    }
}
```

### Plantillas PDF

Las vistas Twig de PDF del legacy (`eskaera/show.html.twig` para PDF, `ikastaroapdf.html.twig`, `ordainketapdf.html.twig`) se portan a `templates/pdf/`:

```
templates/pdf/
├── base_pdf.html.twig          # layout base para PDFs
├── eskaera.html.twig           # solicitud general
├── ikastaroa.html.twig         # formulario de curso
└── ordainketa.html.twig        # justificante de pago
```

---

## 6. Comandos manuales de mantenimiento

### `TransferNotificationsCommand`

```bash
bin/console app:transfer-notifications <origenUserId> <destinoUserId>
```

Mueve todas las `Notification`s pendientes de un usuario a otro (sustitución de empleado). Equivale al legacy `app:jakinarazpen_aldaketa`.

### `AssignRoleCommand`

```bash
bin/console app:user:assign-role <username> <role>
bin/console app:user:remove-role <username> <role>
```

### `RebuildKuadranteaCommand` (manual)

```bash
bin/console app:kuadrantea:rebuild [--year=2025] [--month=8] [--saila=3]
```

Útil para reconstruir el cuadrante de un mes específico sin esperar al Scheduler nocturno.

---

## 7. Worker de Messenger en producción

```yaml
# compose.yaml
  messenger_worker:
    build: docker/php
    command: bin/console messenger:consume async --time-limit=3600 --memory-limit=512M
    restart: unless-stopped
    depends_on: [postgres, redis]
    deploy:
      replicas: 2   # dos workers en paralelo
```

```bash
# Monitorear cola de mensajes
bin/console messenger:stats

# Reintentar mensajes fallidos
bin/console messenger:failed:retry
bin/console messenger:failed:show
```

---

## 8. Nginx — servir ficheros estáticos

Los ficheros del directorio `storage/uploads/` **NO** deben ser accesibles directamente vía Nginx sin pasar por la autenticación del API. Configurar Nginx para bloquear el acceso directo:

```nginx
# docker/nginx/default.conf
location /storage/ {
    deny all;
    return 403;
}
```

El acceso a ficheros siempre pasa por el endpoint `/api/files/{path}` que verifica la autenticación.
