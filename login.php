<?php
/**
 * Sistema de Tutoriais e POP's - Login
 * Versão: 2.0 (Revisada)
 * 
 * Página de autenticação com hash de senha e proteção CSRF
 */

require_once 'config.php';

$error = '';
$success = '';

// Verificar se sessão expirou
if (isset($_GET['expired'])) {
    $error = 'Sua sessão expirou. Por favor, faça login novamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = 'Token de segurança inválido. Tente novamente.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Usuário e senha são obrigatórios.';
        } else {
            try {
                $db = Database::getInstance();
                $pdo = $db->getConnection();

                $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ?');
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    // Autenticação bem-sucedida
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['last_activity'] = time();

                    // Atualizar último login
                    $updateStmt = $pdo->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
                    $updateStmt->execute([$user['id']]);

                    // Registrar na auditoria
                    log_audit($user['id'], 'LOGIN', 'Usuário fez login com sucesso');

                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Credenciais inválidas.';
                    // Registrar tentativa falhada
                    log_audit(null, 'LOGIN_FAILED', "Tentativa de login com usuário: $username");
                }
            } catch (Exception $e) {
                $error = 'Erro ao processar login. Tente novamente.';
                error_log('Erro de login: ' . $e->getMessage());
            }
        }
    }
}

// Gerar token CSRF
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Login - Sistema de Tutoriais e POP's</title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }
        .card-header .icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 0.75rem;
            font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 5px;
            padding: 0.75rem;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .alert {
            border-radius: 5px;
            border: none;
        }
        .input-group-text {
            background: transparent;
            border: 1px solid #ddd;
            color: #667eea;
        }

        /* Responsivo para Celulares */
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            .login-container {
                max-width: 100%;
                width: 100%;
            }
            .card {
                border-radius: 8px;
            }
            .card-header {
                padding: 1.5rem;
            }
            .card-header h4 {
                font-size: 1.3rem;
            }
            .card-header .icon {
                font-size: 2.5rem;
            }
            .card-body {
                padding: 1.5rem !important;
            }
            .form-control, .form-select {
                padding: 0.85rem;
                font-size: 1rem;
                border-radius: 8px;
            }
            .form-label {
                font-size: 0.95rem;
                margin-bottom: 0.6rem;
            }
            .btn-login {
                padding: 0.9rem;
                font-size: 1rem;
                border-radius: 8px;
            }
            .mb-3, .mb-4 {
                margin-bottom: 1rem !important;
            }
            input, label {
                touch-action: manipulation;
            }
        }

        /* Melhorias de Toque */
        @media (hover: none) {
            .btn-login:active {
                transform: scale(0.98);
            }
            .form-control:focus {
                outline: 2px solid #667eea;
                outline-offset: 2px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <div class="icon">
                    <i class="bi bi-play-circle"></i>
                </div>
                <h4>Sistema de Tutoriais e POP's</h4>
                <small>Acesso Administrativo</small>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person"></i> Usuário
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="username" 
                            name="username" 
                            placeholder="Digite seu usuário"
                            required
                            autofocus
                            
                        >
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> Senha
                        </label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="Digite sua senha"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-login w-100 text-white">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                    </button>
                </form>

                <hr class="my-4">
                <small class="text-muted d-block text-center">
                    <i class="bi bi-info-circle"></i> 
                    Credenciais padrão: admin / admin123
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validação do formulário
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const forms = document.querySelectorAll('.needs-validation');
                Array.prototype.slice.call(forms).forEach(function(form) {
                    form.addEventListener('submit', function(event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            });
        })();
    </script>
</body>
</html>
