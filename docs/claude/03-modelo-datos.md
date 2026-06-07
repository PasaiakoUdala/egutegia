# 03 — Modelo de datos

## Glosario euskera ↔ dominio

| Término euskera | Significado / dominio |
|---|---|
| egutegia | calendario |
| eskaera | solicitud / petición de permiso |
| firma | firma / proceso de aprobación |
| firmadet | detalle de firma (línea de un firmante) |
| sinatzaileak | firmantes (grupo de firmantes) |
| sinatzaileakdet | detalle de firmantes (miembro del grupo) |
| saila | departamento |
| taldea | equipo / grupo |
| sailburua | jefe de departamento |
| zinegotzi | concejal |
| oporrak | vacaciones |
| norberarentzako | día/hora personal (NAE) |
| konpentsatuak | horas compensadas |
| ikastaroa | curso de formación |
| azterketa | examen |
| udaltzain / udaltzaingoa | policía municipal |
| bideratzailea | enrutador / tramitador |
| arduraduna | responsable |
| lizentziamota | tipo de licencia |
| gutxienekoak | mínimos (de plantilla / incompatibilidades) |
| gutxienekoakdet | detalle de mínimos (miembro del grupo) |
| kuadrantea | cuadrante / grid mensual de presencia |
| jakinarazpena | notificación / aviso |
| bertanbehera | cancelado / anulado |
| abiatua | iniciado |
| bideratua | tramitado |
| amaitua | finalizado / completado |
| egutegian | en el calendario |
| emaitza | resultado / resolución |
| justifikatua | justificado |
| konfliktoa | conflicto (con otras solicitudes o mínimos) |
| postit | nota (firma rápida vía "post-it") |
| hirurtekoa | trienio (complemento salarial antigüedad) |
| ordainketa | pago / justificante de pago |
| oharra | nota / comentario |
| noiz | cuándo (campo de fecha de registro) |
| hasi / amaitu | inicio / fin |
| orduak | horas |
| egunak | días |
| izena | nombre (de saila/taldea) |
| aktibo | activo |
| lanpostua | puesto de trabajo |
| hizkuntza | idioma |
| nan | DNI / número de documento |
| erakundea | entidad / institución |
| non | dónde (lugar) |
| sareko | online |
| kostua | coste |
| portzentaia | porcentaje |
| labur | abreviatura / código corto |
| erakutsi | mostrar |
| related | vinculado (a un bucket de horas) |
| nondik | desde dónde (origen, p.ej. de qué bucket se descuenta) |
| urtea | año |
| hilabetea | mes |

---

## Mejoras de modelo a introducir

### 1. `EskaeraState` — enum PHP 8 en vez de 8 flags booleanos

El legacy modela el estado de `Eskaera` con flags independientes:
```
abiatua | bertanbehera | bideratua | amaitua | emaitza | justifikatua
```
Esto permite combinaciones incoherentes. Sustituir por un enum:

```php
enum EskaeraState: string
{
    case ZIRRIBORROA   = 'draft';          // creada, no enviada al workflow
    case ABIATUA       = 'pending';        // en proceso de firma
    case BERTANBEHERA  = 'cancelled';      // anulada
    case ONARTUA       = 'approved';       // todas las firmas OK (emaitza=true)
    case UKATUA        = 'rejected';       // rechazada (emaitza=false)
    case EGUTEGIAN     = 'in_calendar';    // añadida al calendario
    case JUSTIFIKATUA  = 'justified';      // justificación documental aportada
}
```

- `bideratua` (tramitado/enrutado) puede modelarse como columna boolean adicional si se necesita para el cuadrante sin cambiar el estado principal.
- `konfliktoa` se mantiene como columna boolean separada (es una bandera informativa, no un estado).

### 2. Claves estables en `Type` y `Lizentziamota`

En el legacy, `Type.labur` es la abreviatura de 3 chars que identifica semánticamente el tipo (p.ej. `'IKA'` = formación, `'OPO'` = vacaciones, `'NAE'` = norberarentzako, `'KON'` = konpentsatuak). **Usar `labur` (o `kodea`) como clave de negocio estable**, nunca el ID numérico. Estandarizar los códigos:

