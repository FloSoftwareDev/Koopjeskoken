<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

setCorsHeaders();
setJsonHeaders();
handleOptions();
startSecureSession();

$method = $_SERVER['REQUEST_METHOD'];
$conn   = getDb();

// ── GET — auth required (matches the rest of the site) ─────────────────────
if ($method === 'GET') {
    requireAuth();
    $slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : null;
    $id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($slug !== null && $slug !== '') {
        echo json_encode(loadOneBySlug($conn, $slug) ?? ['error' => 'Recipe not found']);
    } elseif ($id) {
        echo json_encode(loadOneById($conn, (int)$id) ?? ['error' => 'Recipe not found']);
    } else {
        echo json_encode(loadAll($conn));
    }
    exit;
}

// ── Admin-only writes ───────────────────────────────────────────────────────
requireAdmin();
verifyCsrfToken();

if ($method === 'POST') {
    $data = getJsonBody();
    $id   = saveRecipe($conn, null, $data);
    http_response_code(201);
    echo json_encode(['success' => true, 'id' => $id]);
    exit;
}

if ($method === 'PUT') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid recipe id is required']);
        exit;
    }
    $data = getJsonBody();
    saveRecipe($conn, (int)$id, $data);
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
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
exit;


// ── Helpers ──────────────────────────────────────────────────────────────────

function loadAll(mysqli $conn): array {
    $recipes = [];
    $byId    = [];

    $res = $conn->query(
        'SELECT id, slug, name, description, gradient, time_minutes, persons, difficulty,
                price_now, price_was, total_now, total_was, saving,
                allergen_gluten, allergen_lactose, allergen_noten, allergen_pinda,
                allergen_ei, allergen_soja, allergen_vis, allergen_schaaldieren,
                nutrition_kcal, nutrition_eiwit, nutrition_koolh, nutrition_vet
         FROM recipes
         ORDER BY CAST(slug AS UNSIGNED), slug'
    );
    while ($row = $res->fetch_assoc()) {
        $r = shapeRecipe($row);
        $recipes[] = &$r;
        $byId[(int)$row['id']] = &$r;
        unset($r);
    }

    if (!$recipes) return [];

    $ids   = array_keys($byId);
    $inSql = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    // Ingredients
    $stmt = $conn->prepare(
        "SELECT recipe_id, name, per_persoon, unit, inkoop, inkoop_qty,
                prijs_per_pak, pakken_base, deal, supermarkt
         FROM recipe_ingredients
         WHERE recipe_id IN ($inSql)
         ORDER BY recipe_id, position"
    );
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $byId[(int)$row['recipe_id']]['ingredients'][] = shapeIngredient($row);
    }
    $stmt->close();

    // Steps
    $stmt = $conn->prepare(
        "SELECT recipe_id, text FROM recipe_steps
         WHERE recipe_id IN ($inSql) ORDER BY recipe_id, position"
    );
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $byId[(int)$row['recipe_id']]['steps'][] = $row['text'];
    }
    $stmt->close();

    // Tags / diets / supermarkets
    fillMany($conn, 'recipe_tags',         'tag',        'tags',        $byId, $ids, $types, $inSql);
    fillMany($conn, 'recipe_diets',        'diet',       'diets',       $byId, $ids, $types, $inSql);
    fillMany($conn, 'recipe_supermarkets', 'supermarkt', 'supermarkts', $byId, $ids, $types, $inSql);

    return $recipes;
}

