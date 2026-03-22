<?php
// ---- CORS ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/conexion.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // GET /libros_api.php?action=recientes&limit=8
    case 'recientes':
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 8)));

        $stmt = $pdo->prepare(
            "SELECT id, google_id, titulo, titulo_es, autor, stock, portada
             FROM libros
             ORDER BY id DESC
             LIMIT $limit"
        );
        $stmt->execute();
        $libros = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $libros]);
        break;

    // GET /libros_api.php?action=buscar&q=termino
    case 'buscar':
        $q = trim($_GET['q'] ?? '');
        if (empty($q)) {
            echo json_encode(['success' => true, 'data' => []]);
            break;
        }

        $searchTerm = "%$q%";
        $stmt = $pdo->prepare(
            "SELECT id, google_id, titulo, titulo_es, autor, stock, portada
             FROM libros
             WHERE titulo LIKE :q OR titulo_es LIKE :q OR autor LIKE :q
             ORDER BY id DESC
             LIMIT 50"
        );
        $stmt->execute(['q' => $searchTerm]);
        $libros = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $libros]);
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

    // GET /libros_api.php?action=todos
    case 'todos':
        $stmt = $pdo->query(
            "SELECT id, titulo, titulo_es, autor, stock 
             FROM libros 
             ORDER BY id DESC"
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
            echo json_encode(['success' => true, 'id' => $new_id]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error guardando en BD: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Acción no encontrada']);
        break;
}
