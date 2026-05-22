<?php
declare(strict_types=1);
/**
 * NexaMake - NexaUI Controller Generator
 * Dipanggil oleh controllers.bat
 */
$basePath = dirname(__DIR__, 2);
$controllersPath = $basePath . DIRECTORY_SEPARATOR . 'controllers';

$name = $argv[1] ?? null;
$withTemplate = in_array('--template', $argv, true) || in_array('-t', $argv, true);

if (!$name) {
    echo "Usage: php NexaMake.php Admin/ProductController [--template]\n";
    exit(1);
}

$parts = explode('/', $name);
$className = array_pop($parts);
if (!preg_match('/Controller$/i', $className)) $className .= 'Controller';

// Shorthand: 1=Admin, 2=Api, 3=Frontend
$shortcuts = ['1' => 'Admin', '2' => 'Api', '3' => 'Frontend'];
if (!empty($parts[0]) && isset($shortcuts[$parts[0]])) {
    $parts[0] = $shortcuts[$parts[0]];
}

$namespaceParts = array_map('ucfirst', array_map('strtolower', $parts));
$namespace = 'App\\Controllers\\' . implode('\\', $namespaceParts);

$relativePath = implode(DIRECTORY_SEPARATOR, $parts);
$filePath = $controllersPath . DIRECTORY_SEPARATOR . $relativePath . DIRECTORY_SEPARATOR . $className . '.php';

$allowed = ['Admin', 'Api', 'Frontend'];
$first = $namespaceParts[0] ?? '';
if (!in_array($first, $allowed)) {
    echo "  [ERROR] Namespace harus Admin, Api, atau Frontend.\n";
    exit(1);
}

if (file_exists($filePath)) {
    echo "  [ERROR] File sudah ada: {$filePath}\n";
    exit(1);
}

$dir = dirname($filePath);
if (!is_dir($dir)) mkdir($dir, 0755, true);

$pageName = strtolower(preg_replace('/Controller$/', '', $className));
$pageTitle = ucfirst(str_replace(['-', '_'], ' ', $pageName));

$stubs = [
    'Admin' => "<?php\ndeclare(strict_types=1);\nnamespace {$namespace};\nuse App\System\NexaController;\n\n/**\n * {$className} - Admin Controller\n * URL: /{username}/{$pageName}\n * Template: templates/dashboard/{$pageName}/index.html\n */\nclass {$className} extends NexaController\n{\n    public function index(array \$params = []): void\n    {\n        \$username = \$params['username'] ?? \$this->getSession()->getUserSlug();\n        \$this->assignVars(['page_title' => '{$pageTitle}', 'username' => \$username]);\n    }\n}\n",
    'Api' => "<?php\ndeclare(strict_types=1);\nnamespace {$namespace};\nuse App\System\NexaController;\n\n/**\n * {$className} - API Controller\n * URL: /api/{$pageName}\n */\nclass {$className} extends NexaController\n{\n    public function index(array \$params = []): void\n    {\n        header('Content-Type: application/json; charset=UTF-8');\n        header('Access-Control-Allow-Origin: *');\n        header('Access-Control-Allow-Headers: *');\n        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');\n        \$response = ['success' => true, 'message' => 'API {$pageName} is working', 'timestamp' => date('Y-m-d H:i:s')];\n        echo json_encode(\$response, JSON_UNESCAPED_UNICODE);\n        exit;\n    }\n}\n",
    'Frontend' => "<?php\ndeclare(strict_types=1);\nnamespace {$namespace};\nuse App\System\NexaController;\n\n/**\n * {$className} - Frontend Controller\n * URL: /{$pageName}\n * Template: templates/theme/{$pageName}/index.html\n */\nclass {$className} extends NexaController\n{\n    public function index(array \$params = []): void\n    {\n        \$this->assignVars(['page_title' => '{$pageTitle}', 'base_url' => \$this->getBaseUrl()]);\n    }\n}\n",
];

$stub = $stubs[$first] ?? $stubs['Admin'];
file_put_contents($filePath, $stub);

$relPath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $filePath);
echo "  [OK] Controller: {$relPath}\n";
echo "       Namespace:  {$namespace}\n";
echo "       Class:     {$className}\n";

// Selalu buat folder template untuk Admin dan Frontend (Api tidak pakai template)
if ($first !== 'Api') {
    $layoutFolder = $first === 'Admin' ? 'dashboard' : 'theme';
    $templateDir = $basePath . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $layoutFolder . DIRECTORY_SEPARATOR . $pageName;
    $templateFile = $templateDir . DIRECTORY_SEPARATOR . 'index.html';
    if (!is_dir($templateDir)) mkdir($templateDir, 0755, true);
    if (!file_exists($templateFile)) {
        file_put_contents($templateFile, "<!-- {$className} -->\n<div class=\"container\">\n    <h1>{$pageTitle}</h1>\n</div>\n");
        echo "  [OK] Template:  templates/{$layoutFolder}/{$pageName}/index.html\n";
    }
}