| Código (`labur`) | Nombre | Bucket de horas |
|---|---|---|
| `OPO` | Oporrak (Vacaciones) | `hours_free` |
| `NAE` | Norberarentzako (Personal) | `hours_self` / `hours_self_half` |
| `KON` | Konpentsatuak (Compensadas) | `hours_compensed` |
| `SIN` | Sindikal (Sindical) | `hours_sindikal` |
| `IKA` | Ikastaroa (Formación) | — (no descuenta horas) |
| `AZT` | Azterketa (Examen) | — |
| `MUN` | Munipada (Policía municipal) | — |

### 3. Relaciones corregidas

- `User` añade `#[ORM\OneToMany(targetEntity: Kuadrantea::class, mappedBy: 'user')]` correctamente.
- `User::$zinegotziSailak` → ManyToMany con `Saila` (tabla `zinegotzi_sailak`) correctamente mapeada.
- `KuadranteaEskaerekin` puede plantearse como vista materializada o tabla de caché gestionada por el Scheduler, no como entidad ORM de primer nivel.

---

## Catálogo de entidades (PHP 8 atributos)

### `User`

```php
#[ORM\Entity, ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column(unique: true)]
    private string $username;          // sAMAccountName del AD

    #[ORM\Column(nullable: true)]
    private ?string $email;

    #[ORM\Column(nullable: true)]
    private ?string $displayname;

    #[ORM\Column(nullable: true)]
    private ?string $nan;              // DNI

    #[ORM\Column(nullable: true)]
    private ?string $lanpostua;        // puesto de trabajo

    #[ORM\Column(nullable: true)]
    private ?string $hizkuntza;        // idioma (eu/es)

    #[ORM\Column(nullable: true)]
    private ?string $dn;               // LDAP DN

    #[ORM\Column(nullable: true)]
    private ?string $department;       // AD department

    #[ORM\Column(nullable: true)]
    private ?string $ldapsaila;        // LDAP group name

    #[ORM\Column]
    private bool $sailburuada = false; // es jefe de departamento

    #[ORM\Column]
    private bool $munipada = false;    // es policía municipal

    #[ORM\Column]
    private bool $aktibo = true;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?string $notes;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $members = null;    // miembros gestionados (concejalías)

    #[ORM\ManyToOne(targetEntity: Saila::class, inversedBy: 'users')]
    private ?Saila $saila;

    #[ORM\ManyToOne(targetEntity: Taldea::class, inversedBy: 'users')]
    private ?Taldea $taldea;

    #[ORM\ManyToMany(targetEntity: Taldea::class, inversedBy: 'zinegotziak')]
    #[ORM\JoinTable(name: 'zinegotzi_taldea')]
    private Collection $zinegotziTaldeak;

    #[ORM\ManyToMany(targetEntity: Saila::class, inversedBy: 'zinegotziak')]
    #[ORM\JoinTable(name: 'zinegotzi_saila')]
    private Collection $zinegotziSailak;   // ← CORREGIDO (faltaba en el legacy)

    #[ORM\OneToMany(targetEntity: Calendar::class, mappedBy: 'user')]
    private Collection $calendars;

    #[ORM\OneToMany(targetEntity: Eskaera::class, mappedBy: 'user')]
    private Collection $eskaerak;

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'user')]
    private Collection $messages;

    #[ORM\OneToMany(targetEntity: Kuadrantea::class, mappedBy: 'user')]
    private Collection $kuadranteak;   // ← CORREGIDO (faltaba en el legacy)

    // timestamps via Gedmo o custom (Timestampable)
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;
}
```

### `Saila` (Departamento)

```php
#[ORM\Entity, ORM\Table(name: 'saila')]
class Saila
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column]
    private string $izena;

    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'saila')]
    private Collection $users;

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'zinegotziSailak')]
    private Collection $zinegotziak;
}
```

### `Taldea` (Equipo)

```php
#[ORM\Entity, ORM\Table(name: 'taldea')]
class Taldea
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column]
    private string $izena;

    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'taldea')]
    private Collection $users;

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'zinegotziTaldeak')]
    private Collection $zinegotziak;
}
```

### `Calendar`

