<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
session_name('smartec_session');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();

function reply(mixed $data, int $status = 200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function fail(string $message, int $status = 400): never { reply(['error' => $message], $status); }
set_exception_handler(function (Throwable $error): void { error_log('Smartec API: ' . $error->getMessage()); reply(['error' => 'Erro interno. Tente novamente.'], 500); });
function input(): array { $raw = file_get_contents('php://input'); if ($raw === '') return $_POST; $data = json_decode($raw, true); return is_array($data) ? $data : fail('JSON inválido.'); }
function field(array $data, string $name, int $max = 0): string { $value = trim((string)($data[$name] ?? '')); if ($max && mb_strlen($value) > $max) fail("Campo $name excede o limite."); return $value; }
function integer(array $data, string $name): int { $value = filter_var($data[$name] ?? null, FILTER_VALIDATE_INT); if ($value === false || $value === null || $value < 1) fail("Campo $name inválido."); return (int)$value; }
function config(): array { $files = [dirname(__DIR__, 5) . '/smartec-private/config.php', __DIR__ . '/config.php']; foreach ($files as $file) { if (!is_file($file)) continue; $value = require $file; return is_array($value) ? $value : fail('Configuração inválida.', 500); } fail('API ainda não configurada.', 503); }
function db(): PDO { static $pdo; if ($pdo) return $pdo; $c = config()['db'] ?? []; try { $pdo = new PDO("mysql:host={$c['host']};dbname={$c['database']};charset=" . ($c['charset'] ?? 'utf8mb4'), $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]); return $pdo; } catch (PDOException) { fail('Não foi possível conectar ao banco.', 503); } }
function user(): array { if (empty($_SESSION['user'])) fail('Não autenticado.', 401); return $_SESSION['user']; }
function csrfToken(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verifyCsrf(): void { $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''); if (!$provided || !hash_equals(csrfToken(), $provided)) fail('Sessão inválida. Atualize a página e tente novamente.', 403); }
function allow(array $roles, array $current): void { if (!in_array($current['role'], $roles, true)) fail('Você não tem permissão para esta ação.', 403); }
function execute(string $sql, array $values): int { $st = db()->prepare($sql); $st->execute($values); return (int)db()->lastInsertId(); }
function one(string $table, int $id): array { $st = db()->prepare("SELECT * FROM `$table` WHERE id = ?"); $st->execute([$id]); return $st->fetch() ?: fail('Registro não encontrado.', 404); }

$resource = $_GET['resource'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($resource === 'health') { db(); reply(['status' => 'ok']); }
if ($resource === 'auth/login' && $method === 'POST') {
    $data = input(); $email = field($data, 'email', 120); $password = (string)($data['password'] ?? '');
    $st = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1'); $st->execute([$email]); $found = $st->fetch();
    if (!$found || !password_verify($password, $found['password_hash'])) fail('E-mail ou senha inválidos.', 401);
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$found['id'], 'name' => $found['name'], 'email' => $found['email'], 'role' => $found['role']];
    reply(['user' => $_SESSION['user'], 'csrf' => csrfToken()]);
}
if ($resource === 'auth/logout' && $method === 'POST') { verifyCsrf(); $_SESSION = []; session_destroy(); reply(['ok' => true]); }
if ($resource === 'auth/me' && $method === 'GET') reply(['user' => user(), 'csrf' => csrfToken()]);
if ($resource === 'auth/setup' && $method === 'POST') {
    $settings = config(); $count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(); if ($count) fail('Administrador já configurado.', 409);
    if (!hash_equals((string)($settings['setup_key'] ?? ''), (string)($_SERVER['HTTP_X_SETUP_KEY'] ?? ''))) fail('Chave de configuração inválida.', 403);
    $data = input(); $name = field($data, 'name', 120); $email = field($data, 'email', 120); $password = (string)($data['password'] ?? ''); if (mb_strlen($password) < 10) fail('A senha precisa ter ao menos 10 caracteres.');
    $id = execute('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, "admin")', [$name, $email, password_hash($password, PASSWORD_DEFAULT)]); reply(['id' => $id], 201);
}

