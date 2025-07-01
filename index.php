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
    public function listContents($path) { 
        if (!is_resource($this->conn)) throw new Exception("A conexão FTP foi perdida."); 
        if (@ftp_chdir($this->conn, $path) === false) throw new Exception("Não foi possível acessar o diretório.");
        $raw_list = @ftp_rawlist($this->conn, ".");
        $dirs = []; $files = [];
        foreach ($raw_list as $item) {
            $chunks = preg_split("/\s+/", $item, 9);
            if (count($chunks) < 9) continue;
            $name = $chunks[8];
            if ($name === "." || $name === "..") continue;
            $is_dir = $chunks[0][0] === "d";
            $size = (int)$chunks[4];
            $perms = substr($chunks[0], 1);
            if ($is_dir) {
                $dirs[] = ['name' => $name, 'perms' => $perms];
            } else {
                $files[] = ['name' => $name, 'size' => $size, 'perms' => $perms];
            }
        }
        return ['dirs' => $dirs, 'files' => $files];
    }
    public function getCurrentPath() { return ftp_pwd($this->conn); }
    public function changeDir($dir) { return @ftp_chdir($this->conn, $dir); }
    public function deleteFile($file) { return @ftp_delete($this->conn, $file); }
    public function deleteDir($dir) { return @ftp_rmdir($this->conn, $dir); }
    public function createDir($name) { return @ftp_mkdir($this->conn, $name); }
    public function rename($from, $to) { return @ftp_rename($this->conn, $from, $to); }
    public function setPermissions($path, $mode) { return @ftp_chmod($this->conn, octdec($mode), $path); }
    public function uploadFile($source_path, $remote_name) { return @ftp_put($this->conn, $remote_name, $source_path, FTP_BINARY); }
    public function getFileContents($file) { 
        $handle = fopen('php://temp', 'r+'); 
        if (@ftp_fget($this->conn, $handle, $file, FTP_BINARY, 0)) { 
            rewind($handle); 
            return stream_get_contents($handle); 
        } 
        return false; 
    }
    public function updateFileContents($file, $content) { 
        $handle = fopen('php://temp', 'r+'); 
        fwrite($handle, $content); 
        rewind($handle); 
        return @ftp_fput($this->conn, $file, $handle, FTP_BINARY, 0); 
    }
    public function createEmptyFile($file) {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, ''); rewind($handle);
        return @ftp_fput($this->conn, $file, $handle, FTP_BINARY, 0);
    }
    public function downloadFile($local_path, $remote_path) { return @ftp_get($this->conn, $local_path, $remote_path, FTP_BINARY); }
    public function close() { if ($this->conn && is_resource($this->conn)) @ftp_close($this->conn); }
}