```php
#[ORM\Entity, ORM\Table(name: 'calendar')]
class Calendar
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column]
    private string $name;

    #[ORM\Column]
    private int $year;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?float $percentYear;

    // Budgets de horas
    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hoursYear;         // horas anuales

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hoursFree;         // vacaciones (oporrak)

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hoursSelf;         // norberarentzako (días completos)

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hoursSelfHalf;     // norberarentzako (medias jornadas)

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hoursCompensed;    // konpentsatuak

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hoursSindikal;     // sindikal

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hoursDay;          // jornada diaria (horas/día)

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hirurtekoa;        // trienio

    #[ORM\Column(nullable: true, length: 2000)]
    private ?string $note;

    #[ORM\Column(unique: true)]
    private string $slug;              // Gedmo Sluggable

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'calendars')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Template::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Template $template;

    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'calendar', cascade: ['remove'])]
    private Collection $events;

    #[ORM\OneToMany(targetEntity: Hour::class, mappedBy: 'calendar', cascade: ['remove'])]
    private Collection $hours;

    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'calendar')]
    private Collection $documents;

    #[ORM\OneToMany(targetEntity: Eskaera::class, mappedBy: 'calendar')]
    private Collection $eskaerak;

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    #[ORM\Column(nullable: true)] private ?string $contentChangedBy; // Blameable
}
```

### `Type` (Tipo de evento/permiso)

```php
#[ORM\Entity, ORM\Table(name: 'type')]
class Type
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column]
    private string $name;

    #[ORM\Column(length: 10, unique: true)]
    private string $labur;             // CLAVE ESTABLE: 'OPO','NAE','KON','IKA','AZT','MUN','SIN'

    #[ORM\Column(nullable: true)]
    private ?string $description;

    #[ORM\Column(unique: true)]
    private string $slug;

    #[ORM\Column(nullable: true, type: 'decimal', precision: 5, scale: 2)]
    private ?float $hours;

    #[ORM\Column(nullable: true, length: 7)]
    private ?string $color;            // hex

    #[ORM\Column]
    private int $orden = 0;

    #[ORM\Column]
    private bool $erakutsi = true;

    #[ORM\Column]
    private bool $erakutsiEskaera = true;

    #[ORM\Column]
    private bool $erakutsiOrdua = false;

    #[ORM\Column]
    private bool $erakutsiEguna = true;

    #[ORM\Column(nullable: true)]
    private ?string $related;          // 'hours_free' | 'hours_self' | 'hours_compensed' | null

    #[ORM\Column]
    private bool $lizentziamotabehar = false; // requiere tipo de licencia

    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'type')]
    private Collection $events;

    #[ORM\OneToMany(targetEntity: Eskaera::class, mappedBy: 'type')]
    private Collection $eskaerak;
}
```

### `Event`

```php
#[ORM\Entity, ORM\Table(name: 'event')]
class Event
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column(nullable: true)]
    private ?string $name;

    #[ORM\Column(nullable: true)]
    private ?string $egunorduak;       // 'egunak' | 'orduak' (días o horas)

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hours;

    #[ORM\Column(nullable: true)]
    private ?string $nondik;           // origen del descuento ('hours_self', etc.)

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hoursSelfBefore;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?float $hoursSelfHalfBefore;

    #[ORM\ManyToOne(targetEntity: Calendar::class, inversedBy: 'events')]
    private Calendar $calendar;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'events')]
    private Type $type;

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
}
```

### `Eskaera` (Solicitud de permiso)

