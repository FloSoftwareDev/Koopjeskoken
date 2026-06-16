<?php
require_once __DIR__ . '/helpers.php';

setCorsHeaders();
setJsonHeaders();
handleOptions();

http_response_code(403);
echo json_encode(['error' => 'Registratie is uitgeschakeld.']);