$currentUser = user();
if ($method !== 'GET') verifyCsrf();
if ($resource === 'users') {
    allow(['admin'], $currentUser);
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($method === 'GET') {
        if ($id) { $st = db()->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = ?'); $st->execute([$id]); reply($st->fetch() ?: fail('Usuário não encontrado.', 404)); }
        reply(db()->query('SELECT id, name, email, role, created_at FROM users ORDER BY name ASC')->fetchAll());
    }
    if ($method === 'POST') {
        $data = input(); $name = field($data, 'name', 120); $email = field($data, 'email', 120); $password = (string)($data['password'] ?? ''); $role = field($data, 'role', 20);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('E-mail inválido.'); if (mb_strlen($password) < 10) fail('A senha precisa ter ao menos 10 caracteres.'); if (!in_array($role, ['admin','tecnico','financeiro'], true)) fail('Perfil inválido.');
        $id = execute('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        $st = db()->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = ?'); $st->execute([$id]); reply($st->fetch(), 201);
    }
    if ($method === 'PATCH') {
        if (!$id) fail('ID obrigatório.'); $data = input(); $set = []; $values = [];
        foreach (['name','email','role'] as $column) if (array_key_exists($column, $data)) { $value = field($data, $column, in_array($column, ['name','email'], true) ? 120 : 20); if ($column === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) fail('E-mail inválido.'); if ($column === 'role' && !in_array($value, ['admin','tecnico','financeiro'], true)) fail('Perfil inválido.'); $set[] = "`$column` = ?"; $values[] = $value; }
        if (!empty($data['password'])) { if (mb_strlen((string)$data['password']) < 10) fail('A senha precisa ter ao menos 10 caracteres.'); $set[] = 'password_hash = ?'; $values[] = password_hash((string)$data['password'], PASSWORD_DEFAULT); }
        if (!$set) fail('Nenhum campo para atualizar.'); $values[] = $id; $st = db()->prepare('UPDATE users SET ' . implode(',', $set) . ' WHERE id = ?'); $st->execute($values); reply(['ok' => true]);
    }
    if ($method === 'DELETE') { if (!$id) fail('ID obrigatório.'); if ($id === (int)$currentUser['id']) fail('Você não pode excluir seu próprio acesso.', 409); $st = db()->prepare('DELETE FROM users WHERE id = ?'); $st->execute([$id]); reply(['ok' => true]); }
    fail('Método não permitido.', 405);
}
$specs = [
    'clients' => ['table' => 'clients', 'columns' => ['name', 'document', 'phone', 'email', 'address'], 'required' => ['name'], 'max' => ['name'=>120,'document'=>18,'phone'=>30,'email'=>120,'address'=>200]],
    'services' => ['table' => 'services', 'columns' => ['name', 'description', 'reference_price'], 'required' => ['name'], 'max' => ['name'=>120,'description'=>500]],
    'tools' => ['table' => 'tools', 'columns' => ['name', 'asset_code', 'status', 'notes'], 'required' => ['name'], 'max' => ['name'=>120,'asset_code'=>60,'status'=>30,'notes'=>500]],
    'financial-entries' => ['table' => 'financial_entries', 'columns' => ['description', 'amount', 'due_date', 'status'], 'required' => ['description','amount','due_date'], 'max' => ['description'=>150,'status'=>20]],
    'orders' => ['table' => 'service_orders', 'columns' => ['client_id', 'type', 'status', 'service_date', 'description', 'notes', 'amount', 'photo_path'], 'required' => ['client_id','service_date','description'], 'max' => ['type'=>30,'status'=>30,'description'=>10000,'notes'=>10000,'photo_path'=>255]],
];
if (!isset($specs[$resource])) fail('Rota não encontrada.', 404);
$spec = $specs[$resource]; $table = $spec['table']; $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($resource === 'financial-entries') allow(['admin', 'financeiro'], $currentUser);
if ($method === 'GET') { if ($id) reply(one($table, $id)); $order = $resource === 'orders' ? 'created_at DESC' : 'name ASC'; if ($resource === 'financial-entries') $order = 'due_date ASC'; reply(db()->query("SELECT * FROM `$table` ORDER BY $order")->fetchAll()); }
if ($method === 'DELETE') { allow(['admin'], $currentUser); if (!$id) fail('ID obrigatório.'); one($table, $id); $st = db()->prepare("DELETE FROM `$table` WHERE id = ?"); $st->execute([$id]); reply(['ok' => true]); }
if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) fail('Método não permitido.', 405);
$data = input(); foreach ($spec['required'] as $required) if ($method === 'POST' && field($data, $required) === '') fail("Campo $required é obrigatório.");
$values = []; foreach ($spec['columns'] as $column) { if (!array_key_exists($column, $data)) continue; $value = field($data, $column, $spec['max'][$column] ?? 0); $values[$column] = $value; }
if ($resource === 'orders' && $method === 'POST') { $values['number'] = (int)db()->query('SELECT COALESCE(MAX(number), 0) + 1 FROM service_orders')->fetchColumn(); }
if ($method === 'POST') { $columns = array_keys($values); $id = execute('INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')', array_values($values)); reply(one($table, $id), 201); }
if (!$id) fail('ID obrigatório.'); one($table, $id); if (!$values) fail('Nenhum campo para atualizar.'); $set = implode(',', array_map(fn($column) => "`$column` = ?", array_keys($values))); $st = db()->prepare("UPDATE `$table` SET $set WHERE id = ?"); $st->execute([...array_values($values), $id]); reply(one($table, $id));
