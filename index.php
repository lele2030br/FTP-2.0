<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

//======================================================================
// CLASSE DO GERENCIADOR DE FTP
//======================================================================
class FtpManager {
    private $conn;
    public $basePath;
    public function __construct($host, $port, $user, $pass, $use_ssl = false) {
        if ($use_ssl) {
            $this->conn = @ftp_ssl_connect($host, $port, 20);
        } else {
            $this->conn = @ftp_connect($host, $port, 20);
        }
        if (!is_resource($this->conn)) {
            throw new Exception("Não foi possível conectar ao servidor FTP: " . htmlspecialchars($host) . ". Verifique o host e a porta.");
        }
        if (!@ftp_login($this->conn, $user, $pass)) {
            @ftp_close($this->conn);
            throw new Exception("Login FTP falhou: Usuário ou senha incorretos.");
        }
        @ftp_pasv($this->conn, true);
        $this->basePath = $this->getCurrentPath();
    }
    public function listContents($path) { if (!is_resource($this->conn)) throw new Exception("A conexão FTP foi perdida."); if (@ftp_chdir($this->conn, $path) === false) throw new Exception("Não foi possível acessar o diretório: " . htmlspecialchars($path)); $raw_list = @ftp_rawlist($this->conn, "."); $contents = ['dirs' => [], 'files' => []]; if (is_array($raw_list)) { foreach ($raw_list as $item) { $info = preg_split("/\s+/", $item, 9); if (count($info) < 9 || in_array($info[8], [".", ".."])) continue; $is_dir = $info[0][0] === 'd'; if ($is_dir) $contents['dirs'][] = ['name' => $info[8], 'perms' => $info[0]]; else $contents['files'][] = ['name' => $info[8], 'size' => (int)$info[4], 'perms' => $info[0]]; } } usort($contents['dirs'], fn($a, $b) => strcmp($a['name'], $b['name'])); usort($contents['files'], fn($a, $b) => strcmp($a['name'], $b['name'])); return $contents; }
    public function getCurrentPath() { return ftp_pwd($this->conn); }
    public function changeDir($dir) { return @ftp_chdir($this->conn, $dir); }
    public function deleteFile($file) { return @ftp_delete($this->conn, $file); }
    public function deleteDir($dir) { return @ftp_rmdir($this->conn, $dir); }
    public function createDir($name) { return @ftp_mkdir($this->conn, $name); }
    public function rename($from, $to) { return @ftp_rename($this->conn, $from, $to); }
    public function setPermissions($path, $mode) { return @ftp_chmod($this->conn, octdec($mode), $path); }
    public function uploadFile($source_path, $remote_name) { return @ftp_put($this->conn, $remote_name, $source_path, FTP_BINARY); }
    public function getFileContents($file) { $handle = fopen('php://temp', 'r+'); if (@ftp_fget($this->conn, $handle, $file, FTP_BINARY, 0)) { rewind($handle); return stream_get_contents($handle); } return false; }
    public function updateFileContents($file, $content) { $handle = fopen('php://temp', 'r+'); fwrite($handle, $content); rewind($handle); return @ftp_fput($this->conn, $file, $handle, FTP_BINARY, 0); }
    public function downloadFile($local_path, $remote_path) { return @ftp_get($this->conn, $local_path, $remote_path, FTP_BINARY); }
    public function close() { if ($this->conn && is_resource($this->conn)) @ftp_close($this->conn); }
}

//======================================================================
// FUNÇÕES AUXILIARES GLOBAIS
//======================================================================
function format_size($size) { if ($size <= 0) return '0 B'; $units = ['B', 'KB', 'MB', 'GB', 'TB']; $i = floor(log($size, 1024)); return round($size / (1024 ** $i), 2) . ' ' . $units[$i]; }
function get_file_icon($filename) { $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); switch ($ext) { case 'png': case 'jpg': case 'jpeg': case 'gif': case 'bmp': case 'svg': return '🖼️'; case 'pdf': return '📕'; case 'zip': case 'rar': case '7z': case 'gz': return '📦'; case 'doc': case 'docx': return '📄'; case 'xls': case 'xlsx': return '📊'; case 'mp3': case 'wav': return '🎵'; case 'mp4': case 'mov': case 'avi': return '🎬'; case 'txt': case 'md': return '📝'; case 'php': case 'js': case 'html': case 'css': return '💻'; default: return '❔'; } }