function loadOneById(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare('SELECT * FROM recipes WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    return loadOneInternal($conn, $stmt);
}

function loadOneBySlug(mysqli $conn, string $slug): ?array {
    $stmt = $conn->prepare('SELECT * FROM recipes WHERE slug = ? LIMIT 1');
    $stmt->bind_param('s', $slug);
    return loadOneInternal($conn, $stmt);
}

function loadOneInternal(mysqli $conn, mysqli_stmt $stmt): ?array {
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;

    $recipe = shapeRecipe($row);
    $id     = (int)$row['id'];

    $children = [
        'ingredients' => ['table' => 'recipe_ingredients',
                          'cols'  => 'name, per_persoon, unit, inkoop, inkoop_qty, prijs_per_pak, pakken_base, deal, supermarkt',
                          'shape' => 'shapeIngredient'],
        'steps'       => ['table' => 'recipe_steps', 'cols' => 'text', 'shape' => 'text'],
    ];

    foreach ($children as $key => $cfg) {
        $s = $conn->prepare("SELECT {$cfg['cols']} FROM {$cfg['table']} WHERE recipe_id = ? ORDER BY position");
        $s->bind_param('i', $id);
        $s->execute();
        $rs = $s->get_result();
        while ($r = $rs->fetch_assoc()) {
            $recipe[$key][] = $cfg['shape'] === 'text' ? $r['text'] : shapeIngredient($r);
        }
        $s->close();
    }

    foreach (['tags' => 'tag', 'diets' => 'diet', 'supermarkts' => 'supermarkt'] as $key => $col) {
        $table = 'recipe_' . ($key === 'supermarkts' ? 'supermarkets' : $key);
        $s = $conn->prepare("SELECT $col FROM $table WHERE recipe_id = ?");
        $s->bind_param('i', $id);
        $s->execute();
        $rs = $s->get_result();
        while ($r = $rs->fetch_assoc()) $recipe[$key][] = $r[$col];
        $s->close();
    }

    return $recipe;
}

function fillMany(mysqli $conn, string $table, string $col, string $key, array &$byId, array $ids, string $types, string $inSql): void {
    $stmt = $conn->prepare("SELECT recipe_id, $col FROM $table WHERE recipe_id IN ($inSql)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $byId[(int)$row['recipe_id']][$key][] = $row[$col];
    }
    $stmt->close();
}

function shapeRecipe(array $row): array {
    return [
        'id'          => $row['slug'],
        '_dbId'       => (int)$row['id'],
        'name'        => $row['name'],
        'gradient'    => $row['gradient'],
        'desc'        => $row['description'],
        'time'        => (int)$row['time_minutes'],
        'persons'     => (int)$row['persons'],
        'difficulty'  => $row['difficulty'],
        'priceNow'    => (float)$row['price_now'],
        'priceWas'    => (float)$row['price_was'],
        'totalNow'    => (float)$row['total_now'],
        'totalWas'    => (float)$row['total_was'],
        'saving'      => (float)$row['saving'],
        'tags'        => [],
        'diets'       => [],
        'supermarkts' => [],
        'ingredients' => [],
        'steps'       => [],
        'allergens'   => [
            'gluten'       => (bool)$row['allergen_gluten'],
            'lactose'      => (bool)$row['allergen_lactose'],
            'noten'        => (bool)$row['allergen_noten'],
            'pinda'        => (bool)$row['allergen_pinda'],
            'ei'           => (bool)$row['allergen_ei'],
            'soja'         => (bool)$row['allergen_soja'],
            'vis'          => (bool)$row['allergen_vis'],
            'schaaldieren' => (bool)$row['allergen_schaaldieren'],
        ],
        'nutrition'   => [
            'kcal'  => (int)$row['nutrition_kcal'],
            'eiwit' => (int)$row['nutrition_eiwit'],
            'koolh' => (int)$row['nutrition_koolh'],
            'vet'   => (int)$row['nutrition_vet'],
        ],
    ];
}

function shapeIngredient(array $row): array {
    return [
        'name'        => $row['name'],
        'perPersoon'  => (float)$row['per_persoon'],
        'unit'        => $row['unit'],
        'inkoop'      => $row['inkoop'],
        'inkoopQty'   => (float)$row['inkoop_qty'],
        'prijsPerPak' => (float)$row['prijs_per_pak'],
        'pakkenBase'  => (int)$row['pakken_base'],
        'deal'        => (bool)$row['deal'],
        'sm'          => $row['supermarkt'],
    ];
}

// ── Write helpers ────────────────────────────────────────────────────────────

function saveRecipe(mysqli $conn, ?int $id, array $d): int {
    $required = ['name','gradient','desc','time','persons','difficulty',
                 'priceNow','priceWas','totalNow','totalWas','saving',
                 'allergens','nutrition'];
    foreach ($required as $f) {
        if (!isset($d[$f])) {
            http_response_code(422);
            echo json_encode(['error' => "Missing field: $f"]);
            exit;
        }
    }

    $name        = (string)$d['name'];
    $description = (string)$d['desc'];
    $gradient    = (string)$d['gradient'];
    $time        = (int)$d['time'];
    $persons     = (int)$d['persons'];
    $difficulty  = (string)$d['difficulty'];
    if (!in_array($difficulty, ['Makkelijk','Gemiddeld','Moeilijk'], true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid difficulty']);
        exit;
    }
    $priceNow = (float)$d['priceNow'];
    $priceWas = (float)$d['priceWas'];
    $totalNow = (float)$d['totalNow'];
    $totalWas = (float)$d['totalWas'];
    $saving   = (float)$d['saving'];

    $a = $d['allergens'];
    $gluten       = (int)!empty($a['gluten']);
    $lactose      = (int)!empty($a['lactose']);
    $noten        = (int)!empty($a['noten']);
    $pinda        = (int)!empty($a['pinda']);
    $ei           = (int)!empty($a['ei']);
    $soja         = (int)!empty($a['soja']);
    $vis          = (int)!empty($a['vis']);
    $schaaldieren = (int)!empty($a['schaaldieren']);

    $n = $d['nutrition'];
    $kcal  = (int)$n['kcal'];
    $eiwit = (int)$n['eiwit'];
    $koolh = (int)$n['koolh'];
    $vet   = (int)$n['vet'];

    $slug = isset($d['id']) && trim((string)$d['id']) !== '' ? trim((string)$d['id']) : null;

    $conn->begin_transaction();
    try {
        if ($id === null) {
            if ($slug === null) {
                $slug = nextSlug($conn);
            } elseif (slugTaken($conn, $slug, null)) {
                throw new RuntimeException("Slug '$slug' is already in use");
            }
            $stmt = $conn->prepare(
                'INSERT INTO recipes
                   (slug, name, description, gradient, time_minutes, persons, difficulty,
                    price_now, price_was, total_now, total_was, saving,
                    allergen_gluten, allergen_lactose, allergen_noten, allergen_pinda,
                    allergen_ei, allergen_soja, allergen_vis, allergen_schaaldieren,
                    nutrition_kcal, nutrition_eiwit, nutrition_koolh, nutrition_vet, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $userId = (int)$_SESSION['user_id'];
            $stmt->bind_param(
                'ssssiisdddddiiiiiiiiiiiii',
                $slug, $name, $description, $gradient, $time, $persons, $difficulty,
                $priceNow, $priceWas, $totalNow, $totalWas, $saving,
                $gluten, $lactose, $noten, $pinda, $ei, $soja, $vis, $schaaldieren,
                $kcal, $eiwit, $koolh, $vet, $userId
            );
            $stmt->execute();
            $id = $conn->insert_id;
            $stmt->close();
        } else {
            // existence check
            $check = $conn->prepare('SELECT slug FROM recipes WHERE id = ? LIMIT 1');
            $check->bind_param('i', $id);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();
            if (!$existing) {
                throw new RuntimeException('Recipe not found', 404);
            }
            if ($slug === null) {
                $slug = $existing['slug'];
            } elseif ($slug !== $existing['slug'] && slugTaken($conn, $slug, $id)) {
                throw new RuntimeException("Slug '$slug' is already in use");
            }

            $stmt = $conn->prepare(
                'UPDATE recipes SET
                   slug=?, name=?, description=?, gradient=?, time_minutes=?, persons=?, difficulty=?,
                   price_now=?, price_was=?, total_now=?, total_was=?, saving=?,
                   allergen_gluten=?, allergen_lactose=?, allergen_noten=?, allergen_pinda=?,
                   allergen_ei=?, allergen_soja=?, allergen_vis=?, allergen_schaaldieren=?,
                   nutrition_kcal=?, nutrition_eiwit=?, nutrition_koolh=?, nutrition_vet=?
                 WHERE id=?'
            );
            $stmt->bind_param(
                'ssssiisdddddiiiiiiiiiiiii',
                $slug, $name, $description, $gradient, $time, $persons, $difficulty,
                $priceNow, $priceWas, $totalNow, $totalWas, $saving,
                $gluten, $lactose, $noten, $pinda, $ei, $soja, $vis, $schaaldieren,
                $kcal, $eiwit, $koolh, $vet, $id
            );
            $stmt->execute();
            $stmt->close();

            // wipe child rows
            foreach (['recipe_ingredients','recipe_steps','recipe_tags','recipe_diets','recipe_supermarkets'] as $t) {
                $s = $conn->prepare("DELETE FROM $t WHERE recipe_id = ?");
                $s->bind_param('i', $id);
                $s->execute();
                $s->close();
            }
        }

        saveChildren($conn, $id, $d);

        $conn->commit();
        return $id;
    } catch (Throwable $e) {
        $conn->rollback();
        $code = $e->getCode() === 404 ? 404 : 422;
        http_response_code($code);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

function saveChildren(mysqli $conn, int $id, array $d): void {
    $insIng  = $conn->prepare(
        'INSERT INTO recipe_ingredients
           (recipe_id, position, name, per_persoon, unit, inkoop, inkoop_qty,
            prijs_per_pak, pakken_base, deal, supermarkt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach (($d['ingredients'] ?? []) as $pos => $ing) {
        $position = (int)$pos;
        $iname    = (string)($ing['name'] ?? '');
        $pp       = (float)($ing['perPersoon'] ?? 0);
        $unit     = (string)($ing['unit'] ?? '');
        $inkoop   = (string)($ing['inkoop'] ?? '');
        $iqty     = (float)($ing['inkoopQty'] ?? 0);
        $prijs    = (float)($ing['prijsPerPak'] ?? 0);
        $pakken   = (int)($ing['pakkenBase'] ?? 1);
        $deal     = (int)!empty($ing['deal']);
        $sm       = (string)($ing['sm'] ?? '');
        $insIng->bind_param(
            'iisdssddiis',
            $id, $position, $iname, $pp, $unit, $inkoop, $iqty,
            $prijs, $pakken, $deal, $sm
        );
        $insIng->execute();
    }
    $insIng->close();

    $insStep = $conn->prepare('INSERT INTO recipe_steps (recipe_id, position, text) VALUES (?, ?, ?)');
    foreach (($d['steps'] ?? []) as $pos => $text) {
        $position = (int)$pos;
        $textStr  = (string)$text;
        $insStep->bind_param('iis', $id, $position, $textStr);
        $insStep->execute();
    }
    $insStep->close();

    foreach ([['recipe_tags','tag','tags'],
              ['recipe_diets','diet','diets'],
              ['recipe_supermarkets','supermarkt','supermarkts']] as [$table,$col,$key]) {
        $stmt = $conn->prepare("INSERT INTO $table (recipe_id, $col) VALUES (?, ?)");
        $seen = [];
        foreach (($d[$key] ?? []) as $val) {
            $val = (string)$val;
            if (isset($seen[$val])) continue;
            $seen[$val] = true;
            $stmt->bind_param('is', $id, $val);
            $stmt->execute();
        }
        $stmt->close();
    }
}

function nextSlug(mysqli $conn): string {
    $row = $conn->query("SELECT MAX(CAST(slug AS UNSIGNED)) AS m FROM recipes")->fetch_assoc();
    $next = max(1, ((int)$row['m']) + 1);
    return (string)$next;
}

function slugTaken(mysqli $conn, string $slug, ?int $excludeId): bool {
    $sql = 'SELECT id FROM recipes WHERE slug = ?';
    if ($excludeId !== null) $sql .= ' AND id <> ?';
    $stmt = $conn->prepare($sql);
    if ($excludeId !== null) $stmt->bind_param('si', $slug, $excludeId);
    else                     $stmt->bind_param('s', $slug);
    $stmt->execute();
    $taken = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $taken;
}
