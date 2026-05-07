# Diagramas UML — Librum Tenebris

Diagramas UML del sistema, en notación **Mermaid** (renderiza directamente
en GitHub, GitLab, VS Code y la mayoría de visores de Markdown).

Se cubren los tres tipos más relevantes para un TFG de DAW:

1. **Casos de uso** — qué puede hacer cada rol.
2. **Secuencia** — flujos críticos paso a paso (login con JWT, préstamo y
   recuperación de contraseña).
3. **Clases** — estructura de clases del backend `ApiLoging`.

---

## 1. Diagramas de casos de uso

El sistema reconoce **4 actores** con permisos crecientes:

| Actor | Hereda de | Capacidades extra |
|---|---|---|
| **Visitante** | — | Navegar catálogo público, registrarse, login. |
| **Usuario** | Visitante | Tomar libros prestados (máx. 2), favoritos, perfil. |
| **Pro** | Usuario | (Reservado para funcionalidad futura: recomendaciones avanzadas, lectura sin límite). |
| **Admin** | Pro | Panel de carga, CRUD de libros, gestión de préstamos y usuarios. |

### 1.1 Casos de uso por rol

```mermaid
flowchart TB
    %% Actores
    Visitante([👤 Visitante])
    Usuario([👤 Usuario])
    Pro([👤 Pro])
    Admin([👤 Admin])

    %% Casos de uso visitante
    UC1[Ver catálogo / Buscar]
    UC2[Ver ficha de libro]
    UC3[Registrarse]
    UC4[Iniciar sesión]
    UC5[Verificar email]
    UC6[Recuperar contraseña]

    %% Casos de uso usuario
    UC7[Tomar libro prestado]
    UC8[Marcar como favorito]
    UC9[Devolver libro]
    UC10[Valorar libro 1-5]
    UC11[Editar perfil]
    UC12[Cambiar email/password]
    UC13[Eliminar mi cuenta]

    %% Casos de uso admin
    UC14[Crear/editar libro]
    UC15[Subir portada]
    UC16[Crear préstamo manual]
    UC17[Cambiar estado préstamo]
    UC18[Listar usuarios]
    UC19[Cambiar rol usuario]
    UC20[Banear usuario]
    UC21[Forzar logout global]

    %% Visitante
    Visitante --> UC1
    Visitante --> UC2
    Visitante --> UC3
    Visitante --> UC4
    Visitante --> UC5
    Visitante --> UC6

    %% Usuario hereda visitante + propios
    Usuario -.->|hereda| Visitante
    Usuario --> UC7
    Usuario --> UC8
    Usuario --> UC9
    Usuario --> UC10
    Usuario --> UC11
    Usuario --> UC12
    Usuario --> UC13

    %% Pro hereda usuario
    Pro -.->|hereda| Usuario

    %% Admin hereda pro + propios
    Admin -.->|hereda| Pro
    Admin --> UC14
    Admin --> UC15
    Admin --> UC16
    Admin --> UC17
    Admin --> UC18
    Admin --> UC19
    Admin --> UC20
    Admin --> UC21
```

### 1.2 Caso de uso detallado: tomar un libro prestado

| Atributo | Valor |
|---|---|
| **Actor principal** | Usuario autenticado |
| **Precondición** | Usuario logueado, email verificado, no baneado, < 2 préstamos activos |
| **Postcondición (éxito)** | Préstamo en estado `pendiente`, stock −1 |
| **Postcondición (fallo)** | No se modifica nada (transacción rollback) |

**Flujo principal:**

1. Usuario abre la ficha de un libro (`/libro/:id`).
2. Click en **"Tomar prestado"**.
3. Frontend → `POST /api/?action=prestar` con `{usuario_id, libro_id}`.
4. Backend valida: ≤ 1 préstamo activo, stock > 0.
5. Backend transaccional: descuenta stock, crea fila `prestamos` (`pendiente`).
6. Backend sincroniza con Notion (asíncrono, no bloquea respuesta).
7. Frontend muestra confirmación + actualiza vista de "Mis préstamos".

**Flujos alternativos:**

- **A1** — Ya tiene 2 préstamos activos → 403 con mensaje.
- **A2** — Stock = 0 → 400 "Libro agotado".
- **A3** — Concurrencia (otro usuario lo cogió primero) → rollback + 400.
- **A4** — Notion no responde → préstamo se crea igualmente, sync se reintenta luego.

---

## 2. Diagramas de secuencia

### 2.1 Login con JWT y detección de localización geográfica