//======================================================================
// LÓGICA DO CONTROLADOR
//======================================================================
$ftpManager = null;
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

// Tenta restabelecer a conexão a partir da sessão
if (isset($_SESSION['ftp_creds'])) {
    try {
        $creds = $_SESSION['ftp_creds'];
        $ftpManager = new FtpManager($creds['host'], $creds['port'], $creds['user'], $creds['pass'], $creds['ssl']);
        if (isset($_SESSION['ftp_path'])) {
            $ftpManager->changeDir($_SESSION['ftp_path']);
        } else {
            $_SESSION['ftp_path'] = $ftpManager->basePath;
        }
    } catch (Exception $e) {
        // Se a reconexão falhar, destrói a sessão e força o logout.
        $ftpManager = null; // Garante que o gerenciador não será usado.
        $_SESSION = [];
        session_destroy();
        $error_message = "Sua sessão expirou ou a conexão falhou: " . $e->getMessage() . " Por favor, faça login novamente.";
    }
}

// Lógica de Login (quando um novo formulário é enviado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ftp_host'])) {
    try {
        $use_ssl = isset($_POST['ftp_ssl']);
        $ftpManager = new FtpManager($_POST['ftp_host'], (int)($_POST['ftp_port'] ?: 21), $_POST['ftp_user'], $_POST['ftp_pass'], $use_ssl);
        $_SESSION['ftp_creds'] = ['host' => $_POST['ftp_host'], 'port' => (int)($_POST['ftp_port'] ?: 21), 'user' => $_POST['ftp_user'], 'pass' => $_POST['ftp_pass'], 'ssl' => $use_ssl];
        $_SESSION['ftp_path'] = $ftpManager->basePath;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $ftpManager = null;
        $error_message = $e->getMessage();
    }
}

