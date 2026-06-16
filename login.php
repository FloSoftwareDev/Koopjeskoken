<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

startSecureSession();

// Al ingelogd? Direct door naar de app.
if (!empty($_SESSION['user_id'])) {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($base === '' || $base === '.' || $base === '/' || $base === '\\') $base = '';
    header('Location: ' . $base . '/index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('login_form_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 60);

    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $rememberMe = !empty($_POST['remember_me']);

    if ($rememberMe) {
        // Sessiecookie hernieuwen met langere levensduur.
        session_write_close();
        session_set_cookie_params([
            'lifetime' => 30 * 24 * 60 * 60,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    $usernameOk = hash_equals(AUTH_USERNAME, $username);
    $passwordOk = password_verify($password, AUTH_PASSWORD_HASH);

    if ($usernameOk && $passwordOk) {
        session_regenerate_id(true);
        $_SESSION['user_id']  = 1;
        $_SESSION['role']     = AUTH_ROLE;
        $_SESSION['username'] = AUTH_USERNAME;
        generateCsrfToken();

        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        if ($base === '' || $base === '.' || $base === '/' || $base === '\\') $base = '';
        header('Location: ' . $base . '/index.php');
        exit;
    }

    $error = 'Onjuiste gebruikersnaam of wachtwoord.';
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — KoopjesKoken</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --orange: #E8500A;
    --orange-pale: #FFE8D7;
    --ink: #1a1a1a;
    --ink-soft: #666;
    --border: #e5e5e5;
    --bg: #faf7f2;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 2rem 1rem;
  }
  .login-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    padding: 2.5rem;
    width: 100%;
    max-width: 400px;
  }
  .login-brand {
    font-family: 'Playfair Display', serif;
    font-weight: 900;
    font-size: 1.75rem;
    text-align: center;
    margin-bottom: 0.5rem;
  }
  .login-brand span { color: var(--orange); }
  .login-sub {
    text-align: center;
    color: var(--ink-soft);
    font-size: 0.9rem;
    margin-bottom: 2rem;
  }
  label {
    display: block;
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.4rem;
    margin-top: 1rem;
  }
  input[type="text"], input[type="password"] {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font: inherit;
  }
  input[type="text"]:focus, input[type="password"]:focus {
    outline: none;
    border-color: var(--orange);
    box-shadow: 0 0 0 3px var(--orange-pale);
  }
  .controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    font-size: 0.85rem;
    color: var(--ink-soft);
  }
  button {
    width: 100%;
    background: var(--orange);
    color: white;
    border: none;
    padding: 0.85rem;
    border-radius: 8px;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
    margin-top: 1.5rem;
  }
  button:hover { background: #c84408; }
  .error {
    background: #fee;
    color: #c00;
    border: 1px solid #fcc;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    margin-bottom: 1rem;
  }
</style>
</head>
<body>
  <div class="login-card">
    <div class="login-brand">Koopjes<span>Koken</span></div>
    <div class="login-sub">Log in om door te gaan</div>

    <?php if ($error): ?>
      <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
      <label for="username">Gebruikersnaam</label>
      <input type="text" id="username" name="username" autocomplete="username" required autofocus>

      <label for="password">Wachtwoord</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>

      <div class="controls">
        <input type="checkbox" id="remember_me" name="remember_me" value="1">
        <label for="remember_me" style="margin: 0; font-weight: normal;">Onthoud mij</label>
      </div>

      <button type="submit">Inloggen</button>
    </form>
  </div>
</body>
</html>