```mermaid
sequenceDiagram
    autonumber
    actor U as Usuario
    participant V as Vue (LoginModal)
    participant A as ApiLoging<br/>:8000
    participant D as MySQL<br/>bibliouser
    participant G as GeoIP2
    participant M as SMTP

    U->>V: introduce email + password
    V->>A: POST /auth/login
    A->>D: SELECT * FROM users WHERE email=?
    D-->>A: user row
    A->>A: password_verify(password, hash)
    alt password incorrecta
        A-->>V: 401 + rate_limit++
        V-->>U: "Credenciales inválidas"
    else password correcta
        A->>D: SELECT FROM login_locations WHERE user_id=? ORDER BY DESC
        A->>G: lookup(IP) → país
        alt país nuevo / sospechoso
            A->>D: INSERT login_locations (status='pending')
            A->>M: enviar email "¿Eres tú?"
            A-->>V: 202 + "verifica tu correo"
            U->>M: click enlace verificación
            M->>A: GET /auth/confirm-login-location?token=...
            A->>D: UPDATE login_locations SET status='approved'
            A-->>U: redirect a frontend
        else país conocido / aprobado
            A->>A: generar JWT (jti único)
            A->>D: UPDATE users SET current_session_id=jti
            A-->>V: 200 + {token, user}
            V->>V: localStorage.setItem('token', ...)
            V-->>U: redirige a /perfil
        end
    end
```

### 2.2 Flujo completo de préstamo (con valoración posterior)

```mermaid
sequenceDiagram
    autonumber
    actor U as Usuario
    actor Adm as Admin
    participant V as Vue (BookDetailsPage)
    participant L as libros_api<br/>:8080
    participant DL as MySQL<br/>librum-tenebris
    participant N as Notion API

    Note over U,N: FASE 1 — Solicitud de préstamo

    U->>V: click "Tomar prestado"
    V->>L: POST ?action=prestar<br/>{usuario_id, libro_id}
    L->>DL: BEGIN TRANSACTION
    L->>DL: COUNT prestamos activos del usuario
    alt >= 2 activos
        L->>DL: ROLLBACK
        L-->>V: 403 "límite alcanzado"
    else stock >= 1
        L->>DL: UPDATE libros SET stock = stock - 1
        L->>DL: INSERT prestamos (estado='pendiente')
        L->>DL: COMMIT
        L->>N: syncLoanCreated() async
        L-->>V: 200 success
        V-->>U: "Reserva confirmada — recoge en biblioteca"
    end

    Note over U,N: FASE 2 — Activación por admin

    Adm->>L: POST ?action=actualizar_prestamo<br/>{prestamo_id, estado:'activo'}
    L->>DL: UPDATE prestamos SET estado='activo',<br/>fecha_prestamo=NOW(),<br/>fecha_devolucion=NOW()+14 días
    L->>N: syncLoanUpdated()
    L-->>Adm: 200 success

    Note over U,N: FASE 3 — Devolución y valoración

    Adm->>L: POST ?action=actualizar_prestamo<br/>{prestamo_id, estado:'devuelto'}
    L->>DL: UPDATE prestamos SET estado='devuelto',<br/>fecha_entregado=NOW()
    L->>DL: UPDATE libros SET stock = stock + 1
    L-->>Adm: 200 success

    U->>V: valora 4★ desde "Mis préstamos"
    V->>L: POST ?action=valorar_prestamo<br/>{prestamo_id, rating:4}
    L->>DL: UPDATE prestamos SET rating=4
    L->>DL: UPDATE libros SET rating = AVG(prestamos.rating)
    L->>N: syncBookUpdated() con nuevo rating
    L-->>V: 200 success
    V-->>U: estrella aplicada
```

### 2.3 Recuperación de contraseña

```mermaid
sequenceDiagram
    autonumber
    actor U as Usuario
    participant V as Vue
    participant A as ApiLoging
    participant D as MySQL
    participant M as SMTP

    U->>V: "He olvidado mi contraseña"
    V->>A: POST /auth/request-password-reset {email}
    A->>D: SELECT id FROM users WHERE email=?
    Note over A: Respuesta uniforme aunque<br/>el email no exista (anti-enumeración)
    alt user existe
        A->>A: generar token aleatorio (32 bytes)
        A->>D: INSERT password_reset_tokens<br/>(user_id, token_hash, expires_at=+1h)
        A->>M: enviar email con enlace<br/>?token=<plaintext>
    end
    A-->>V: 200 "si existe, recibirás un correo"
    V-->>U: mensaje genérico

    U->>M: click enlace
    M-->>U: redirige a /restablecer-contrasena?token=...
    U->>V: introduce nueva password
    V->>A: POST /auth/reset-password {token, new_password}
    A->>D: SELECT FROM password_reset_tokens<br/>WHERE token_hash=? AND used_at IS NULL<br/>AND expires_at > NOW()
    alt token válido
        A->>D: UPDATE users SET password=BCRYPT(new),<br/>password_changed_at=NOW(),<br/>sessions_invalidated_at=NOW()
        A->>D: UPDATE password_reset_tokens SET used_at=NOW()
        A-->>V: 200 success
        V-->>U: "Contraseña actualizada — vuelve a iniciar sesión"
    else token inválido / caducado
        A-->>V: 400 "enlace inválido o caducado"
    end
```