//======================================================================
// FUNÇÕES AUXILIARES GLOBAIS
//======================================================================
function format_size($size) { if ($size <= 0) return '0 B'; $units = ['B', 'KB', 'MB', 'GB', 'TB']; $i = floor(log($size, 1024)); return round($size / (1024 ** $i), 2) . ' ' . $units[$i]; }
function get_file_icon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'png': case 'jpg': case 'jpeg': case 'gif': case 'bmp': case 'svg': return '<i class="bi bi-image"></i>';
        case 'zip': case 'rar': case 'gz': case 'tar': return '<i class="bi bi-file-zip"></i>';
        case 'php': return '<i class="bi bi-filetype-php"></i>';
        case 'html': case 'htm': return '<i class="bi bi-filetype-html"></i>';
        case 'css': return '<i class="bi bi-filetype-css"></i>';
        case 'js': return '<i class="bi bi-filetype-js"></i>';
        case 'pdf': return '<i class="bi bi-file-pdf"></i>';
        case 'txt': return '<i class="bi bi-file-text"></i>';
        case 'doc': case 'docx': return '<i class="bi bi-file-word"></i>';
        case 'xls': case 'xlsx': return '<i class="bi bi-file-excel"></i>';
        case 'mp3': case 'wav': return '<i class="bi bi-music-note"></i>';
        case 'mp4': case 'avi': case 'mov': return '<i class="bi bi-file-earmark-play"></i>';
        default: return '<i class="bi bi-file-earmark"></i>';
    }
}

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
        $ftpManager = null;
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
            case 'delete_dir': if ($ftpManager->deleteDir($current_path . '/' . $_GET['dir'])) $_SESSION['success_message'] = "Diretório excluído."; else throw new Exception("Erro ao excluir diretório."); break;
            case 'upload': if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) { if ($ftpManager->uploadFile($_FILES['file_upload']['tmp_name'], $current_path . '/' . $_FILES['file_upload']['name'])) $_SESSION['success_message'] = "Upload concluído."; else throw new Exception("Falha no upload."); } else throw new Exception("Nenhum arquivo enviado."); break;
            case 'create_dir': if (!empty($_POST['dir_name'])) { if ($ftpManager->createDir($current_path . '/' . $_POST['dir_name'])) $_SESSION['success_message'] = "Diretório criado."; else throw new Exception("Erro ao criar diretório."); } break;
            case 'create_file': if (!empty($_POST['file_name'])) { 
                $file_path = $current_path . '/' . $_POST['file_name'];
                if ($ftpManager->createEmptyFile($file_path)) $_SESSION['success_message'] = "Arquivo criado."; else throw new Exception("Erro ao criar arquivo."); 
            } break;
            case 'rename': if (!empty($_POST['old_name']) && !empty($_POST['new_name'])) { if ($ftpManager->rename($current_path . '/' . $_POST['old_name'], $current_path . '/' . $_POST['new_name'])) $_SESSION['success_message'] = "Renomeado!"; else throw new Exception("Erro ao renomear."); } break;
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { transition: background-color 0.3s, color 0.3s; }
        .dark-mode { background-color: #18191a !important; color: #e4e6eb !important; }
        .dark-mode .card, .dark-mode .modal-content, .dark-mode .form-control { background-color: #242526 !important; color: #e4e6eb !important; }
        .dark-mode .table { color: #e4e6eb; }
        .theme-toggle { cursor: pointer; }
        .breadcrumbs a { color: #0d6efd; text-decoration: none; }
        .breadcrumbs a:hover { text-decoration: underline; }
        .breadcrumbs span { margin: 0 5px; color: #6c757d; }
        .file-icon { font-size: 1.2em; margin-right: 4px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content textarea { width: 100%; height: 300px; font-family: monospace; box-sizing: border-box; }
        @media (max-width: 600px) {
            .table-responsive { font-size: 0.92em; }
            .container, .card { padding: 0.5rem; }
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="bi bi-hdd-network"></i> FTP 2.0</a>
            <button class="btn btn-outline-light theme-toggle" id="themeToggleBtn" title="Alternar tema">
                <i class="bi bi-moon-fill"></i>
            </button>
        </div>
    </nav>
    <div class="container">
        <div class="card shadow-sm p-4 mb-4">
            <h1 class="mb-4">Gerenciador de FTP</h1>
            <?php if (!$ftpManager): ?>
                <h2 class="mb-3">Conectar ao Servidor FTP</h2>
                <?php if ($error_message) echo '<div class="alert alert-danger">'.$error_message.'</div>'; ?>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="ftp_host" placeholder="Servidor FTP" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="ftp_user" placeholder="Usuário" required>
                    </div>
                    <div class="col-md-6">
                        <input type="password" class="form-control" name="ftp_pass" placeholder="Senha" required>
                    </div>
                    <div class="col-md-3">
                        <input type="number" class="form-control" name="ftp_port" placeholder="Porta (padrão 21)">
                    </div>
                    <div class="col-md-3 d-flex align-items-center">
                        <input type="checkbox" class="form-check-input me-2" name="ftp_ssl" id="ssl">
                        <label class="form-check-label" for="ssl">Usar conexão segura (FTPS/SSL)</label>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right"></i> Conectar</button>
                    </div>
                </form>
            <?php else: 
                $csrf_token = $_SESSION['csrf_token'];
                $current_path = $_SESSION['ftp_path'];
                $contents = $ftpManager->listContents($current_path);
            ?>
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                    <p class="mb-0">Conectado a: <strong><?php echo htmlspecialchars($_SESSION['ftp_creds']['host']); ?></strong></p>
                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="mb-0">
                        <input type="hidden" name="action" value="logout">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-left"></i> Sair</button>
                    </form>
                </div>
                <?php if ($error_message) echo '<div class="alert alert-danger">'.$error_message.'</div>'; ?>
                <?php if ($success_message) echo '<div class="alert alert-success">'.$success_message.'</div>'; ?>
                <nav class="breadcrumbs mb-3">
                    <?php
                    $base = $ftpManager->basePath;
                    $rel = trim(str_replace($base, '', $current_path), '/');
                    echo '<a href="?action=chdir&dir=' . urlencode($base) . '&csrf_token='.$csrf_token.'"><i class="bi bi-house"></i></a>';
                    if ($rel) {
                        $path = $base;
                        foreach (explode('/', $rel) as $i => $p) {
                            $path .= '/' . $p;
                            echo ' <span>/</span> <a href="?action=chdir&dir='.urlencode($path).'&csrf_token='.$csrf_token.'">'.htmlspecialchars($p).'</a>';
                        }
                    }
                    ?>
                </nav>
                <div class="table-responsive mb-4">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tamanho</th>
                                <th>Permissões</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($current_path !== $ftpManager->basePath && $current_path !== '/'): ?>
                            <tr>
                                <td colspan="4">
                                    <a href="?action=chdir&dir=..&csrf_token=<?php echo $csrf_token; ?>">
                                        <i class="bi bi-arrow-90deg-up"></i> Voltar
                                    </a>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($contents['dirs'] as $dir): ?>
                            <tr>
                                <td>
                                    <a href="?action=chdir&dir=<?php echo urlencode($current_path . '/' . $dir['name']); ?>&csrf_token=<?php echo $csrf_token; ?>">
                                        <i class="bi bi-folder-fill file-icon text-warning"></i> <?php echo htmlspecialchars($dir['name']); ?>
                                    </a>
                                </td>
                                <td>--</td>
                                <td><?php echo htmlspecialchars($dir['perms']); ?></td>
                                <td>
                                    <button class="btn btn-outline-secondary btn-sm me-1" onclick="showRenameModal('<?php echo htmlspecialchars($dir['name']); ?>')"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-outline-secondary btn-sm me-1" onclick="showChmodModal('<?php echo htmlspecialchars($dir['name']); ?>', '<?php echo htmlspecialchars($dir['perms']); ?>')"><i class="bi bi-shield-lock"></i></button>
                                    <a href="?action=delete_dir&dir=<?php echo urlencode($dir['name']); ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir este diretório?');"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php foreach ($contents['files'] as $file): ?>
                            <tr>
                                <td><?php echo get_file_icon($file['name']); ?> <?php echo htmlspecialchars($file['name']); ?></td>
                                <td><?php echo format_size($file['size']); ?></td>
                                <td><?php echo htmlspecialchars($file['perms']); ?></td>
                                <td>
                                    <a href="?action=download&file=<?php echo urlencode($file['name']); ?>" class="btn btn-outline-primary btn-sm me-1"><i class="bi bi-download"></i></a>
                                    <button class="btn btn-outline-secondary btn-sm me-1" onclick="showEditModal('<?php echo htmlspecialchars($file['name']); ?>')"><i class="bi bi-pencil-square"></i></button>
                                    <button class="btn btn-outline-secondary btn-sm me-1" onclick="showRenameModal('<?php echo htmlspecialchars($file['name']); ?>')"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-outline-secondary btn-sm me-1" onclick="showChmodModal('<?php echo htmlspecialchars($file['name']); ?>', '<?php echo htmlspecialchars($file['perms']); ?>')"><i class="bi bi-shield-lock"></i></button>
                                    <a href="?action=delete_file&file=<?php echo urlencode($file['name']); ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir este arquivo?');"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card p-3">
                            <h5 class="mb-2"><i class="bi bi-upload"></i> Fazer Upload</h5>
                            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="file" name="file_upload" class="form-control mb-2" required>
                                <button type="submit" class="btn btn-success w-100"><i class="bi bi-upload"></i> Enviar</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3">
                            <h5 class="mb-2"><i class="bi bi-folder-plus"></i> Criar Diretório</h5>
                            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                <input type="hidden" name="action" value="create_dir">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="text" name="dir_name" class="form-control mb-2" placeholder="Nome do diretório" required>
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-folder-plus"></i> Criar</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3">
                            <h5 class="mb-2"><i class="bi bi-file-earmark-plus"></i> Criar Arquivo</h5>
                            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                <input type="hidden" name="action" value="create_file">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="text" name="file_name" class="form-control mb-2" placeholder="Nome do arquivo (ex: novo.txt)" required>
                                <button type="submit" class="btn btn-info w-100"><i class="bi bi-file-earmark-plus"></i> Criar</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Modals -->
    <div id="renameModal" class="modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <div class="modal-header">
                    <h5 class="modal-title">Renomear Item</h5>
                    <button type="button" class="btn-close" aria-label="Fechar" onclick="closeModal('renameModal')"></button>
                </div>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?? ''; ?>">
                        <input type="hidden" name="old_name" id="renameOldName">
                        <div class="mb-3">
                            <label for="renameNewName" class="form-label">Novo nome:</label>
                            <input type="text" class="form-control" id="renameNewName" name="new_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('renameModal')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Renomear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="chmodModal" class="modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <div class="modal-header">
                    <h5 class="modal-title">Alterar Permissões</h5>
                    <button type="button" class="btn-close" aria-label="Fechar" onclick="closeModal('chmodModal')"></button>
                </div>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="chmod">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?? ''; ?>">
                        <input type="hidden" name="item_name" id="chmodItemName">
                        <div class="mb-3">
                            <label class="form-label" for="chmodPerms">Permissões (ex: 755):</label>
                            <input type="text" class="form-control" id="chmodPerms" name="permissions" required pattern="[0-7]{3,4}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('chmodModal')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Alterar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="editModal" class="modal">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content p-4">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalTitle">Editar Arquivo</h5>
                    <button type="button" class="btn-close" aria-label="Fechar" onclick="closeModal('editModal')"></button>
                </div>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?? ''; ?>">
                        <input type="hidden" name="file_name" id="editFileName">
                        <div class="mb-3">
                            <label for="editFileContent" class="form-label">Conteúdo do arquivo:</label>
                            <textarea class="form-control" id="editFileContent" name="file_content" rows="12"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancelar</button>
                        <button type="submit" class="btn btn-success">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script>
        // Dark/Light mode toggle
        const toggleBtn = document.getElementById('themeToggleBtn');
        toggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            if(document.body.classList.contains('dark-mode')){
                toggleBtn.innerHTML = '<i class="bi bi-sun-fill"></i>';
            } else {
                toggleBtn.innerHTML = '<i class="bi bi-moon-fill"></i>';
            }
        });

        function showModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        window.onclick = function(event) { 
            if (event.target.classList.contains('modal')) event.target.style.display = 'none'; 
        }
        function showRenameModal(oldName) {
            document.getElementById('renameOldName').value = oldName;
            document.getElementById('renameNewName').value = oldName;
            showModal('renameModal');
        }
        function showChmodModal(itemName, perms) {
            document.getElementById('chmodItemName').value = itemName;
            document.getElementById('chmodPerms').value = '';
            showModal('chmodModal');
        }
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
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>