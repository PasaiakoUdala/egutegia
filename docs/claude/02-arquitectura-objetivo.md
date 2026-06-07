# 02 — Arquitectura objetivo

## Stack destino (resumen ejecutivo)

| Capa | Tecnología | Versión mínima |
|------|-----------|----------------|
| Framework | Symfony | 7.2 |
| PHP | — | 8.3 |
| API | API Platform | 4.x |
| ORM | Doctrine ORM | 3.x |
| Migraciones | doctrine/migrations | 3.x |
| Base de datos | PostgreSQL | 15 |
| Auth | SSO OIDC/OAuth2 (bundle propio) | — |
| Email | symfony/mailer + Twig | Symfony 7 |
| Async | symfony/messenger + symfony/scheduler | Symfony 7 |
| Ficheros | league/flysystem-bundle | 3.x |
| PDF | KnpSnappyBundle o Gotenberg | — |
| Tests | PHPUnit | 11 |
| Cache/Sesión | Redis (symfony/cache + symfony/http-foundation) | — |
| Contenedor | Docker Compose | — |
| Infraestructura como código | Makefile | — |

---

## Estructura de directorios

```
egutegia-api/                        # nuevo repositorio
├── compose.yaml                     # servicios: php, nginx, postgres, redis, mailpit
├── compose.override.yaml            # dev overrides
├── Makefile                         # comandos de desarrollo
├── .env                             # plantilla de variables de entorno
├── config/
│   ├── packages/
│   │   ├── api_platform.yaml
│   │   ├── doctrine.yaml
│   │   ├── doctrine_migrations.yaml
│   │   ├── messenger.yaml
│   │   ├── scheduler.yaml
│   │   ├── mailer.yaml
│   │   ├── flysystem.yaml
│   │   ├── security.yaml
│   │   └── ...
│   ├── routes/
│   │   └── api_platform.yaml
│   └── services.yaml
├── migrations/                      # doctrine/migrations
├── src/
│   ├── Api/                         # capa API Platform (adaptadores)
│   │   ├── Resource/                # clases con #[ApiResource]
│   │   ├── Dto/                     # Input DTOs, Output DTOs
│   │   ├── Processor/               # State Processors (operaciones write)
│   │   ├── Provider/                # State Providers (operaciones read custom)
│   │   ├── Filter/                  # Custom API Platform filters
│   │   └── Security/                # Voters
│   ├── Domain/                      # núcleo de negocio (sin dependencias externas)
│   │   ├── Calendar/
│   │   │   ├── Entity/Calendar.php
│   │   │   ├── Entity/Event.php
│   │   │   ├── Entity/Hour.php
│   │   │   ├── Repository/CalendarRepositoryInterface.php
│   │   │   └── Service/CalendarHourService.php
│   │   ├── Eskaera/
│   │   │   ├── Entity/Eskaera.php
│   │   │   ├── Enum/EskaeraState.php
│   │   │   ├── Repository/EskaeraRepositoryInterface.php
│   │   │   └── Service/ConflictDetectorService.php
│   │   ├── Firma/
│   │   │   ├── Entity/Firma.php
│   │   │   ├── Entity/Firmadet.php
│   │   │   ├── Entity/Sinatzaileak.php
│   │   │   ├── Entity/Sinatzaileakdet.php
│   │   │   └── Service/FirmaWorkflowService.php
│   │   ├── Notification/
│   │   │   └── Entity/Notification.php
│   │   ├── User/
│   │   │   ├── Entity/User.php
│   │   │   ├── Entity/Saila.php
│   │   │   └── Entity/Taldea.php
│   │   ├── Template/
│   │   │   ├── Entity/Template.php
│   │   │   └── Entity/TemplateEvent.php
│   │   └── Shared/
│   │       ├── Entity/Document.php
│   │       ├── Entity/Log.php
│   │       └── Entity/Message.php
│   ├── Application/                 # casos de uso, orquestación entre servicios de dominio
│   │   ├── Calendar/
│   │   │   └── AddEventUseCase.php
│   │   ├── Eskaera/
│   │   │   ├── CreateEskaeraUseCase.php
│   │   │   ├── AddToCalendarUseCase.php
│   │   │   └── IkastaroaApprovedNotifier.php
│   │   └── Firma/
│   │       ├── ProcessSignatureUseCase.php
│   │       └── RejectionEmailNotifier.php
│   ├── Infrastructure/              # implementaciones (Doctrine, Mailer, Storage, SSO)
│   │   ├── Doctrine/
│   │   │   ├── Repository/          # implementaciones de los interfaces de dominio
│   │   │   └── Type/                # custom Doctrine types si los hubiera
│   │   ├── Mailer/
│   │   │   └── SymfonyMailerService.php
│   │   ├── Storage/
│   │   │   └── FlysystemStorageService.php
│   │   └── Sso/
│   │       └── OidcUserProvider.php # bridge con el bundle SSO propio
│   └── Scheduler/                   # Symfony Scheduler handlers
│       ├── NotifyPendingSignaturesHandler.php
│       ├── KuadranteaRebuildHandler.php
│       └── EskaerakEgutegianSyncHandler.php
├── templates/
│   └── emails/                      # plantillas Twig de email
├── tests/
│   ├── Api/                         # tests funcionales (ApiTestCase)
│   ├── Domain/                      # tests unitarios de servicios de dominio
│   └── Application/                 # tests de casos de uso
└── docs/                            # documentación (este directorio)
```

