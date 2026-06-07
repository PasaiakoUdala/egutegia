# Egutegia — Documentación de migración para Claude Code

Estos documentos son el **brief completo** para que Claude Code desarrolle la nueva versión de `egutegia`: de monolito Symfony 3.4 / PHP 7 a **API REST moderna** (Symfony 7 + API Platform 4 + PostgreSQL + OIDC).

> **Rol de este directorio:** análisis, decisiones de arquitectura y spec de implementación.  
> **No contiene código nuevo** — eso lo genera Claude Code usando estos docs como contexto.

---

## Orden de lectura recomendado

| # | Fichero | Contenido |
|---|---------|-----------|
| — | **`00-prompt-maestro.md`** | ⭐ **Empieza aquí.** Prompt listo para pegar en Claude Code. |
| 1 | `01-analisis-proyecto-actual.md` | Fotografía del legacy: stack, entidades, riesgos. |
| 2 | `02-arquitectura-objetivo.md` | Stack destino, estructura `src/`, tabla de reemplazos de bundles. |
| 3 | `03-modelo-datos.md` | Catálogo de entidades PHP 8 + glosario euskera + mejoras de modelo. |
| 4 | `04-endpoints-api.md` | Diseño de recursos API Platform 4, operaciones custom, DTOs, seguridad. |
| 5 | `05-logica-negocio.md` | Servicios de dominio a portar (horas, firmas, conflictos, emails). |
| 6 | `06-autenticacion-seguridad.md` | OIDC resource server, roles, Voters, CORS. |
| 7 | `07-migracion-datos.md` | Estrategia MySQL → PostgreSQL. |
| 8 | `08-comandos-cron-emails-ficheros.md` | Commands, Scheduler, Mailer, ficheros, PDFs. |
| 9 | `09-plan-fases.md` | Roadmap F0–F6 con criterios de aceptación. |
| 10 | `10-spa-frontend.md` | Spec SPA Vue 3 (segunda fase). |

---

## Cómo usar con Claude Code

1. Abre una sesión Claude Code **en el directorio del nuevo proyecto** (repo vacío o recién creado).
2. Pega el contenido de `00-prompt-maestro.md` como primer mensaje.
3. Adjunta (o referencia con `@`) los ficheros restantes según la fase en que trabajes — Claude Code los leerá como contexto adicional.
4. Sigue el roadmap de `09-plan-fases.md`: trabaja fase a fase y valida antes de avanzar.

---

## Decisiones cerradas

| Dimensión | Decisión |
|-----------|----------|
| Framework | Symfony 7.x |
| API | API Platform 4 |
| BD | PostgreSQL (migración desde MySQL) |
| Auth | SSO OIDC/OAuth2 — resource server con bundle propio |
| Frontend | SPA Vue 3 + TypeScript + Vite + Pinia + Vue Router (2ª fase) |
