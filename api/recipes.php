<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

setCorsHeaders();
setJsonHeaders();
handleOptions();
startSecureSession();

$method = $_SERVER['REQUEST_METHOD'];
$conn   = getDb();

// ── GET /recipes.php — public, returns all recipes ───────────────────────────
if ($method === 'GET') {
    $stmt   = $conn->prepare('SELECT id, title, description, ingredients, instructions, created_at FROM recipes ORDER BY created_at DESC');
    $stmt->execute();
    $recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode($recipes);
    exit;
}

// ── POST /recipes.php — admin only, add a recipe ─────────────────────────────
if ($method === 'POST') {
    requireAdmin();
    verifyCsrfToken();

    $data = getJsonBody();
    requireFields($data, ['title', 'description', 'ingredients', 'instructions']);

    $title        = trim($data['title']);
    $description  = trim($data['description']);
    $ingredients  = trim($data['ingredients']);
    $instructions = trim($data['instructions']);

    if (strlen($title) > 255) {
        http_response_code(422);
        echo json_encode(['error' => 'Title is too long (max 255 characters)']);
        exit;
    }

    $stmt = $conn->prepare(
        'INSERT INTO recipes (title, description, ingredients, instructions, created_by) VALUES (?, ?, ?, ?, ?)'
    );
    $userId = $_SESSION['user_id'];
    $stmt->bind_param('ssssi', $title, $description, $ingredients, $instructions, $userId);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    http_response_code(201);
    echo json_encode(['success' => true, 'id' => $newId]);
    exit;
}

// ── PUT /recipes.php?id=X — admin only, update a recipe ─────────────────────
if ($method === 'PUT') {
    requireAdmin();
    verifyCsrfToken();

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid recipe id is required']);
        exit;
    }

    $data = getJsonBody();
    requireFields($data, ['title', 'description', 'ingredients', 'instructions']);

    $title        = trim($data['title']);
    $description  = trim($data['description']);
    $ingredients  = trim($data['ingredients']);
    $instructions = trim($data['instructions']);

    if (strlen($title) > 255) {
        http_response_code(422);
        echo json_encode(['error' => 'Title is too long (max 255 characters)']);
        exit;
    }

    $stmt = $conn->prepare(
        'UPDATE recipes SET title=?, description=?, ingredients=?, instructions=? WHERE id=?'
    );
    $stmt->bind_param('ssssi', $title, $description, $ingredients, $instructions, $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Recipe not found']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

// ── DELETE /recipes.php?id=X — admin only ────────────────────────────────────
if ($method === 'DELETE') {
    requireAdmin();
    verifyCsrfToken();

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid recipe id is required']);
        exit;
    }

    $stmt = $conn->prepare('DELETE FROM recipes WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Recipe not found']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);