---

## API Platform 4 — configuración base

```yaml
# config/packages/api_platform.yaml
api_platform:
    title: 'Egutegia API'
    description: 'API de gestión de calendarios laborales del Ayuntamiento de Pasaia'
    version: '2.0.0'
    formats:
        jsonld: ['application/ld+json']
        json: ['application/json']
    docs_formats:
        jsonld: ['application/ld+json']
        json: ['application/json']
        html: ['text/html']
    defaults:
        stateless: true
        pagination_enabled: true
        pagination_items_per_page: 30
        pagination_maximum_items_per_page: 200
    collection:
        order:
            order: 'DESC'
    serializer:
        groups: ['read', 'write']
    swagger:
        api_keys:
            Bearer:
                name: Authorization
                type: header
```

---

## Seguridad — configuración base

```yaml
# config/packages/security.yaml
security:
    providers:
        oidc_user_provider:
            id: App\Infrastructure\Sso\OidcUserProvider

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        api:
            pattern: ^/api
            stateless: true
            custom_authenticators:
                - App\Infrastructure\Sso\OidcAuthenticator   # PLACEHOLDER: sustituir por clase del bundle SSO

    access_control:
        - { path: ^/api/docs, roles: PUBLIC_ACCESS }  # OpenAPI docs públicos o protegidos según decisión
        - { path: ^/api, roles: ROLE_USER }

    role_hierarchy:
        ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_BIDERATZAILEA]
        ROLE_ADMIN:       [ROLE_USER]
        ROLE_SAILBURUA:   [ROLE_USER, ROLE_ALLOWED_TO_SWITCH]
        ROLE_BIDERATZAILEA: [ROLE_USER, ROLE_ALLOWED_TO_SWITCH]
        ROLE_ARDURADUNA:  [ROLE_USER, ROLE_ALLOWED_TO_SWITCH]
```

---

## Doctrine — configuración

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        url: '%env(DATABASE_URL)%'
        # DATABASE_URL=postgresql://user:pass@postgres:5432/egutegia?serverVersion=15&charset=utf8

    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: false
        mappings:
            Domain:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/Domain'
                prefix: 'App\Domain'
                alias: Domain
