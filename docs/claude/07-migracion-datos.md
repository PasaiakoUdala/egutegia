# 07 — Migración de datos (MySQL → PostgreSQL)

## Contexto

El sistema legado usa **MySQL** (config hardcoded a `pdo_mysql` en `app/config/config.yml`). El nuevo sistema usa **PostgreSQL 15**. La migración de datos es la fase F5 del roadmap.

**El esquema en PostgreSQL se crea limpio a través de Doctrine Migrations** — no se convierte el esquema MySQL directamente. Solo se migran los datos.

---

## Estrategia general

```
1. [F0-F1] Crear el esquema Postgres limpio via doctrine:migrations:migrate
2. [F5]    Exportar datos de MySQL (dump o conexión directa)
3. [F5]    Transformar datos: tipos, encoding, normalización
4. [F5]    Importar a Postgres vía script ETL
5. [F5]    Verificar integridad referencial y business rules
6. [F5]    Smoke test funcional de la nueva API con datos reales
```

---

## Diferencias de tipo a tener en cuenta

| MySQL | PostgreSQL | Notas |
|---|---|---|
| `json_array` (Doctrine custom) | `jsonb` | Los campos `roles` y `members` de User. Verificar que el JSON es válido. |
| `TINYINT(1)` | `boolean` | En MySQL, los booleanos son TINYINT. PostgreSQL espera `true/false`. |
| `DECIMAL(10,2)` | `NUMERIC(10,2)` | Compatible, pero verificar que no hay valores NULL inesperados. |
| `DATETIME` | `TIMESTAMP` | Zona horaria: MySQL no la almacena; Postgres puede ser `TIMESTAMP WITH TIME ZONE`. Usar UTC. |
| `TEXT` con charset `utf8mb4` | `TEXT` | PostgreSQL usa UTF-8 por defecto; sin problema. |
| `VARCHAR(255)` | `VARCHAR(255)` | Compatible. |
| `AUTO_INCREMENT` | `SERIAL` / `GENERATED ALWAYS AS IDENTITY` | Gestionado por Doctrine. Resetear secuencias después de importar. |
| `ENUM` implícito (campos string) | `TEXT` con CHECK constraint o enum Postgres | El nuevo modelo usa PHP enums mapeados a `VARCHAR`. |

---

## Normalización requerida antes de importar

### 1. Eliminar IDs hardcodeados en datos

El campo `Type.id` = 5 (y similar) aparece en algunas columnas como referencia informal. Verificar que en los datos reales el `Type.labur` es correcto para cada registro, y que los registros de `Type` tienen los `labur` correctos antes de importar.

**Acción:**
```sql
-- MySQL: verificar que Type tiene labur correcto
SELECT id, name, labur FROM type ORDER BY id;
-- Si labur está vacío o NULL, rellenar según el nombre antes de migrar
UPDATE type SET labur = 'OPO' WHERE name = 'Oporrak' AND (labur IS NULL OR labur = '');
UPDATE type SET labur = 'NAE' WHERE name LIKE '%Norberarentzako%' AND (labur IS NULL OR labur = '');
UPDATE type SET labur = 'KON' WHERE name LIKE '%Konpentsatu%' AND (labur IS NULL OR labur = '');
UPDATE type SET labur = 'IKA' WHERE name LIKE '%Ikastaro%' AND (labur IS NULL OR labur = '');
UPDATE type SET labur = 'AZT' WHERE name LIKE '%Azterketa%' AND (labur IS NULL OR labur = '');
UPDATE type SET labur = 'SIN' WHERE name LIKE '%Sindikal%' AND (labur IS NULL OR labur = '');
UPDATE type SET labur = 'MUN' WHERE name LIKE '%Munip%' AND (labur IS NULL OR labur = '');
```

### 2. Convertir flags booleanos de Eskaera → `state` enum

La columna `state` del nuevo modelo reemplaza los 8 flags booleanos. Regla de conversión:

```sql
-- Lógica de mapeo (MySQL → valor del enum PostgreSQL)
-- Prioridad: bertanbehera > ukatua > egutegian > justifikatua > onartua > abiatua > draft

SELECT id,
  CASE
    WHEN bertanbehera = 1 THEN 'cancelled'
    WHEN emaitza = 0 AND amaitua = 1 THEN 'rejected'
    WHEN egutegian = 1 THEN 'in_calendar'
    WHEN justifikatua = 1 THEN 'justified'
    WHEN emaitza = 1 AND amaitua = 1 THEN 'approved'
    WHEN abiatua = 1 THEN 'pending'
    ELSE 'draft'
  END AS state
FROM eskaera;
```

Verificar con el cliente que la regla de prioridad es correcta antes de ejecutar en producción.

### 3. Corregir el mapping kuadranteak/zinegotziSailak

Antes de importar `User`, comprobar que los registros de la tabla de join `zinegotzi_saila` (si existe en MySQL) tienen integridad referencial correcta.

### 4. Hack `username='acuevas'` departamento 3

Documentar si este comportamiento debe preservarse en los datos o si ya no es necesario. Si es necesario, modelarlo explícitamente en la entidad (p.ej. campo `sailaOverride` en User).

---

## Script ETL de referencia

Usar `pgloader` para la mayor parte de la migración o un script PHP personalizado para las transformaciones complejas.

### Opción A — `pgloader` (recomendado para la base)

```bash
# pgloader.load
LOAD DATABASE
     FROM      mysql://dbuser:dbpass@mysql-host/egutegia_mysql
     INTO      pgsql://egutegia:egutegia@localhost/egutegia
WITH include drop, create tables, create indexes, reset sequences
     , data only                          -- solo datos, esquema ya está en Postgres
     , workers = 4
CAST
  type tinyint to boolean using tinyint-to-boolean,
  type datetime to timestamptz
SET work_mem to '128MB', maintenance_work_mem to '512MB';
```

