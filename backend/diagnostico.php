<?php
/**
 * Diagnóstico de instalación de CoreTT
 *
 * Abre en el navegador:
 *   https://tu-servidor/CoreTT/backend/diagnostico.php
 *
 * No muestra contraseñas. Solo indica qué está configurado y qué falla.
 */

require_once __DIR__ . '/config/env.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$envFile = corett_load_environment();

$checks = array();
$problems = array();
$warnings = array();

function addCheck(&$checks, $name, $ok, $detail) {
    $checks[] = array(
        'comprobacion' => $name,
        'estado' => $ok ? 'OK' : 'ERROR',
        'detalle' => $detail,
    );
}

// --- PHP y extensiones -----------------------------------------------------
addCheck($checks, 'Versión de PHP', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION);
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    $problems[] = 'Se requiere PHP 7.4 o superior.';
}

$pdoMysql = extension_loaded('pdo_mysql');
addCheck($checks, 'Extensión pdo_mysql', $pdoMysql, $pdoMysql ? 'Cargada' : 'No está instalada');
if (!$pdoMysql) {
    $problems[] = 'Instala la extensión pdo_mysql (en Debian/Ubuntu: apt install php-mysql).';
}

$mbstring = extension_loaded('mbstring');
addCheck($checks, 'Extensión mbstring', $mbstring, $mbstring ? 'Cargada' : 'No está instalada (recomendada)');
if (!$mbstring) {
    $warnings[] = 'mbstring no está instalada; algunos textos podrían cortarse mal.';
}

// --- Archivo .env ----------------------------------------------------------
$envPath = is_string($envFile) ? $envFile : '';
addCheck($checks, 'Archivo .env', $envPath !== '', $envPath !== '' ? $envPath : 'No encontrado');
if ($envPath === '') {
    $problems[] = 'No existe .env. Copia .env.example a .env en la raíz del proyecto y escribe los datos reales de MySQL.';
}

$config = array(
    'DB_HOST' => (string) corett_env_first(array('DB_HOST', 'MYSQL_HOST', 'DATABASE_HOST', 'DB_HOSTNAME'), 'localhost'),
    'DB_PORT' => (string) corett_env_first(array('DB_PORT', 'MYSQL_PORT', 'DATABASE_PORT'), '3306'),
    'DB_NAME' => (string) corett_env_first(array('DB_NAME', 'MYSQL_DATABASE', 'DATABASE_NAME', 'DB_DATABASE'), 'corett_db'),
    'DB_USER' => (string) corett_env_first(array('DB_USER', 'MYSQL_USER', 'DATABASE_USER', 'DB_USERNAME', 'MYSQL_USERNAME'), 'root'),
    'DB_PASS' => corett_env_first(array('DB_PASS', 'DB_PASSWORD', 'MYSQL_PASSWORD', 'DATABASE_PASSWORD', 'MYSQL_ROOT_PASSWORD'), '') !== '' ? '(definida)' : '(vacía)',
    'APP_DEBUG' => corett_debug_enabled() ? 'true' : 'false',
    'SECRET_KEY' => (string) corett_env_first(array('SECRET_KEY'), '') !== '' && corett_env_first(array('SECRET_KEY'), '') !== 'corett_secret_key_change_in_production'
        ? '(personalizada)'
        : '(valor por defecto: cámbiala)',
);

if ($config['SECRET_KEY'] === '(valor por defecto: cámbiala)') {
    $warnings[] = 'SECRET_KEY sigue con el valor de ejemplo. Cámbiala en .env antes de publicar.';
}

// --- Conexión a la base de datos ------------------------------------------
$db = null;
$dsn = 'mysql:host=' . $config['DB_HOST'] . ';port=' . $config['DB_PORT']
     . ';dbname=' . $config['DB_NAME'] . ';charset=' . (string) corett_env('DB_CHARSET', 'utf8mb4');

try {
    $db = new PDO($dsn, $config['DB_USER'], (string) corett_env_first(array('DB_PASS', 'DB_PASSWORD', 'MYSQL_PASSWORD', 'DATABASE_PASSWORD', 'MYSQL_ROOT_PASSWORD'), ''), array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));
    addCheck($checks, 'Conexión MySQL', true, 'Conectado a ' . $config['DB_NAME']);
} catch (Throwable $e) {
    addCheck($checks, 'Conexión MySQL', false, $e->getMessage());
    $problems[] = 'No se pudo conectar a MySQL. Revisa DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS en .env.';
    $problems[] = 'En phpMyAdmin, confirma el nombre exacto de la base de datos y que el usuario tenga permisos sobre ella.';
}

// --- Tablas y datos --------------------------------------------------------
$requiredTables = array('usuarios', 'juegos', 'parches_temporadas', 'guias', 'foro_posts', 'foro_comentarios', 'votos_guias', 'meta_stats');
$counts = array();

