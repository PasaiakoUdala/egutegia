# 06 — Autenticación y seguridad

## Arquitectura general

La nueva aplicación actúa como **OAuth2/OIDC Resource Server**. No gestiona identidades: delega completamente en el IdP corporativo (vuestro SSO propio) para autenticar usuarios.

```
┌──────────────┐    Bearer token    ┌────────────────────┐    Validate token   ┌─────────┐
│  Client App  │ ─────────────────> │  Egutegia API      │ ──────────────────> │  SSO    │
│  (SPA/otras) │                    │  (Resource Server) │ <────────────────── │  IdP    │
└──────────────┘                    └────────────────────┘    Claims + roles    └─────────┘
```

Cada request al API incluye `Authorization: Bearer <access_token>`. El bundle SSO propio valida el token contra el IdP y devuelve los claims del usuario.

---

## Integración con el bundle SSO propio

> **⚠️ PLACEHOLDER:** El nombre real del bundle SSO es `[PLACEHOLDER_SSO_BUNDLE]`. Sustituir por el nombre real antes de implementar.

### Punto de integración

El bundle SSO debe proporcionar (o el proyecto debe implementar para él):

1. Un **`TokenAuthenticator`** de Symfony Security que extrae el Bearer token del header `Authorization`, valida con el IdP, y devuelve un `Passport`.

2. Un **`UserProvider`** que carga (o crea) el `User` de la BD a partir del `username`/`sub` del token.

3. Opcionalmente, un **`UserBadge`** + **`SelfValidatingPassport`** si el bundle ya gestiona la validación.

### Implementación mínima si el bundle solo valida el token

```php
// src/Infrastructure/Sso/OidcAuthenticator.php
class OidcAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr($request->headers->get('Authorization'), 7);

        // Delegar en el bundle SSO para validar el token y obtener claims
        $claims = $this->ssoTokenValidator->validate($token); // [PLACEHOLDER_SSO_BUNDLE]

        return new SelfValidatingPassport(
            new UserBadge($claims['username'], function (string $username) use ($claims) {
                return $this->oidcUserProvider->loadOrCreateUser($username, $claims);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // dejar pasar la request
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }
}
```

```php
// src/Infrastructure/Sso/OidcUserProvider.php
class OidcUserProvider implements UserProviderInterface
{
    public function loadOrCreateUser(string $username, array $claims): User
    {
        $user = $this->userRepository->findByUsername($username);

        if (!$user) {
            // Crear usuario en la BD con los datos del claim
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($claims['email'] ?? null);
            $user->setDisplayname($claims['name'] ?? $username);
        }

        // Sincronizar roles desde los claims del token
        $roles = $this->mapClaimsToRoles($claims);
        $user->setRoles($roles);

        $this->userRepository->save($user);
        return $user;
    }

    private function mapClaimsToRoles(array $claims): array
    {
        // Mapear grupos/claims del IdP a roles de Symfony
        // Ejemplo: claim 'groups' del OIDC → roles Symfony
        $roles = ['ROLE_USER'];

        $groups = $claims['groups'] ?? [];
        if (in_array('egutegia-admin', $groups)) $roles[] = 'ROLE_ADMIN';
        if (in_array('egutegia-bideratzaile', $groups)) $roles[] = 'ROLE_BIDERATZAILEA';
        if (in_array('egutegia-sailburua', $groups)) $roles[] = 'ROLE_SAILBURUA';
        // ... etc.

        return $roles;
    }
}
```

---

## Configuración de Security (`config/packages/security.yaml`)

```yaml
security:
    enable_authenticator_manager: true

    password_hashers:
        App\Domain\User\Entity\User: 'auto'

    providers:
        oidc_provider:
            id: App\Infrastructure\Sso\OidcUserProvider

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        api:
            pattern: ^/api
            stateless: true           # ← CRÍTICO: API stateless, sin sesiones
            provider: oidc_provider
            custom_authenticators:
                - App\Infrastructure\Sso\OidcAuthenticator

    access_control:
        - { path: ^/api/docs$, roles: PUBLIC_ACCESS }        # OpenAPI UI pública (opcional)
        - { path: ^/api/docs\., roles: PUBLIC_ACCESS }
        - { path: ^/api/contexts, roles: PUBLIC_ACCESS }
        - { path: ^/api, roles: ROLE_USER }

    role_hierarchy:
        ROLE_SUPER_ADMIN:    [ROLE_ADMIN, ROLE_BIDERATZAILEA]
        ROLE_ADMIN:          [ROLE_USER]
        ROLE_SAILBURUA:      [ROLE_USER, ROLE_ALLOWED_TO_SWITCH]
        ROLE_BIDERATZAILEA:  [ROLE_USER, ROLE_ALLOWED_TO_SWITCH]
        ROLE_ARDURADUNA:     [ROLE_USER, ROLE_ALLOWED_TO_SWITCH]
```

---

## Roles del sistema