// Processa ações APENAS se a conexão estiver ativa
if ($ftpManager) {
    $action = $_REQUEST['action'] ?? '';
    if ($action && $action !== 'download' && $action !== 'get_content') { // Valida CSRF para ações que modificam dados
        $token_sent = $_REQUEST['csrf_token'] ?? '';
        if (empty($token_sent) || !hash_equals($_SESSION['csrf_token'], $token_sent)) {
            $_SESSION['error_message'] = "Erro de segurança: Token CSRF inválido ou expirado. A ação foi bloqueada.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    
    try {
        $current_path = $_SESSION['ftp_path'];
        switch ($action) {
            case 'logout': session_destroy(); header('Location: ' . $_SERVER['PHP_SELF']); exit;
            case 'chdir':
                $dir_to_change = $_GET['dir'];
                if ($dir_to_change === '..') {
                    $new_path = dirname($current_path);
                    if (strlen($new_path) < strlen($ftpManager->basePath)) $new_path = $ftpManager->basePath;
                } else { $new_path = $dir_to_change; }
                if ($ftpManager->changeDir($new_path)) $_SESSION['ftp_path'] = $ftpManager->getCurrentPath(); else throw new Exception("Não foi possível mudar para o diretório.");
                break;
            case 'delete_file': if ($ftpManager->deleteFile($current_path . '/' . $_GET['file'])) $_SESSION['success_message'] = "Arquivo excluído."; else throw new Exception("Erro ao excluir o arquivo."); break;
            case 'delete_dir': if ($ftpManager->deleteDir($current_path . '/' . $_GET['dir'])) $_SESSION['success_message'] = "Diretório excluído."; else throw new Exception("Erro ao excluir diretório (pode não estar vazio)."); break;
            case 'upload': if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) { if ($ftpManager->uploadFile($_FILES['file_upload']['tmp_name'], $current_path . '/' . basename($_FILES['file_upload']['name']))) $_SESSION['success_message'] = "Arquivo enviado."; else throw new Exception("Erro ao enviar o arquivo."); } break;
            case 'create_dir': if (!empty($_POST['dir_name'])) { if ($ftpManager->createDir($current_path . '/' . $_POST['dir_name'])) $_SESSION['success_message'] = "Diretório criado."; else throw new Exception("Erro ao criar diretório."); } break;
            case 'rename': if (!empty($_POST['old_name']) && !empty($_POST['new_name'])) { if ($ftpManager->rename($current_path . '/' . $_POST['old_name'], $current_path . '/' . $_POST['new_name'])) $_SESSION['success_message'] = "Item renomeado."; else throw new Exception("Erro ao renomear."); } break;
            case 'chmod': if (!empty($_POST['item_name']) && !empty($_POST['permissions'])) { if ($ftpManager->setPermissions($current_path . '/' . $_POST['item_name'], $_POST['permissions'])) $_SESSION['success_message'] = "Permissões atualizadas."; else throw new Exception("Erro ao alterar permissões."); } break;
            case 'edit': if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['file_name']) && isset($_POST['file_content'])) { if ($ftpManager->updateFileContents($current_path . '/' . $_POST['file_name'], $_POST['file_content'])) $_SESSION['success_message'] = "Arquivo salvo."; else throw new Exception("Erro ao salvar arquivo."); } break;
            case 'download': header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="' . basename($_GET['file']) . '"'); $ftpManager->downloadFile('php://output', $current_path . '/' . $_GET['file']); exit;
            case 'get_content': header('Content-Type: text/plain; charset=utf-8'); echo $ftpManager->getFileContents($current_path . '/' . $_GET['file']) ?: 'ERRO: Não foi possível ler o arquivo.'; exit;
        }
        if ($action && $action !== 'download' && $action !== 'get_content') { header('Location: ' . $_SERVER['PHP_SELF']); exit; }
    } catch (Exception $e) { $_SESSION['error_message'] = $e->getMessage(); header('Location: ' . $_SERVER['PHP_SELF']); exit; }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de FTP</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; background-color: #f4f4f4; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #0056b3; }
        .login-form { display: flex; flex-direction: column; gap: 10px; max-width: 400px; margin: 20px auto; }
        input[type="text"], input[type="password"], input[type="number"] { padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1em; }
        button, input[type="submit"] { font-size: 1em; padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        button:hover, input[type="submit"]:hover { background-color: #0056b3; }
        .file-manager { margin-top: 20px; overflow-x: auto; }
        .path-bar { background-color: #e9ecef; padding: 10px; border-radius: 4px; margin-bottom: 15px; word-wrap: break-word; }
        .breadcrumbs a { color: #007bff; text-decoration: none; } .breadcrumbs a:hover { text-decoration: underline; } .breadcrumbs span { margin: 0 5px; color: #6c757d; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #ddd; white-space: nowrap;}
        th { background-color: #f8f9fa; }
        tr:hover { background-color: #f1f1f1; }
        a { color: #007bff; text-decoration: none; }
        .action-btn { background: none; border: none; color: #007bff; cursor: pointer; padding: 0; font-size: 1em; margin-right: 10px; font-family: inherit; }
        .action-btn:hover { text-decoration: underline; } .delete-btn { color: #dc3545; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid; }
        .error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .forms-container { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .form-box { flex: 1; min-width: 300px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
        .modal-content textarea { width: 100%; height: 300px; font-family: monospace; box-sizing: border-box; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 20px; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gerenciador de FTP</h1>
        <?php if (!$ftpManager): ?>
            <h2>Conectar ao Servidor FTP</h2>
            <?php if ($error_message) echo '<p class="message error">' . htmlspecialchars($error_message) . '</p>'; ?>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="login-form">
                <input type="text" name="ftp_host" placeholder="Servidor FTP" required>
                <input type="text" name="ftp_user" placeholder="Usuário" required>
                <input type="password" name="ftp_pass" placeholder="Senha" required>
                <input type="number" name="ftp_port" placeholder="Porta (padrão 21)">
                <label><input type="checkbox" name="ftp_ssl"> Usar conexão segura (FTPS/SSL)</label>
                <button type="submit">Conectar</button>
            </form>
        <?php else: $csrf_token = $_SESSION['csrf_token']; $current_path = $_SESSION['ftp_path']; $contents = $ftpManager->listContents($current_path); ?>
            <div class="header-bar">
                <p>Conectado a: <strong><?php echo htmlspecialchars($_SESSION['ftp_creds']['host']); ?></strong></p>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><button type="submit">Logout</button></form>
            </div>
            <?php if ($error_message) echo '<p class="message error">' . htmlspecialchars($error_message) . '</p>'; ?>
            <?php if ($success_message) echo '<p class="message success">' . htmlspecialchars($success_message) . '</p>'; ?>
            <div class="path-bar">
                <div class="breadcrumbs">
                    <?php $path_parts = explode('/', trim(str_replace($ftpManager->basePath, '', $current_path), '/')); echo '<a href="?action=chdir&dir=' . urlencode($ftpManager->basePath) . '&csrf_token=' . $csrf_token . '">🏠 Raiz</a>'; $built_path = $ftpManager->basePath; foreach ($path_parts as $part) { if (empty($part)) continue; $built_path = rtrim($built_path, '/') . '/' . $part; echo '<span>&gt;</span> <a href="?action=chdir&dir=' . urlencode($built_path) . '&csrf_token=' . $csrf_token . '">' . htmlspecialchars($part) . '</a>'; } ?>
                </div>
            </div>
            <div class="file-manager">
                <table>
                    <thead><tr><th>Nome</th><th>Tamanho</th><th>Permissões</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php if ($current_path !== $ftpManager->basePath && $current_path !== '/'): ?><tr><td colspan="4"><a href="?action=chdir&dir=..&csrf_token=<?php echo $csrf_token; ?>">⬆️ .. (Voltar)</a></td></tr><?php endif; ?>
                        <?php foreach ($contents['dirs'] as $dir): ?>
                        <tr>
                            <td><a href="?action=chdir&dir=<?php echo urlencode($current_path . '/' . $dir['name']); ?>&csrf_token=<?php echo $csrf_token; ?>">📁 <?php echo htmlspecialchars($dir['name']); ?></a></td><td>Diretório</td><td><?php echo htmlspecialchars($dir['perms']); ?></td>
                            <td><button class="action-btn" onclick="showRenameModal('<?php echo htmlspecialchars($dir['name']); ?>')">Renomear</button><button class="action-btn" onclick="showChmodModal('<?php echo htmlspecialchars($dir['name']); ?>', '<?php echo htmlspecialchars($dir['perms']); ?>')">Permissões</button><a href="?action=delete_dir&dir=<?php echo urlencode($dir['name']); ?>&csrf_token=<?php echo $csrf_token; ?>" onclick="return confirm('Tem certeza?');" class="action-btn delete-btn">Excluir</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php foreach ($contents['files'] as $file): ?>
                        <tr>
                            <td><?php echo get_file_icon($file['name']); ?> <?php echo htmlspecialchars($file['name']); ?></td><td><?php echo format_size($file['size']); ?></td><td><?php echo htmlspecialchars($file['perms']); ?></td>
                            <td><a href="?action=download&file=<?php echo urlencode($file['name']); ?>" class="action-btn">Baixar</a><button class="action-btn" onclick="showEditModal('<?php echo htmlspecialchars($file['name']); ?>')">Editar</button><button class="action-btn" onclick="showRenameModal('<?php echo htmlspecialchars($file['name']); ?>')">Renomear</button><button class="action-btn" onclick="showChmodModal('<?php echo htmlspecialchars($file['name']); ?>', '<?php echo htmlspecialchars($file['perms']); ?>')">Permissões</button><a href="?action=delete_file&file=<?php echo urlencode($file['name']); ?>&csrf_token=<?php echo $csrf_token; ?>" onclick="return confirm('Tem certeza?');" class="action-btn delete-btn">Excluir</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="forms-container">
                <div class="form-box"><h3>Fazer Upload</h3><form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="upload"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="file" name="file_upload" required><button type="submit">Enviar</button></form></div>
                <div class="form-box"><h3>Criar Diretório</h3><form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post"><input type="hidden" name="action" value="create_dir"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="text" name="dir_name" placeholder="Nome do diretório" required><button type="submit">Criar</button></form></div>
            </div>
        <?php endif; ?>
    </div>
    <div id="renameModal" class="modal"><div class="modal-content"><div class="modal-header"><h2>Renomear Item</h2><span class="close-btn" onclick="closeModal('renameModal')">&times;</span></div><form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post"><input type="hidden" name="action" value="rename"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?? ''; ?>"><input type="hidden" id="renameOldName" name="old_name"><p>Renomear: <strong id="renameLabel"></strong></p><input type="text" id="renameNewName" name="new_name" placeholder="Novo nome" required style="width: 95%; padding: 10px;"><br><br><button type="submit">Salvar</button></form></div></div>
    <div id="chmodModal" class="modal"><div class="modal-content"><div class="modal-header"><h2>Alterar Permissões</h2><span class="close-btn" onclick="closeModal('chmodModal')">&times;</span></div><form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post"><input type="hidden" name="action" value="chmod"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?? ''; ?>"><input type="hidden" id="chmodItemName" name="item_name"><p>Permissões para: <strong id="chmodLabel"></strong></p><input type="text" name="permissions" pattern="[0-7]{3}" title="Valor octal de 3 dígitos (ex: 755)" placeholder="Ex: 755" required style="width: 95%; padding: 10px;"><br><br><button type="submit">Salvar</button></form></div></div>
    <div id="editModal" class="modal"><div class="modal-content"><div class="modal-header"><h2 id="editModalTitle">Editar Arquivo</h2><span class="close-btn" onclick="closeModal('editModal')">&times;</span></div><form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post"><input type="hidden" name="action" value="edit"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?? ''; ?>"><input type="hidden" id="editFileName" name="file_name"><textarea id="editFileContent" name="file_content"></textarea><br><br><button type="submit">Salvar Alterações</button></form></div></div>
    <script>
        function showModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.style.display = 'none'; }
        function showRenameModal(oldName) { document.getElementById('renameOldName').value = oldName; document.getElementById('renameNewName').value = oldName; document.getElementById('renameLabel').innerText = oldName; showModal('renameModal'); }
        function showChmodModal(itemName, perms) { const numericPerms = perms.match(/[0-7]{3,4}$/); document.getElementById('chmodItemName').value = itemName; document.getElementById('chmodLabel').innerText = itemName; document.querySelector('#chmodModal input[name="permissions"]').value = numericPerms ? numericPerms[0].slice(-3) : '644'; showModal('chmodModal'); }
        async function showEditModal(fileName) {
            document.getElementById('editModalTitle').innerText = 'Editando: ' + fileName;
            document.getElementById('editFileName').value = fileName;
            const contentArea = document.getElementById('editFileContent');
            contentArea.value = 'Carregando...';
            showModal('editModal');
            try {
                const response = await fetch(`?action=get_content&file=${encodeURIComponent(fileName)}`);
                if (!response.ok) throw new Error('Falha ao carregar o arquivo.');
                contentArea.value = await response.text();
            } catch (error) { contentArea.value = 'Erro ao carregar conteúdo: ' + error.message; }
        }
    </script>
</body>
</html>