> **Nota:** pgloader migra el esquema y los datos, pero el esquema de Postgres ya está creado por Doctrine Migrations. Usar `data only` para no sobrescribir el esquema.

### Opción B — Script PHP/Symfony para transformaciones complejas

Para las tablas con transformaciones no triviales (Eskaera `state`, User `roles` jsonb, etc.):

```php
// src/Infrastructure/Migration/EskaeraMigrator.php
class EskaeraMigrator
{
    public function migrate(Connection $mysql, Connection $postgres): void
    {
        $rows = $mysql->fetchAllAssociative('SELECT * FROM eskaera');

        foreach ($rows as $row) {
            $state = $this->mapState($row);
            $postgres->insert('eskaera', [
                'id'         => $row['id'],
                'state'      => $state,
                'user_id'    => $row['user_id'],
                'calendar_id'=> $row['calendar_id'],
                // ... demás campos ...
                'created_at' => $row['created'],
                'updated_at' => $row['updated'],
            ]);
        }

        // Resetear la secuencia de PostgreSQL
        $maxId = $postgres->fetchOne('SELECT MAX(id) FROM eskaera');
        $postgres->executeStatement("SELECT setval('eskaera_id_seq', $maxId)");
    }

    private function mapState(array $row): string { /* ver regla §2 */ }
}
```

---

## Verificación de integridad post-migración

```sql
-- 1. Contar registros por tabla (comparar MySQL vs Postgres)
SELECT 'user' as tabla, COUNT(*) FROM users
UNION ALL SELECT 'calendar', COUNT(*) FROM calendar
UNION ALL SELECT 'event', COUNT(*) FROM event
UNION ALL SELECT 'eskaera', COUNT(*) FROM eskaera
UNION ALL SELECT 'firma', COUNT(*) FROM firma
UNION ALL SELECT 'firmadet', COUNT(*) FROM firmadet
UNION ALL SELECT 'notification', COUNT(*) FROM notification;

-- 2. Integridad referencial: Eskaerak sin Calendar válido
SELECT e.id FROM eskaera e
LEFT JOIN calendar c ON c.id = e.calendar_id
WHERE c.id IS NULL;

-- 3. Eskaerak con state inconsistente
SELECT id, state FROM eskaera WHERE state NOT IN
  ('draft','pending','approved','rejected','cancelled','in_calendar','justified');

-- 4. Firmas sin Eskaera
SELECT f.id FROM firma f
LEFT JOIN eskaera e ON e.id = f.eskaera_id
WHERE e.id IS NULL;

-- 5. Tipos sin labur (campo clave estable)
SELECT id, name, labur FROM type WHERE labur IS NULL OR labur = '';

-- 6. Verificar secuencias PostgreSQL
SELECT sequence_name, last_value FROM information_schema.sequences
JOIN pg_sequences USING (sequence_name)
WHERE sequence_schema = 'public';
```

---

## Orden de importación (por dependencias FK)

```
1.  saila
2.  taldea
3.  users (depende de saila, taldea)
4.  zinegotzi_taldea (join table, depende de users + taldea)
5.  zinegotzi_saila (join table, depende de users + saila)
6.  type
7.  lizentziamota
8.  template
9.  template_event (depende de template, type)
10. calendar (depende de users, template)
11. event (depende de calendar, type)
12. event_history (depende de calendar, type)
13. hour (depende de calendar)
14. sinatzaileak
15. sinatzaileakdet (depende de sinatzaileak, users)
16. gutxienekoak
17. gutxienekoakdet (depende de gutxienekoak, users)
18. eskaera (depende de users, type, calendar, sinatzaileak, lizentziamota)
19. firma (depende de eskaera, sinatzaileak)
20. firmadet (depende de firma, sinatzaileakdet, users)
21. document (depende de calendar, eskaera)
22. notification (depende de firma, eskaera, users)
23. message (depende de users)
24. log (depende de users, calendar, event)
25. kuadrantea (depende de users)
26. kuadrantea_eskaerekin (depende de users)
27. ikastaroa (standalone)
28. temp_eskaerak_egutegian (tabla de scratch — puede ignorarse en migración)
```

---

## Migración de ficheros adjuntos

Los ficheros subidos (justifikante, ikastaroa, ordainketa) están en `web/uploads/` y `web/uploads/_justifikanteak/` en el servidor legacy.

1. Copiar el directorio `web/uploads/` al almacenamiento Flysystem del nuevo sistema.
2. Actualizar las rutas en la BD si la estructura de directorios cambia.
3. Verificar que los paths almacenados en `eskaera.justifikante_file_path`, etc., apuntan a los ficheros correctos.

```bash
# Sincronizar ficheros (en el servidor)
rsync -avz legacy-server:/var/www/egutegia/web/uploads/ \
  /var/www/egutegia-api/storage/uploads/
```

---

## Checklist de migración

- [ ] Backup completo de MySQL antes de empezar.
- [ ] Entorno de staging con datos reales para probar la migración.
- [ ] Script ETL ejecutado en staging y verificado.
- [ ] Recuentos de filas comparados MySQL vs Postgres: 0 diferencias.
- [ ] Integridad referencial: 0 orphans.
- [ ] Tipos `labur` rellenos en todos los registros de `type` y `lizentziamota`.
- [ ] Secuencias PostgreSQL reseteadas al MAX(id) de cada tabla.
- [ ] Ficheros adjuntos copiados y rutas verificadas.
- [ ] Smoke test de la API con datos reales (crear eskaera, firmar, ver kuadrantea).
- [ ] Ventana de mantenimiento acordada para la migración de producción.