---

## 3. Diagrama de clases — `ApiLoging`

Estructura simplificada del backend de autenticación, agrupada por capas
(MVC + servicios + utilidades).

```mermaid
classDiagram
    class AuthController {
        +static register()
        +static login()
        +static logout()
        +static me()
        +static verifyEmail()
        +static requestPasswordReset()
        +static resetPassword()
        +static updateUsername()
        +static updateName()
        +static updatePhone()
        +static changePassword()
        +static requestEmailChange()
        +static confirmEmailChange()
        +static deleteMe()
        +static adminUsers()
        +static adminUpdateRole()
        +static adminRegister()
        +static adminDeleteUser()
        +static adminForceLogout()
        +static adminSetBan()
        +static confirmLoginLocation()
    }

    class AuthMiddleware {
        +static handle() User
        -static extractToken() string
        -static checkRevoked(jti) bool
    }

    class User {
        +int id
        +string username
        +string email
        +string passwordHash
        +string role
        +bool isEmailVerified
        +datetime bannedAt
        +static findById(id)
        +static findByEmail(email)
        +static create(data)
        +update(fields)
    }

    class JwtService {
        +static encode(payload) string
        +static decode(token) array
        +static revoke(token)
        +static isRevoked(jti) bool
    }

    class MailService {
        +static sendVerification(email, token)
        +static sendPasswordReset(email, token)
        +static sendLoginAlert(email, ip, country)
        +static sendEmailChange(newEmail, token)
    }

    class RateLimiter {
        +static hit(key, scope) bool
        +static getAttempts(key, scope) int
        +static reset(key, scope)
    }

    class GeoLocationService {
        +static lookup(ip) array
    }

    class SecurityLogger {
        +static log(eventType, userId, ip, context)
    }

    class NotionService {
        +static syncBookCreated(book)
        +static syncBookUpdated(book)
        +static syncLoanCreated(loan)
        +static syncLoanUpdated(loan)
    }

    class Database {
        -PDO pdo
        +static connection() PDO
    }

    class Response {
        +static json(data, status)
    }

    class Security {
        +static sendSecurityHeaders()
        +static enforceProductionTransport()
        +static ensureStrongJwtSecret()
        +static bootstrapCors()
    }

    AuthController --> AuthMiddleware : usa
    AuthController --> User           : CRUD
    AuthController --> JwtService     : emite/revoca
    AuthController --> MailService    : envía emails
    AuthController --> RateLimiter    : throttling
    AuthController --> GeoLocationService : enriquece login
    AuthController --> SecurityLogger : audita
    AuthMiddleware  --> JwtService    : valida
    User            --> Database      : PDO
    JwtService      --> Database      : revoked_tokens
    AuthController  --> Response      : JSON output
```

---

## 4. Diagrama de despliegue (producción Hostinger)

```mermaid
flowchart TB
    subgraph Cliente
        Nav[Navegador]
    end

    subgraph Hostinger["Hostinger Premium · public_html/"]
        Apache[Apache 2.4 + .htaccess]
        subgraph Static["Estático (Vue build)"]
            Index[index.html]
            JS[JS chunks]
            CSS[CSS]
            Robots[robots.txt + sitemap.xml]
        end
        subgraph Auth["Subcarpeta /auth/"]
            APHP[ApiLoging<br/>index.php]
            Vendor[vendor/<br/>JWT, PHPMailer, GeoIP2]
        end
        subgraph Api["Subcarpeta /api/"]
            LPHP[libros_api.php]
            Uploads[uploads/covers/]
        end
    end

    subgraph Mysql_Hostinger["MySQL Hostinger"]
        DBA[(bibliouser)]
        DBL[(librum-tenebris)]
    end

    subgraph Externos
        SMTP[SMTP Hostinger<br/>mail()]
        Notion[Notion API]
    end

    Nav -->|HTTPS| Apache
    Apache --> Static
    Apache -->|/auth/*| APHP
    Apache -->|/api/*| LPHP
    APHP --> Vendor
    APHP --> DBA
    LPHP --> DBL
    LPHP --> DBA
    APHP -.-> SMTP
    LPHP -.-> Notion
```

---

## Referencias

- **UML 2.5.1 — OMG Specification**: <https://www.omg.org/spec/UML/2.5.1/>
- **Mermaid (sintaxis ER y secuencia)**: <https://mermaid.js.org/intro/>
- **Casos de uso (Cockburn)**: *Writing Effective Use Cases*, Alistair Cockburn, 2000.
