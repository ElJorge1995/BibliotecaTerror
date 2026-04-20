<?php
/**
 * Librum Tenebris - API Principal del Catálogo y Alquileres
 * 
 * Este microservicio maneja todas las interacciones con la base de datos `libros`
 * y `prestamos`. Funciona como un enrutador REST mediante el parámetro GET `action`.
 * Provee listados con filtros de favoritos, calificaciones, control de estados
 * de préstamo y deducción/restauración de stock.
 * 
 * @author ElJorge1995
 * @version 1.5.0
 */

// ---- CORS y Headers ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/../../ApiLoging/config/Env.php';
require_once __DIR__ . '/../../ApiLoging/services/NotionService.php';

Env::load(__DIR__ . '/../../ApiLoging/.env');

/** @var string $action Control principal de rutas RESTful estáticas */
$action = $_GET['action'] ?? '';

switch ($action) {

    /**
     * @route GET /libros_api.php?action=recientes&limit={X}&usuario_id={Y}
     * @desc Devuelve los últimos libros añadidos al catálogo y cruza favoritos si existe usuario.
     */
    // GET /libros_api.php?action=recientes&limit=8
    case 'recientes':
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 8)));
        $usuario_id = (int)($_GET['usuario_id'] ?? 0);
        $fav_select = $usuario_id > 0 ? ", IF(f.id IS NOT NULL, 1, 0) as is_favorito" : "";
        $fav_join = $usuario_id > 0 ? " LEFT JOIN favoritos f ON l.id = f.libro_id AND f.usuario_id = " . $pdo->quote($usuario_id) : "";

        $stmt = $pdo->prepare(
            "SELECT l.id, l.google_id, l.titulo, l.titulo_es, l.autor, l.stock, l.portada, l.rating $fav_select
             FROM libros l $fav_join
             ORDER BY l.id DESC
             LIMIT $limit"
        );
        $stmt->execute();
        $libros = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $libros]);
        break;

    // GET /libros_api.php?action=recomendaciones&limit=32
    case 'recomendaciones':
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 32)));
        $usuario_id = (int)($_GET['usuario_id'] ?? 0);
        $fav_select = $usuario_id > 0 ? ", IF(f.id IS NOT NULL, 1, 0) as is_favorito" : "";
        $fav_join = $usuario_id > 0 ? " LEFT JOIN favoritos f ON l.id = f.libro_id AND f.usuario_id = " . $pdo->quote($usuario_id) : "";

        $stmt = $pdo->prepare(
            "SELECT l.id, l.google_id, l.titulo, l.titulo_es, l.autor, l.stock, l.portada, l.rating $fav_select
             FROM libros l $fav_join
             WHERE l.rating >= 4.0
             ORDER BY l.rating DESC, l.id DESC
             LIMIT $limit"
        );
        $stmt->execute();
        $recomendados = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $recomendados]);
        break;

    // GET /libros_api.php?action=buscar&q=termino
    case 'buscar':
        $q = trim($_GET['q'] ?? '');
        $usuario_id = (int)($_GET['usuario_id'] ?? 0);
        if (empty($q)) {
            echo json_encode(['success' => true, 'data' => []]);
            break;
        }

        $fav_select = $usuario_id > 0 ? ", IF(f.id IS NOT NULL, 1, 0) as is_favorito" : "";
        $fav_join = $usuario_id > 0 ? " LEFT JOIN favoritos f ON l.id = f.libro_id AND f.usuario_id = " . $pdo->quote($usuario_id) : "";

        $searchTerm = "%$q%";
        $stmt = $pdo->prepare(
            "SELECT l.id, l.google_id, l.titulo, l.titulo_es, l.autor, l.stock, l.portada, l.rating $fav_select
             FROM libros l $fav_join
             WHERE l.titulo LIKE :q OR l.titulo_es LIKE :q OR l.autor LIKE :q
             ORDER BY l.id DESC
             LIMIT 50"
        );
        $stmt->execute(['q' => $searchTerm]);
        $libros = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $libros]);
        break;

    case 'count_active_loans':
        $usuario_id = (int)($_GET['usuario_id'] ?? 0);
        if ($usuario_id <= 0) {
            echo json_encode(['success' => false, 'count' => 0]);
            break;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM prestamos WHERE usuario_id = ? AND estado IN ('activo', 'pendiente')");
        $stmt->execute([$usuario_id]);
        $count = (int)$stmt->fetchColumn();
        echo json_encode(['success' => true, 'count' => $count]);
        break;

    // GET /libros_api.php?action=obtener&id=123
    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de libro inválido']);
            break;
        }

        $stmt = $pdo->prepare(
            "SELECT id, google_id, titulo, titulo_es, autor, stock, descripcion, descripcion_es, portada
             FROM libros
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $libro = $stmt->fetch();

        if ($libro) {
            echo json_encode(['success' => true, 'data' => $libro]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Libro no encontrado']);
        }
        break;

    // POST /libros_api.php?action=toggle_favorito
    // Body json: { "usuario_id": 1, "libro_id": 2 }
    case 'toggle_favorito':
        $data = json_decode(file_get_contents('php://input'), true);
        $usuario_id = (int)($data['usuario_id'] ?? 0);
        $libro_id = (int)($data['libro_id'] ?? 0);
        
        if ($usuario_id <= 0 || $libro_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetros inválidos']);
            break;
        }

        $stmt = $pdo->prepare("SELECT id FROM favoritos WHERE usuario_id = ? AND libro_id = ?");
        $stmt->execute([$usuario_id, $libro_id]);
        if ($stmt->fetch()) {
            // Borrar si existe
            $stmt = $pdo->prepare("DELETE FROM favoritos WHERE usuario_id = ? AND libro_id = ?");
            $stmt->execute([$usuario_id, $libro_id]);
            echo json_encode(['success' => true, 'is_favorito' => false]);
        } else {
            // Insertar si no existe
            $stmt = $pdo->prepare("INSERT INTO favoritos (usuario_id, libro_id) VALUES (?, ?)");
            $stmt->execute([$usuario_id, $libro_id]);
            echo json_encode(['success' => true, 'is_favorito' => true]);
        }
        break;

    // GET /libros_api.php?action=check_favorito&usuario_id=1&libro_id=2
    case 'check_favorito':
        $usuario_id = (int)($_GET['usuario_id'] ?? 0);
        $libro_id = (int)($_GET['libro_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id FROM favoritos WHERE usuario_id = ? AND libro_id = ?");
        $stmt->execute([$usuario_id, $libro_id]);
        $isFav = (bool)$stmt->fetch();
        echo json_encode(['success' => true, 'is_favorito' => $isFav]);
        break;

    // GET /libros_api.php?action=mis_favoritos&usuario_id=1
    case 'mis_favoritos':
        $usuario_id = (int)($_GET['usuario_id'] ?? 0);
        $stmt = $pdo->prepare(
            "SELECT l.id, l.google_id, l.titulo, l.titulo_es, l.autor, l.stock, l.portada 
             FROM libros l
             JOIN favoritos f ON l.id = f.libro_id
             WHERE f.usuario_id = ?
             ORDER BY f.created_at DESC"
        );
        $stmt->execute([$usuario_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    // GET /libros_api.php?action=mis_prestamos&usuario_id=X
    case 'mis_prestamos':
        $usuario_id = (int)($_GET['usuario_id'] ?? 0);
        if ($usuario_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'usuario_id inválido']);
            break;
        }
        $stmt = $pdo->prepare("
            SELECT p.id as prestamo_id, p.fecha_prestamo, p.fecha_devolucion, p.estado, p.rating as tu_rating, p.fecha_entregado,
                   l.id as libro_id, l.google_id, l.titulo, l.autor, l.portada, l.rating as nota_media 
            FROM prestamos p
            JOIN libros l ON p.libro_id = l.id
            WHERE p.usuario_id = ?
            ORDER BY p.fecha_prestamo DESC
        ");
        $stmt->execute([$usuario_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    // GET /libros_api.php?action=todos_prestamos (Administrator Only - No User Filter)
    case 'todos_prestamos':
        $stmt = $pdo->query("
            SELECT p.id as prestamo_id, p.usuario_id, p.nombre_usuario, p.fecha_prestamo, p.fecha_devolucion, p.estado, p.fecha_entregado,
                   l.id as libro_id, l.titulo, l.autor, l.portada 
            FROM prestamos p
            JOIN libros l ON p.libro_id = l.id
            ORDER BY p.fecha_prestamo ASC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    // GET /libros_api.php?action=todos
    case 'todos':
        $usuario_id = (int)($_GET['usuario_id'] ?? 0);
        $fav_select = $usuario_id > 0 ? ", IF(f.id IS NOT NULL, 1, 0) as is_favorito" : "";
        $fav_join = $usuario_id > 0 ? " LEFT JOIN favoritos f ON l.id = f.libro_id AND f.usuario_id = " . $pdo->quote($usuario_id) : "";

        $stmt = $pdo->query(
            "SELECT l.id, l.titulo, l.titulo_es, l.autor, l.stock, l.portada, l.rating, l.categoria, l.google_id $fav_select
             FROM libros l $fav_join
             ORDER BY l.id DESC"
        );
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    // POST /libros_api.php?action=crear
    case 'crear':
        $isbn = $_POST['google_id'] ?? '';
        $titulo = $_POST['titulo'] ?? '';
        $autor = $_POST['autor'] ?? '';
        $stock = (int)($_POST['stock'] ?? 3);
        $categoria = $_POST['categoria'] ?? '';
        
        if (empty($isbn) || empty($titulo) || empty($autor) || empty($categoria)) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan campos obligatorios']);
            break;
        }

        $portada_url = null;
        if (isset($_FILES['portada']) && $_FILES['portada']['error'] == UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['portada']['tmp_name'];
            $name = basename($_FILES['portada']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($ext, $allowed)) {
                $upload_dir = __DIR__ . '/uploads/covers/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                $new_filename = preg_replace('/[^a-zA-Z0-9-]/', '', $isbn) . '_' . time() . '.' . $ext;
                $dest_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($tmp_name, $dest_path)) {
                    $portada_url = 'http://localhost:8080/uploads/covers/' . $new_filename;
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Error subiendo la portada']);
                    break;
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Formato de imagen no permitido (solo jpg, png, webp)']);
                break;
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'La imagen de portada es obligatoria']);
            break;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO libros (google_id, titulo, autor, stock, categoria, portada) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$isbn, $titulo, $autor, $stock, $categoria, $portada_url]);
            $new_id = $pdo->lastInsertId();
            NotionService::syncBookCreated([
                'id' => $new_id, 'google_id' => $isbn, 'titulo' => $titulo, 
                'autor' => $autor, 'stock' => $stock, 'categoria' => $categoria, 
                'portada' => $portada_url, 'rating' => 0
            ]);
            echo json_encode(['success' => true, 'id' => $new_id]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error guardando en BD: ' . $e->getMessage()]);
        }
        break;

    // POST /libros_api.php?action=editar_libro
    case 'editar_libro':
        $id = (int)($_POST['id'] ?? 0);
        $isbn = $_POST['google_id'] ?? '';
        $titulo = $_POST['titulo'] ?? '';
        $autor = $_POST['autor'] ?? '';
        $stock = (int)($_POST['stock'] ?? 1);
        $categoria = $_POST['categoria'] ?? '';
        
        if ($id <= 0 || empty($titulo) || empty($autor) || empty($categoria)) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan campos obligatorios']);
            break;
        }

        $portada_url = null;
        if (isset($_FILES['portada']) && $_FILES['portada']['error'] == UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['portada']['tmp_name'];
            $name = basename($_FILES['portada']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($ext, $allowed)) {
                $upload_dir = __DIR__ . '/uploads/covers/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                $new_filename = preg_replace('/[^a-zA-Z0-9-]/', '', $isbn ?: $id) . '_' . time() . '.' . $ext;
                $dest_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($tmp_name, $dest_path)) {
                    $portada_url = 'http://localhost:8080/uploads/covers/' . $new_filename;
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Error subiendo la portada']);
                    break;
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Formato de imagen no permitido']);
                break;
            }
        }

        try {
            if ($portada_url) {
                $stmt = $pdo->prepare("UPDATE libros SET google_id = ?, titulo = ?, autor = ?, stock = ?, categoria = ?, portada = ? WHERE id = ?");
                $stmt->execute([$isbn, $titulo, $autor, $stock, $categoria, $portada_url, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE libros SET google_id = ?, titulo = ?, autor = ?, stock = ?, categoria = ? WHERE id = ?");
                $stmt->execute([$isbn, $titulo, $autor, $stock, $categoria, $id]);
            }
            // Sincronizar actualización con Notion
            $stmtGet = $pdo->prepare("SELECT * FROM libros WHERE id = ?");
            $stmtGet->execute([$id]);
            $updatedBook = $stmtGet->fetch();
            if ($updatedBook) NotionService::syncBookUpdated($updatedBook);
            
            echo json_encode(['success' => true]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error guardando en BD: ' . $e->getMessage()]);
        }
        break;

    // POST /libros_api.php?action=prestar
    case 'prestar':
        $data = json_decode(file_get_contents('php://input'), true);
        $usuario_id = (int)($data['usuario_id'] ?? 0);
        $libro_id = (int)($data['libro_id'] ?? 0);
        $nombre_usuario = $data['nombre_usuario'] ?? 'Desconocido';
        
        if ($usuario_id <= 0 || $libro_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetros inválidos']);
            break;
        }

        try {
            $pdo->beginTransaction();

            // 1. Contar préstamos activos o pendientes del usuario (límite 2)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM prestamos WHERE usuario_id = ? AND estado IN ('activo', 'pendiente')");
            $stmt->execute([$usuario_id]);
            $prestamosActivos = $stmt->fetchColumn();

            if ($prestamosActivos >= 2) {
                $pdo->rollBack();
                http_response_code(403);
                echo json_encode(['error' => 'Ya has alcanzado el límite máximo de 2 libros prestados.']);
                break;
            }

            // 2. Verificar stock del libro (X lock for update opcional, haremos query atómica luego)
            $stmt = $pdo->prepare("SELECT stock FROM libros WHERE id = ?");
            $stmt->execute([$libro_id]);
            $stockActual = $stmt->fetchColumn();

            if ($stockActual === false || $stockActual <= 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'El libro está agotado actualmente.']);
                break;
            }

            // 3. Restar stock
            $stmt = $pdo->prepare("UPDATE libros SET stock = stock - 1 WHERE id = ? AND stock > 0");
            $stmt->execute([$libro_id]);

            if ($stmt->rowCount() == 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'Error de concurrencia: El libro se acaba de agotar.']);
                break;
            }

            // 4. Crear préstamo en espera de reserva ('pendiente' y caducidad Nula)
            $new_loan_id = $pdo->lastInsertId();

            $pdo->commit();

            // Sincronizar prestamo con Notion (necesitamos el titulo del libro)
            $stmtLibro = $pdo->prepare("SELECT titulo FROM libros WHERE id = ?");
            $stmtLibro->execute([$libro_id]);
            $tituloLibro = $stmtLibro->fetchColumn() ?: 'Libro Desconocido';
            
            NotionService::syncLoanCreated([
                'id' => $new_loan_id, 'titulo' => $tituloLibro, 
                'nombre_usuario' => $nombre_usuario, 'estado' => 'pendiente',
                'fecha_prestamo' => date('Y-m-d H:i:s'), 'id_usuario' => $usuario_id
            ]);

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Error procesando el préstamo: ' . $e->getMessage()]);
        }
        break;

    // POST /libros_api.php?action=actualizar_prestamo
    case 'actualizar_prestamo':
        $data = json_decode(file_get_contents('php://input'), true);
        $prestamo_id = (int)($data['prestamo_id'] ?? 0);
        $nuevo_estado = $data['estado'] ?? '';
        
        $estados_validos = ['pendiente', 'activo', 'devuelto'];
        if ($prestamo_id <= 0 || !in_array($nuevo_estado, $estados_validos)) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetros inválidos o estado incorrecto.']);
            break;
        }

        try {
            $pdo->beginTransaction();

            // Averiguamos el estado anterior y el libro_id
            $stmt = $pdo->prepare("SELECT libro_id, estado FROM prestamos WHERE id = ?");
            $stmt->execute([$prestamo_id]);
            $prestamo = $stmt->fetch();

            if (!$prestamo) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['error' => 'Préstamo no encontrado']);
                break;
            }

            $estado_anterior = $prestamo['estado'];
            $libro_id = $prestamo['libro_id'];

            // Logica del Stock basada en el cambio
            if ($estado_anterior !== 'devuelto' && $nuevo_estado === 'devuelto') {
                // Devolvemos la unidad
                $stmtStock = $pdo->prepare("UPDATE libros SET stock = stock + 1 WHERE id = ?");
                $stmtStock->execute([$libro_id]);
            } else if ($estado_anterior === 'devuelto' && $nuevo_estado !== 'devuelto') {
                // Si el admin cometió un error y lo vuelve a poner como Activo, hay que volver a quitar el libro del sistema
                $stmtStock = $pdo->prepare("UPDATE libros SET stock = stock - 1 WHERE id = ?");
                $stmtStock->execute([$libro_id]);
            }

            // Actualizamos la fila y asignamos los 14 días si pasa de pendiente a activo por primera vez
            if ($estado_anterior === 'pendiente' && $nuevo_estado === 'activo') {
                $stmtUpdate = $pdo->prepare("UPDATE prestamos SET estado = ?, fecha_prestamo = NOW(), fecha_devolucion = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE id = ?");
            } else if ($nuevo_estado === 'devuelto') {
                $stmtUpdate = $pdo->prepare("UPDATE prestamos SET estado = ?, fecha_entregado = NOW() WHERE id = ?");
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE prestamos SET estado = ? WHERE id = ?");
            }
            $stmtUpdate->execute([$nuevo_estado, $prestamo_id]);

            $pdo->commit();
            
            // Sincronizar prestamo con Notion
            $stmtGet = $pdo->prepare("SELECT p.*, l.titulo FROM prestamos p JOIN libros l ON p.libro_id = l.id WHERE p.id = ?");
            $stmtGet->execute([$prestamo_id]);
            $updatedLoan = $stmtGet->fetch();
            if ($updatedLoan) NotionService::syncLoanUpdated($updatedLoan);

            echo json_encode(['success' => true, 'mensaje' => 'Estado actualizado y stock ajustado']);

        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Error al modificar préstamo: ' . $e->getMessage()]);
        }
        break;

    // POST /libros_api.php?action=valorar_prestamo
    case 'valorar_prestamo':
        $data = json_decode(file_get_contents('php://input'), true);
        $prestamo_id = (int)($data['prestamo_id'] ?? 0);
        $rating = (int)($data['rating'] ?? 0);

        if ($prestamo_id <= 0 || $rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['error' => 'Valoración inválida.']);
            break;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT libro_id, usuario_id FROM prestamos WHERE id = ?");
            $stmt->execute([$prestamo_id]);
            $prestamo = $stmt->fetch();

            if (!$prestamo) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['error' => 'Préstamo no encontrado']);
                break;
            }

            $libro_id = $prestamo['libro_id'];

            // Guardamos el rating del prestamo individual
            $stmtUpdateP = $pdo->prepare("UPDATE prestamos SET rating = ? WHERE id = ?");
            $stmtUpdateP->execute([$rating, $prestamo_id]);

            // Forzamos el recálculo matemático de la nota del libro y lo grabamos:
            $stmtMath = $pdo->prepare("
              UPDATE libros 
              SET rating = (
                  SELECT COALESCE(AVG(rating), 0) 
                  FROM prestamos 
                  WHERE libro_id = ? AND rating IS NOT NULL
              )
              WHERE id = ?
            ");
            $stmtMath->execute([$libro_id, $libro_id]);

            $pdo->commit();

            // Sincronizar valoracion con Notion
            $stmtGet = $pdo->prepare("SELECT p.*, l.titulo FROM prestamos p JOIN libros l ON p.libro_id = l.id WHERE p.id = ?");
            $stmtGet->execute([$prestamo_id]);
            $updatedLoan = $stmtGet->fetch();
            if ($updatedLoan) NotionService::syncLoanUpdated($updatedLoan);

            // Tambien sincronizar el rating actualizado del libro
            $stmtGetLibro = $pdo->prepare("SELECT * FROM libros WHERE id = ?");
            $stmtGetLibro->execute([$libro_id]);
            $updatedBook = $stmtGetLibro->fetch();
            if ($updatedBook) NotionService::syncBookUpdated($updatedBook);

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Error al calcular rating: ' . $e->getMessage()]);
        }
        break;

    // POST /libros_api.php?action=admin_crear_prestamo
    case 'admin_crear_prestamo':
        $data = json_decode(file_get_contents('php://input'), true);
        $dni = trim((string)($data['dni'] ?? ''));
        $libro_titulo = trim((string)($data['libro_titulo'] ?? ''));
        $fecha_devolucion = $data['fecha_devolucion'] ?? null;

        if (empty($dni) || empty($libro_titulo)) {
            http_response_code(400);
            echo json_encode(['error' => 'DNI y título del libro son obligatorios.']);
            break;
        }

        try {
            $pdo->beginTransaction();

            // 1. Buscar usuario por DNI en la db 'bibliouser'
            // Usamos una conexión temporal para no interferir con la principal
            $dsn_user = "mysql:host=localhost;dbname=bibliouser;charset=utf8mb4";
            $pdo_user = new PDO($dsn_user, "root", "");
            $pdo_user->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo_user->prepare("SELECT id, name FROM users WHERE dni = ?");
            $stmt->execute([$dni]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['error' => "No se encontró ningún usuario con DNI: $dni"]);
                break;
            }

            $usuario_id = $user['id'];
            $nombre_usuario = $user['name'];

            // 2. Buscar libro por título
            $stmt = $pdo->prepare("SELECT id, stock FROM libros WHERE titulo = ? OR titulo_es = ? LIMIT 1");
            $stmt->execute([$libro_titulo, $libro_titulo]);
            $libro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$libro) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['error' => "No se encontró ningún libro con título: $libro_titulo"]);
                break;
            }

            $libro_id = $libro['id'];
            $stockActual = $libro['stock'];

            // 3. Verificar límites de préstamo del usuario (incluye activos y pendientes)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM prestamos WHERE usuario_id = ? AND estado IN ('activo', 'pendiente')");
            $stmt->execute([$usuario_id]);
            $prestamosActivos = $stmt->fetchColumn();

            if ($prestamosActivos >= 2) {
                $pdo->rollBack();
                http_response_code(403);
                echo json_encode(['error' => 'El usuario ya tiene el límite máximo de 2 libros prestados.']);
                break;
            }

            // 4. Verificar stock
            if ($stockActual <= 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'El libro no tiene stock disponible.']);
                break;
            }

            // 5. Restar stock
            $stmt = $pdo->prepare("UPDATE libros SET stock = stock - 1 WHERE id = ? AND stock > 0");
            $stmt->execute([$libro_id]);

            // 6. Crear préstamo (Activo directamente)
            $final_devolucion = $fecha_devolucion ? $fecha_devolucion : date('Y-m-d H:i:s', strtotime('+15 days'));
            $stmt = $pdo->prepare("INSERT INTO prestamos (usuario_id, nombre_usuario, libro_id, estado, fecha_prestamo, fecha_devolucion) VALUES (?, ?, ?, 'activo', NOW(), ?)");
            $stmt->execute([$usuario_id, $nombre_usuario, $libro_id, $final_devolucion]);

            $new_loan_id = $pdo->lastInsertId();
            $pdo->commit();

            // Sincronizar con Notion
            NotionService::syncLoanCreated([
                'id' => $new_loan_id, 'titulo' => $libro_titulo, 
                'nombre_usuario' => $nombre_usuario, 'estado' => 'activo',
                'fecha_prestamo' => date('Y-m-d H:i:s'), 'fecha_devolucion' => $final_devolucion,
                'dni' => $dni, 'id_usuario' => $usuario_id
            ]);

            echo json_encode(['success' => true, 'mensaje' => 'Préstamo creado con éxito.']);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear préstamo manual: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Acción no encontrada']);
        break;
}