```

---

## Messenger + Scheduler

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
            # MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages
        routing:
            App\Application\Eskaera\IkastaroaApprovedMessage: async
            App\Application\Firma\RejectionEmailMessage: async

# config/packages/scheduler.yaml
framework:
    scheduler:
        schedules:
            default:
                transport: '%env(SCHEDULER_TRANSPORT_DSN)%'
```

---

## Docker Compose

```yaml
# compose.yaml
services:
  php:
    build: docker/php
    volumes:
      - .:/var/www/html
    environment:
      - DATABASE_URL=postgresql://egutegia:egutegia@postgres:5432/egutegia?serverVersion=15&charset=utf8
      - MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages
      - MAILER_DSN=smtp://mailpit:1025

  nginx:
    image: nginx:alpine
    ports: ["8080:80"]
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf

  postgres:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: egutegia
      POSTGRES_USER: egutegia
      POSTGRES_PASSWORD: egutegia
    volumes:
      - postgres_data:/var/lib/postgresql/data
    ports: ["5432:5432"]

  redis:
    image: redis:7-alpine
    ports: ["6379:6379"]

  mailpit:
    image: axllent/mailpit
    ports: ["1025:1025", "8025:8025"]  # SMTP + UI

volumes:
  postgres_data:
```

---

## Tabla de reemplazo completo de bundles

| Bundle legacy | Reemplazo en Symfony 7 |
|---|---|
| `symfony/symfony` (monolito) | Componentes individuales vía Flex |
| `friendsofsymfony/user-bundle` | Entidad `User` propia + Security nativa |
| `friendsofsymfony/rest-bundle` | API Platform 4 |
| `jms/serializer-bundle` | Symfony Serializer (integrado en API Platform) |
| `nelmio/api-doc-bundle` v2 | API Platform genera OpenAPI 3 automáticamente |
| `nelmio/cors-bundle` | `nelmio/cors-bundle` actualizado (sigue activo) o `config/packages/nelmio_cors.yaml` |
| `symfony/swiftmailer-bundle` | `symfony/mailer` |
| `fr3d/ldap-bundle` + `ldaptools/*` | Bundle SSO propio `[PLACEHOLDER_SSO_BUNDLE]` |
| `knplabs/knp-snappy-bundle` | Mantener o migrar a Gotenberg |
| `vich/uploader-bundle` | `league/flysystem-bundle` + servicio propio |
| `stof/doctrine-extensions-bundle` | Mantener (Timestampable, Sluggable siguen en Symfony 7) |
| `snc/redis-bundle` | `symfony/cache` (redis adapter) + `symfony/http-foundation` (session) |
| `knplabs/knp-menu-bundle` | No necesario (SPA gestiona su propia navegación) |
| `sensio/framework-extra-bundle` | Atributos PHP 8 nativos |
| `sensio/distribution-bundle` | Flex |
| `eightpoints/guzzle-bundle` | `symfony/http-client` (para llamadas externas futuras) |
| `egeloen/form-extra-bundle` | No necesario (sin formularios Twig) |
| `fos/js-routing-bundle` | No necesario (SPA usa URLs directas) |
| `fos/ckeditor-bundle` | No necesario |
| `suncat/mobile-detect-bundle` | No necesario |
| `graylog2/gelf-php` | Mantener si sigue usando Graylog; o Monolog + handler |
| `mopa/bootstrap-bundle` | No necesario |
| `deployer/deployer` | Mantener si el equipo lo usa |
| Frontend: Gulp/Bower | Vue 3 + Vite (SPA separada) |

---

## Makefile de referencia

```makefile
.PHONY: up down sh migrate test lint

up:
    docker compose up -d

down:
    docker compose down

sh:
    docker compose exec php bash

migrate:
    docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

test:
    docker compose exec php bin/phpunit

lint:
    docker compose exec php vendor/bin/php-cs-fixer fix --dry-run

cs-fix:
    docker compose exec php vendor/bin/php-cs-fixer fix

stan:
    docker compose exec php vendor/bin/phpstan analyse
```