```php
#[ORM\Entity, ORM\Table(name: 'eskaera')]
class Eskaera
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column(nullable: true)]
    private ?string $name;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $noiz;  // fecha de solicitud

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $hasi;  // fecha inicio

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $amaitu; // fecha fin

    #[ORM\Column(nullable: true, type: 'decimal', precision: 5, scale: 2)]
    private ?float $egunak;

    #[ORM\Column(nullable: true, type: 'decimal', precision: 7, scale: 2)]
    private ?float $orduak;

    #[ORM\Column(nullable: true, type: 'decimal', precision: 7, scale: 2)]
    private ?float $total;

    #[ORM\Column(nullable: true, type: 'decimal', precision: 10, scale: 2)]
    private ?float $kostua;            // coste

    // Estado (reemplaza los 8 flags booleanos del legacy)
    #[ORM\Column(type: 'string', enumType: EskaeraState::class)]
    private EskaeraState $state = EskaeraState::ZIRRIBORROA;

    // Flags informativos (no son estado)
    #[ORM\Column]
    private bool $konfliktoa = false;  // tiene conflicto detectado

    #[ORM\Column]
    private bool $bideratua = false;   // tramitado/enrutado (para cuadrante)

    #[ORM\Column(nullable: true, length: 2000)]
    private ?string $oharra;           // nota/comentario

    #[ORM\Column(nullable: true)]
    private ?string $nondik;

    // Ficheros de justificación (paths, gestionados por Flysystem)
    #[ORM\Column(nullable: true)]
    private ?string $justifikanteFilePath;

    #[ORM\Column(nullable: true)]
    private ?string $justifikanteFilename;

    // Ficheros de formación (ikastaroa)
    #[ORM\Column(nullable: true)]
    private ?string $ikastaroaFilePath;

    #[ORM\Column(nullable: true)]
    private ?string $ikastaroaFile2Path;

    #[ORM\Column(nullable: true)]
    private ?string $ikastaroaFile3Path;

    #[ORM\Column(nullable: true)]
    private ?string $ikastaroaHizkuntza; // idioma del curso

    // Fichero de pago (ordainketa)
    #[ORM\Column(nullable: true)]
    private ?string $ordainketaFilePath;

    // Campos de formación / ikastaroa
    #[ORM\Column]
    private bool $ordaindubeharda = false;     // debe pagarse

    #[ORM\Column(nullable: true, type: 'decimal', precision: 10, scale: 2)]
    private ?float $ordainduta;        // pagado por empleado

    #[ORM\Column(nullable: true, type: 'decimal', precision: 10, scale: 2)]
    private ?float $udalakordainduta;  // pagado por el ayuntamiento

    #[ORM\Column]
    private bool $sareko = false;      // online

    #[ORM\Column]
    private bool $ikastaroaAmaituta = false;

    #[ORM\Column(nullable: true)]
    private ?string $erakundea;        // institución

    #[ORM\Column(nullable: true)]
    private ?string $non;              // lugar

    #[ORM\Column(nullable: true)]
    private ?string $aurreikusitakoOrdua;

    #[ORM\Column(nullable: true)]
    private ?string $aurreikusitakoIraupena;

    // Relaciones
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'eskaerak')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'eskaerak')]
    private Type $type;

    #[ORM\ManyToOne(targetEntity: Calendar::class, inversedBy: 'eskaerak')]
    private Calendar $calendar;

    #[ORM\ManyToOne(targetEntity: Sinatzaileak::class)]
    private ?Sinatzaileak $sinatzaileak;

    #[ORM\ManyToOne(targetEntity: Lizentziamota::class)]
    private ?Lizentziamota $lizentziamota;

    #[ORM\OneToOne(targetEntity: Firma::class, mappedBy: 'eskaera', cascade: ['remove'])]
    private ?Firma $firma;

    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'eskaera')]
    private Collection $documents;

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'eskaera')]
    private Collection $notifications;

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    #[ORM\Column(nullable: true)] private ?string $contentChangedBy;
}
```

### `Firma` (Proceso de firma)

```php
#[ORM\Entity, ORM\Table(name: 'firma')]
class Firma
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column(nullable: true)]
    private ?string $name;

    #[ORM\Column]
    private bool $completed = false;

    #[ORM\Column]
    private int $orden = 0;           // paso actual en la cadena

    #[ORM\OneToOne(targetEntity: Eskaera::class, inversedBy: 'firma')]
    #[ORM\JoinColumn(nullable: false)]
    private Eskaera $eskaera;

    #[ORM\ManyToOne(targetEntity: Sinatzaileak::class)]
    private ?Sinatzaileak $sinatzaileak;

    #[ORM\OneToMany(targetEntity: Firmadet::class, mappedBy: 'firma', cascade: ['remove'])]
    private Collection $firmadets;

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'firma')]
    private Collection $notifications;

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
}
```

### `Firmadet` (Línea de firma individual)

```php
#[ORM\Entity, ORM\Table(name: 'firmadet')]
class Firmadet
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $noiz;  // cuando firmó

    #[ORM\Column]
    private bool $firmatua = false;    // firmado

    #[ORM\Column(nullable: true, length: 500)]
    private ?string $postit;           // nota del firmante

    #[ORM\Column]
    private bool $autofirma = false;   // firmado automáticamente

    #[ORM\Column]
    private int $orden = 0;

    #[ORM\ManyToOne(targetEntity: Firma::class, inversedBy: 'firmadets')]
    private Firma $firma;

    #[ORM\ManyToOne(targetEntity: Sinatzaileakdet::class)]
    private ?Sinatzaileakdet $sinatzaileakdet;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $firmatzaile;        // quién firmó

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
}
```

### `Sinatzaileak` (Grupo de firmantes)