if ($db instanceof PDO) {
    try {
        $stmt = $db->query('SHOW TABLES');
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_values(array_diff($requiredTables, $tables));

        addCheck($checks, 'Tablas de CoreTT', empty($missing), empty($missing)
            ? 'Todas las tablas existen'
            : 'Faltan: ' . implode(', ', $missing));

        if (!empty($missing)) {
            $problems[] = 'Faltan tablas. Importa database/corett_completo.sql desde phpMyAdmin seleccionando la base de datos correcta.';
        }

        foreach (array('usuarios', 'juegos', 'guias', 'foro_posts') as $table) {
            if (in_array($table, $tables, true)) {
                $counts[$table] = (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
            }
        }

        if (isset($counts['usuarios']) && $counts['usuarios'] === 0) {
            $warnings[] = 'La tabla usuarios está vacía. Importa database/datos_iniciales.sql o usa database/corett_completo.sql.';
        }

        if (in_array('usuarios', $tables, true)) {
            $admin = $db->query("SELECT id, email FROM usuarios WHERE rol = 'admin' LIMIT 1")->fetch();
            addCheck($checks, 'Usuario administrador', (bool) $admin, $admin
                ? 'Existe: ' . $admin['email']
                : 'No hay ningún usuario con rol admin');
            if (!$admin) {
                $warnings[] = 'No hay administrador. Importa los datos iniciales (admin@corett.com / password) y cámbiale la contraseña.';
            }
        }

        // Permisos del usuario de MySQL
        try {
            $grantsRows = $db->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN);
            $grants = strtoupper(implode(' ', $grantsRows));
            $canWrite = strpos($grants, 'ALL PRIVILEGES') !== false
                || (strpos($grants, 'INSERT') !== false && strpos($grants, 'UPDATE') !== false && strpos($grants, 'DELETE') !== false);
            addCheck($checks, 'Permisos de escritura', $canWrite, $canWrite
                ? 'El usuario puede insertar/actualizar/eliminar'
                : 'El usuario parece ser de solo lectura (SHOW GRANTS)');
            if (!$canWrite) {
                $problems[] = 'El usuario de MySQL no tiene permisos INSERT/UPDATE/DELETE. Por eso los registros no se guardan.';
            }
        } catch (Throwable $e) {
            $warnings[] = 'No se pudieron comprobar los permisos con SHOW GRANTS: ' . $e->getMessage();
        }
    } catch (Throwable $e) {
        addCheck($checks, 'Revisión de tablas', false, $e->getMessage());
        $problems[] = 'No se pudieron leer las tablas: ' . $e->getMessage();
    }
}

// --- Resultado -------------------------------------------------------------
$result = array(
    'estado' => empty($problems) ? 'OK' : 'CON_ERRORES',
    'problemas' => $problems,
    'advertencias' => $warnings,
    'configuracion' => $config,
    'comprobaciones' => $checks,
    'filas' => $counts,
    'siguiente_paso' => empty($problems)
        ? 'La configuración parece correcta. Prueba registrar un usuario en la web.'
        : 'Corrige los problemas indicados y vuelve a cargar esta página.',
);

$format = isset($_GET['format']) ? strtolower((string) $_GET['format']) : 'json';

if ($format === 'html') {
    header('Content-Type: text/html; charset=UTF-8');
    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico CoreTT</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; background: #f6f7fb; color: #1c2333; }
        h1 { margin-top: 0; }
        .card { background: #fff; border-radius: 10px; padding: 1.2rem; margin-bottom: 1rem; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
        .ok { color: #0a7a35; font-weight: 700; }
        .error { color: #b42318; font-weight: 700; }
        .warn { color: #9a6700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #e7e9f0; vertical-align: top; }
        code { background: #eef0f6; padding: .1rem .3rem; border-radius: 4px; }
        ul { margin: .4rem 0; }
    </style>
</head>
<body>
    <h1>Diagnóstico de CoreTT</h1>
    <div class="card">
        <p>Estado general:
            <span class="<?php echo $result['estado'] === 'OK' ? 'ok' : 'error'; ?>">
                <?php echo $e($result['estado']); ?>
            </span>
        </p>
        <p><?php echo $e($result['siguiente_paso']); ?></p>
    </div>

    <?php if (!empty($problems)): ?>
    <div class="card">
        <h2 class="error">Problemas</h2>
        <ul><?php foreach ($problems as $problem): ?><li><?php echo $e($problem); ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($warnings)): ?>
    <div class="card">
        <h2 class="warn">Advertencias</h2>
        <ul><?php foreach ($warnings as $warning): ?><li><?php echo $e($warning); ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Comprobaciones</h2>
        <table>
            <thead><tr><th>Comprobación</th><th>Estado</th><th>Detalle</th></tr></thead>
            <tbody>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <td><?php echo $e($check['comprobacion']); ?></td>
                    <td class="<?php echo $check['estado'] === 'OK' ? 'ok' : 'error'; ?>"><?php echo $e($check['estado']); ?></td>
                    <td><?php echo $e($check['detalle']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Configuración detectada</h2>
        <table>
            <tbody>
            <?php foreach ($config as $key => $value): ?>
                <tr><th><?php echo $e($key); ?></th><td><code><?php echo $e($value); ?></code></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p>Para ver esta misma información en JSON añade <code>?format=json</code>.</p>
    </div>
</body>
</html>
    <?php
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
