<?php
require_once __DIR__ . '/helpers.php';

setCorsHeaders();
setJsonHeaders();
handleOptions();
startSecureSession();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Paid API call — rate-limited per session to keep cost predictable
rateLimit('ai_recipe_' . ($_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 3600);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo json_encode(['error' => 'AI dependencies not installed. Run "composer install".']);
    exit;
}
require_once $autoload;

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
$apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : (getenv('ANTHROPIC_API_KEY') ?: '');
if (!$apiKey || $apiKey === 'sk-ant-...') {
    http_response_code(500);
    echo json_encode(['error' => 'AI service not configured (missing ANTHROPIC_API_KEY)']);
    exit;
}

$data = getJsonBody();
if (empty($data['deals']) || !is_array($data['deals'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing deals array']);
    exit;
}

// Cap input to keep cost predictable
$deals = array_slice($data['deals'], 0, 12);

// Stable cache key — same deal set returns the same recipe (saves a paid call)
$keyParts = array_map(fn($d) => ($d['naam'] ?? '') . '|' . ($d['sm'] ?? ''), $deals);
sort($keyParts);
$cacheKey = hash('sha256', implode('||', $keyParts));

$cacheDir = __DIR__ . '/../data/ai-cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0700, true);
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';

// Serve from cache if <24h old
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    echo file_get_contents($cacheFile);
    exit;
}

// Build the deal list for the prompt
$dealsText = '';
foreach ($deals as $d) {
    $naam  = trim($d['naam'] ?? '');
    $prijs = $d['prijsNu'] ?? null;
    $sm    = $d['smLabel'] ?? ($d['sm'] ?? '');
    if (!$naam || !is_numeric($prijs) || $prijs <= 0) continue;
    $dealsText .= sprintf("- %s @ €%.2f (%s)\n", $naam, $prijs, $sm);
}
if (!$dealsText) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid deals']);
    exit;
}

$systemPrompt = <<<EOT
Je bent een Nederlandse kok die budgetrecepten verzint op basis van actuele supermarktaanbiedingen.
Maak realistische, lekkere maaltijden voor Nederlandse huishoudens. Gebruik 2-4 ingrediënten uit de
gegeven aanbiedingen als hoofdingrediënten en vul aan met betaalbare basisingrediënten (pasta, rijst,
ui, knoflook, olie, zout, peper, etc.). Het recept moet voor 2-4 personen zijn en onder €5 per persoon
kosten. Stappen moeten duidelijk en uitvoerbaar zijn voor een beginnende kok.
EOT;

$userPrompt = "Verzin één recept op basis van deze huidige aanbiedingen:\n\n" . $dealsText;

$schema = [
    'type' => 'object',
    'properties' => [
        'name'           => ['type' => 'string',  'description' => 'Naam van het recept'],
        'description'    => ['type' => 'string',  'description' => 'Korte omschrijving van 1-2 zinnen'],
        'allergenen'     => ['type' => 'string',  'description' => 'De allergenen die in dit recept voorkomen'],
        'time'           => ['type' => 'integer', 'description' => 'Bereidingstijd in minuten'],
        'persons'        => ['type' => 'integer', 'description' => 'Aantal personen'],
        'difficulty'     => ['type' => 'string',  'enum' => ['Makkelijk', 'Gemiddeld', 'Moeilijk']],
        'pricePerPerson' => ['type' => 'number',  'description' => 'Geschatte prijs per persoon in euro'],
        'ingredients' => [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'properties' => [
                    'name'        => ['type' => 'string'],
                    'quantity'    => ['type' => 'string', 'description' => 'Hoeveelheid, bijv. "500g" of "2 stuks"'],
                    'fromDeal'    => ['type' => 'boolean', 'description' => 'True als dit ingrediënt uit de aanbiedingen komt'],
                    'supermarket' => ['type' => 'string', 'description' => 'Supermarkt naam of leeg'],
                ],
                'required' => ['name', 'quantity', 'fromDeal', 'supermarket'],
                'additionalProperties' => false,
            ],
        ],
        'steps' => [
            'type'  => 'array',
            'items' => ['type' => 'string'],
            'description' => '5-10 duidelijke kookstappen',
        ],
        'tip' => ['type' => 'string', 'description' => 'Optionele kook-tip (mag leeg zijn)'],
    ],
    'required' => ['name', 'description', 'allergenen', 'time', 'persons', 'difficulty', 'pricePerPerson', 'ingredients', 'steps', 'tip'],
    'additionalProperties' => false,
];

try {
    $client = new \Anthropic\Client(apiKey: $apiKey);

    $response = $client->messages->create(
        model: 'claude-opus-4-7',
        maxTokens: 8000,
        system: [
            ['type' => 'text', 'text' => $systemPrompt, 'cacheControl' => ['type' => 'ephemeral']],
        ],
        messages: [
            ['role' => 'user', 'content' => $userPrompt],
        ],
        thinking: ['type' => 'adaptive'],
        outputConfig: [
            'format' => ['type' => 'json_schema', 'schema' => $schema],
        ],
    );

    // Pull the first text block — guard against thinking blocks coming first
    $recipeJson = null;
    foreach ($response->content as $block) {
        if ($block->type === 'text') {
            $recipeJson = $block->text;
            break;
        }
    }
    if (!$recipeJson) {
        throw new Exception('Empty response from AI');
    }

    $recipe = json_decode($recipeJson, true);
    if (!is_array($recipe)) {
        throw new Exception('Invalid JSON from AI: ' . substr($recipeJson, 0, 200));
    }

    $result = ['success' => true, 'recipe' => $recipe];
    file_put_contents($cacheFile, json_encode($result), LOCK_EX);
    echo json_encode($result);

} catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
    error_log('Anthropic API error: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode(['error' => 'AI service error', 'detail' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log('AI recipe error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate recipe', 'detail' => $e->getMessage()]);
}