```php
#[ORM\Entity, ORM\Table(name: 'sinatzaileak')]
class Sinatzaileak
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column]
    private string $name;

    #[ORM\Column]
    private int $orden = 0;

    #[ORM\OneToMany(targetEntity: Sinatzaileakdet::class, mappedBy: 'sinatzaileak', cascade: ['remove'])]
    #[ORM\OrderBy(['orden' => 'ASC'])]
    private Collection $sinatzaileakdets;

    #[ORM\OneToMany(targetEntity: Firma::class, mappedBy: 'sinatzaileak')]
    private Collection $firmak;

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
}
```

### `Sinatzaileakdet` (Miembro del grupo de firmantes)

```php
#[ORM\Entity, ORM\Table(name: 'sinatzaileakdet')]
class Sinatzaileakdet
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column]
    private int $orden = 0;

    #[ORM\ManyToOne(targetEntity: Sinatzaileak::class, inversedBy: 'sinatzaileakdets')]
    private Sinatzaileak $sinatzaileak;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\OneToMany(targetEntity: Firmadet::class, mappedBy: 'sinatzaileakdet')]
    private Collection $firmadets;

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
}
```

### `Notification`

```php
#[ORM\Entity, ORM\Table(name: 'notification')]
class Notification
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column(nullable: true)]
    private ?string $name;

    #[ORM\Column(nullable: true, length: 2000)]
    private ?string $description;

    #[ORM\Column]
    private bool $notified = false;   // email de aviso enviado

    #[ORM\Column]
    private bool $readed = false;

    #[ORM\Column]
    private bool $completed = false;

    #[ORM\Column]
    private bool $sinatzeprozesua = false; // en proceso de firma

    #[ORM\Column(nullable: true)]
    private ?string $result;          // 'onartua' | 'ukatua' | null

    #[ORM\Column]
    private int $orden = 0;

    #[ORM\ManyToOne(targetEntity: Firma::class, inversedBy: 'notifications')]
    private ?Firma $firma;

    #[ORM\ManyToOne(targetEntity: Eskaera::class, inversedBy: 'notifications')]
    private ?Eskaera $eskaera;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notifications')]
    private User $user;

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
}
```

### `Lizentziamota` (Tipo de licencia)

```php
#[ORM\Entity, ORM\Table(name: 'lizentziamota')]
class Lizentziamota
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column]
    private string $name;

    #[ORM\Column(length: 10, unique: true, nullable: true)]
    private ?string $kodea;            // código estable (añadido en el nuevo modelo)

    #[ORM\Column]
    private bool $sinatubehar = false; // requiere firma

    #[ORM\Column]
    private bool $kostuabehar = false; // requiere coste

    #[ORM\Column]
    private bool $gaitu = true;        // habilitado

    #[ORM\OneToMany(targetEntity: Eskaera::class, mappedBy: 'lizentziamota')]
    private Collection $eskaerak;

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
}
```

### `Template` y `TemplateEvent`

```php
#[ORM\Entity, ORM\Table(name: 'template')]
class Template
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column]
    private string $name;

    #[ORM\Column(unique: true)]
    private string $slug;

    // Mismos campos de presupuesto de horas que Calendar
    private ?float $hoursYear, $hoursFree, $hoursSelf, $hoursCompensed, $hoursDay;

    #[ORM\OneToMany(targetEntity: TemplateEvent::class, mappedBy: 'template', cascade: ['remove'])]
    private Collection $templateEvents;

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
}

#[ORM\Entity, ORM\Table(name: 'template_event')]
class TemplateEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\Column(nullable: true)]
    private ?string $name;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate;

    #[ORM\ManyToOne(targetEntity: Template::class, inversedBy: 'templateEvents')]
    private Template $template;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    private Type $type;

    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
}
```

### Entidades de soporte (`Gutxienekoak`, `Kuadrantea`, `Document`, `Hour`, `Message`, `Log`, `Ikastaroa`)