| Rol | Descripción | Acceso clave |
|---|---|---|
| `ROLE_USER` | Cualquier usuario autenticado | Ver/crear sus propias solicitudes y calendarios |
| `ROLE_ADMIN` | Administrador | CRUD completo de todos los recursos |
| `ROLE_SUPER_ADMIN` | Superadministrador | Todo + operaciones destructivas |
| `ROLE_SAILBURUA` | Jefe de departamento | Ver kuadrantea de su departamento, gestionar su equipo |
| `ROLE_BIDERATZAILEA` | Tramitador/enrutador | Gestionar solicitudes, transferir, procesar firma |
| `ROLE_ARDURADUNA` | Responsable | Impersonación limitada |
| `ROLE_UDALTZAINA` | Policía municipal | Flujo de solicitudes específico (munipa) |
| `ROLE_IKUSI_SAILBURUEN_KUADRANTEA` | Concejal/alcalde | Ver cuadrante de todos los departamentos |
| `ROLE_ALLOWED_TO_SWITCH` | Impersonación | Actuar en nombre de otro usuario |

---

## Voters (autorización por recurso)

Implementar un Voter por agregado principal:

### `CalendarVoter`

```php
// Atributos
const VIEW   = 'calendar:view';
const EDIT   = 'calendar:edit';
const DELETE = 'calendar:delete';

// Reglas
VIEW:   user.id === calendar.user.id  OR  ROLE_ADMIN
EDIT:   ROLE_ADMIN
DELETE: ROLE_SUPER_ADMIN
```

### `EskaeraVoter`

```php
const VIEW          = 'eskaera:view';
const CREATE        = 'eskaera:create';
const CANCEL        = 'eskaera:cancel';
const SIGN          = 'eskaera:sign';
const PROCESS       = 'eskaera:process';    // bideratzailea
const ADD_CALENDAR  = 'eskaera:add_calendar';

VIEW:         user.id === eskaera.user.id  OR  ROLE_ADMIN  OR  ROLE_BIDERATZAILEA
CREATE:       ROLE_USER (para sí mismo)
CANCEL:       user.id === eskaera.user.id  OR  ROLE_ADMIN
SIGN:         user === currentSigner(eskaera.firma)  // el firmante actual
PROCESS:      ROLE_BIDERATZAILEA  OR  ROLE_ADMIN
ADD_CALENDAR: ROLE_BIDERATZAILEA  OR  ROLE_ADMIN
```

### `NotificationVoter`

```php
VIEW:     user.id === notification.user.id
COMPLETE: user.id === notification.user.id  OR  ROLE_BIDERATZAILEA
TRANSFER: ROLE_BIDERATZAILEA  OR  ROLE_ADMIN
```

### `KuadranteaVoter`

```php
VIEW_ALL:     ROLE_ADMIN  OR  ROLE_IKUSI_SAILBURUEN_KUADRANTEA
VIEW_SAILA:   ROLE_SAILBURUA  AND  user.saila.id === requestedSailaId
```

---

## CORS

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        allow_credentials: false
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_headers: ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With']
        allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
        expose_headers: ['Link']
        max_age: 3600
    paths:
        '^/api':
            allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
```

```
# .env
CORS_ALLOW_ORIGIN=^https://(localhost|egutegia\.pasaia\.net)$
```

---

## Rate limiting (recomendado)

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        api_anonymous:
            policy: 'fixed_window'
            limit: 100
            interval: '60 seconds'
        api_authenticated:
            policy: 'fixed_window'
            limit: 1000
            interval: '60 seconds'
```

---

## API keys para apps máquina-a-máquina

Si otras aplicaciones internas necesitan escribir datos en la API **sin usuario humano** (p.ej. un script de sincronización de nóminas), se puede añadir un segundo autenticador de API key:

```yaml
# config/packages/security.yaml (firewall api)
custom_authenticators:
    - App\Infrastructure\Sso\OidcAuthenticator
    - App\Infrastructure\Sso\ApiKeyAuthenticator   # ← añadir si se necesita
```

La API key se envía como `X-API-Key: <key>` y se valida contra una tabla `api_key` en la BD (o como variable de entorno). El `User` asociado tiene el rol `ROLE_API_CLIENT`.

---

## Impersonación (switch_user)

Si se mantiene la funcionalidad de impersonación del legacy:

```yaml
# config/packages/security.yaml (firewall api)
switch_user:
    role: ROLE_ALLOWED_TO_SWITCH
    parameter: _switch_user
```

El cliente envía `?_switch_user=jperez` para actuar en nombre de otro usuario. Solo disponible para `ROLE_SAILBURUA`, `ROLE_BIDERATZAILEA`, `ROLE_ARDURADUNA`.

---

## Checklist de seguridad para implementación

- [ ] HTTPS obligatorio en producción (nginx config con redirect 80→443).
- [ ] `stateless: true` en el firewall API (sin sesiones).
- [ ] Validar `Content-Type` en operaciones POST/PATCH (API Platform lo hace por defecto).
- [ ] Configurar `CORS_ALLOW_ORIGIN` con la lista blanca real, no `*`.
- [ ] Rate limiting activo en producción.
- [ ] Logs de acceso y de errores de auth en Monolog/Graylog.
- [ ] Auditoría en `Log` entity para operaciones de escritura sobre Eskaera y Firma.
- [ ] No exponer detalles de error en producción (`APP_ENV=prod` oculta stack traces).
- [ ] Headers de seguridad en nginx: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.
