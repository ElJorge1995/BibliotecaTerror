# Snippets de Código para Documentación Final

Aquí tienes los fragmentos de código más relevantes de tu proyecto, preparados con comentarios explicativos para que queden muy bien en tu documento PDF o presentación.

---

## 1. Seguridad: Autenticación mediante JWT
Este fragmento demuestra la implementación de un sistema de login seguro que utiliza **JSON Web Tokens (JWT)** para la gestión de sesiones sin estado (Stateless).

**Archivo:** `AuthController.php`

```php
public static function login(): void
{
    $data = self::getJsonInput();
    $email = trim((string) ($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    // 1. Protección contra fuerza bruta (Rate Limiting)
    RateLimiter::enforce('login', Security::getClientIp() . '|' . strtolower($email), 10, 900);

    // 2. Validación de credenciales contra Base de Datos
    $user = User::findByEmail($email);
    if (!$user || !password_verify($password, $user['password'])) {
        SecurityLogger::log('login_failed', $user ? (int) $user['id'] : null, ['email' => $email]);
        Response::json(['error' => 'Credenciales inválidas'], 401);
    }

    // 3. Generación del Token JWT si el acceso es correcto
    $token = JwtService::generate($user);
    SecurityLogger::log('login_success', (int) $user['id']);

    Response::json([
        'token' => $token,
        'user' => [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'email' => $user['email'],
        ]
    ]);
}
```

---

## 2. Lógica de Negocio: Transacciones y Stock Atómico
Este código es vital porque demuestra cómo manejar la **consistencia de datos**. Se utiliza una transacción SQL para asegurar que el descuento de stock y el registro del préstamo ocurran ambos o ninguno (Atomicidad).

**Archivo:** `libros_api.php`

```php
// Fragmento de la acción 'prestar'
try {
    $pdo->beginTransaction(); // Inicio de transacción para asegurar integridad

    // 1. Validación de stock actual
    $stmt = $pdo->prepare("SELECT stock FROM libros WHERE id = ?");
    $stmt->execute([$libro_id]);
    $stockActual = $stmt->fetchColumn();

    if ($stockActual <= 0) {
        $pdo->rollBack();
        Response::json(['error' => 'Libro agotado'], 400);
        break;
    }

    // 2. Actualización ATÓMICA del stock
    // El "WHERE stock > 0" previene condiciones de carrera (Race Conditions)
    $stmt = $pdo->prepare("UPDATE libros SET stock = stock - 1 WHERE id = ? AND stock > 0");
    $stmt->execute([$libro_id]);

    if ($stmt->rowCount() == 0) {
        $pdo->rollBack();
        Response::json(['error' => 'Error de concurrencia'], 400);
        break;
    }

    // 3. Registro del préstamo
    $stmt = $pdo->prepare("INSERT INTO prestamos (usuario_id, libro_id, estado) VALUES (?, ?, 'pendiente')");
    $stmt->execute([$usuario_id, $libro_id]);

    $pdo->commit(); // Confirmación de los cambios
} catch (Exception $e) {
    $pdo->rollBack(); // Reversión en caso de error inesperado
}
```

---

## 3. Integración Avanzada: Sincronización con API Externa (Notion)
Este fragmento muestra la integración del proyecto con un servicio externo mediante una arquitectura de microservicios y el uso de **cURL**.

**Archivo:** `NotionService.php`

```php
private static function request(string $method, string $path, ?array $payload = null): ?array
{
    $apiKey = trim((string) getenv('NOTION_API_KEY'));
    $ch = curl_init(self::API_BASE . $path);
    
    // Configuración de Headers según estándares de Notion API
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Notion-Version: ' . self::API_VERSION,
        'Content-Type: application/json'
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 15
    ]);

    if ($payload) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
    
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return json_decode((string)$raw, true);
}
```

---

## 4. Arquitectura: Middleware de Protección de Rutas
Demuestra el control de acceso en el lado del servidor, verificando la validez del JWT y comprobando si el token ha sido revocado (Logout manual).

**Archivo:** `AuthMiddleware.php`

```php
public static function handle(): array
{
    $token = self::extractBearerToken(); // Extrae el token del Header Authorization

    if ($token === null) {
        Response::json(['error' => 'Acceso no autorizado'], 401);
    }

    try {
        // 1. Verificación matemática de la firma del JWT
        $decoded = JwtService::verify($token);

        // 2. Verificación de seguridad: Comprobar lista de revocación (Blacklist)
        $db = Database::connect();
        $stmt = $db->prepare('SELECT id FROM revoked_tokens WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);

        if ($stmt->fetch()) {
            Response::json(['error' => 'Sesion expirada o cerrada'], 401);
        }

        return $decoded; // Devuelve los datos del usuario logueado
    } catch (Throwable $e) {
        Response::json(['error' => 'Token inválido'], 401);
    }
}
```

---

## 5. Frontend: Enrutado y Guardias de Seguridad (Vue Router)
En el frontend, no basta con proteger el backend; también debemos controlar qué páginas puede ver el usuario según su rol (Usuario, Admin o Invitado).

**Archivo:** `src/router/index.js`

```javascript
// Guardia de navegación global
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore(); // Acceso al estado de autenticación (Pinia)

  // 1. Si hay token pero no datos de usuario, los recuperamos
  if (authStore.token && !authStore.user) {
    await authStore.fetchMe();
  }

  const isAuthenticated = authStore.isAuthenticated;

  // 2. Lógica de protección por Meta-tags
  if (to.meta.requiresAuth && !isAuthenticated) {
    // Si la ruta requiere auth y no está logueado -> al Inicio
    return next({ name: 'home' });
  }
  
  if (to.meta.requiresAdmin && !authStore.isAdmin) {
    // Si la ruta requiere ser Admin y el usuario no lo es -> a su Perfil
    return next({ name: 'profile' });
  }
  
  next(); // Continuar a la ruta solicitada
});
```

---

## 6. Frontend: Reactividad y Consumo de API (Composition API)
Este ejemplo combina el uso de **Hooks** de Vue (`ref`, `onMounted`) con una búsqueda en tiempo real que incluye un **Debounce** para no saturar el servidor.

**Archivo:** `SiteHeader.vue`

```javascript
/* <script setup> - Vue 3 Composition API */
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
let searchTimeout = null;

// Función de búsqueda con retardo (Debounce)
const onSearchInput = () => {
  clearTimeout(searchTimeout); // Limpiamos el anterior para esperar a que el usuario deje de escribir
  
  if (!searchQuery.value.trim()) {
    searchResults.value = [];
    return;
  }
  
  searchTimeout = setTimeout(async () => {
    isSearching.value = true;
    try {
      // Consumo de servicio API centralizado
      const res = await booksApi.buscar(searchQuery.value);
      searchResults.value = res.data.data?.slice(0, 5) ?? [];
    } finally {
      isSearching.value = false;
    }
  }, 250); // Espera 250ms antes de lanzar la petición
};
```

---

## 7. Build Tools: Configuración de Vite
Muestra cómo se configura el entorno de desarrollo moderno, definiendo puertos y plugins necesarios para el compilado de archivos `.vue`.

**Archivo:** `vite.config.js`

```javascript
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()], // Plugin para procesar componentes de Vue
  server: {
    port: 5173,      // Puerto de desarrollo estándar
    strictPort: true, // Asegura que si el puerto está ocupado, falle en lugar de usar otro
  },
})
```