```
Gutxienekoak        name, portzentaia (%), OneToMany→Gutxienekoakdet
Gutxienekoakdet     orden, ManyToOne→Gutxienekoak, ManyToOne→User

Kuadrantea          urtea, hilabetea, day01..day31 (string), ManyToOne→User
                    (CORREGIDO: User.kuadranteak OneToMany inversa añadida)

KuadranteaEskaerekin  urtea, hilabetea, day01..day31, jardunaldia, oporrak,
                      nae, konpentsatuak, ManyToOne→User
                      (candidata a tabla de caché no ORM pura)

Document            filenamepath, filename, imageSize, orden, egutegian (bool),
                    ManyToOne→Calendar (nullable), ManyToOne→Eskaera (nullable)

Hour                date, hours, minutes, factor, total, ManyToOne→Calendar

Message             name, description, readed, readedAt, ManyToOne→User

Log                 name, description, query (2000), contentChangedBy,
                    ManyToOne→User, ManyToOne→Calendar (nullable), ManyToOne→Event (nullable)

Ikastaroa           name, hasi, amaitu, deskribapena, ordaindubeharda,
                    ordainduta, asistentzia (standalone, sin relaciones FK)

TempEskaerakEgutegian  eskaera (integer unique) — tabla de scratch para sync
EventHistory        campos de Event + ManyToOne→Calendar, Type (no inversedBy)
```

---

## Diagrama de relaciones (texto)

```
User ──ManyToOne──> Saila (inversedBy: users)
User ──ManyToOne──> Taldea (inversedBy: users)
User ──ManyToMany──> Taldea (zinegotziTaldeak, inversedBy: zinegotziak)
User ──ManyToMany──> Saila (zinegotziSailak ← CORREGIDO, inversedBy: zinegotziak)
User ──OneToMany──> Calendar
User ──OneToMany──> Eskaera
User ──OneToMany──> Notification
User ──OneToMany──> Message
User ──OneToMany──> Kuadrantea ← CORREGIDO (faltaba inversa)

Calendar ──ManyToOne──> User
Calendar ──ManyToOne──> Template (SET NULL)
Calendar ──OneToMany──> Event
Calendar ──OneToMany──> Hour
Calendar ──OneToMany──> Document (nullable Eskaera side)
Calendar ──OneToMany──> Eskaera

Event ──ManyToOne──> Calendar
Event ──ManyToOne──> Type

Eskaera ──ManyToOne──> User
Eskaera ──ManyToOne──> Calendar
Eskaera ──ManyToOne──> Type
Eskaera ──ManyToOne──> Sinatzaileak (nullable)
Eskaera ──ManyToOne──> Lizentziamota (nullable)
Eskaera ──OneToOne──> Firma (mappedBy eskaera)
Eskaera ──OneToMany──> Document
Eskaera ──OneToMany──> Notification

Firma ──OneToOne──> Eskaera (inversedBy firma)
Firma ──ManyToOne──> Sinatzaileak (nullable)
Firma ──OneToMany──> Firmadet
Firma ──OneToMany──> Notification

Firmadet ──ManyToOne──> Firma
Firmadet ──ManyToOne──> Sinatzaileakdet (nullable)
Firmadet ──ManyToOne──> User (firmatzaile)

Sinatzaileak ──OneToMany──> Sinatzaileakdet
Sinatzaileakdet ──ManyToOne──> Sinatzaileak
Sinatzaileakdet ──ManyToOne──> User
Sinatzaileakdet ──OneToMany──> Firmadet

Notification ──ManyToOne──> Firma (nullable)
Notification ──ManyToOne──> Eskaera (nullable)
Notification ──ManyToOne──> User

Gutxienekoak ──OneToMany──> Gutxienekoakdet
Gutxienekoakdet ──ManyToOne──> Gutxienekoak
Gutxienekoakdet ──ManyToOne──> User

Template ──OneToMany──> TemplateEvent
TemplateEvent ──ManyToOne──> Template
TemplateEvent ──ManyToOne──> Type

Document ──ManyToOne──> Calendar (nullable)
Document ──ManyToOne──> Eskaera (nullable)

Kuadrantea ──ManyToOne──> User
KuadranteaEskaerekin ──ManyToOne──> User

Log ──ManyToOne──> User / Calendar (nullable) / Event (nullable)
```

---

## Fixtures de datos iniciales (a portar)

```php
// Type: 3 tipos base (más en producción)
['name' => 'Oporrak',        'labur' => 'OPO', 'color' => '#e01b1b', 'related' => 'hours_free']
['name' => 'Norberarentzako','labur' => 'NAE', 'color' => '#d451cb', 'related' => 'hours_self']
['name' => 'Konpentsatuak',  'labur' => 'KON', 'color' => '#32a121', 'related' => 'hours_compensed']

// Template: "Orokorra" con jaiegunak (festivos vascos) como TemplateEvents
// (ver LoadTemplateData / LoadTemplateEventsData en el legacy)
```
