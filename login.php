<?php
/**
 * Sistema de Tutoriais e POP's - Login
 * Versão: 3.0 (Neobrutalism Edition)
 */

require_once 'config.php';

$error = '';
$success = '';

if (isset($_GET['expired'])) {
    $error = 'Sua sessão expirou. Por favor, faça login novamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['last_activity'] = time();

                    $updateStmt = $pdo->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
                    $updateStmt->execute([$user['id']]);

                    log_audit($user['id'], 'LOGIN', 'Usuário fez login com sucesso');

                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Credenciais inválidas.';
                    log_audit(null, 'LOGIN_FAILED', "Tentativa de login com usuário: $username");
                }
            } catch (Exception $e) {
                $error = 'Erro ao processar login. Tente novamente.';
                error_log('Erro de login: ' . $e->getMessage());
            }
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - STEPS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'main': '#A3E635',
                        'secondary': '#818CF8',
                        'accent': '#F472B6',
                        'brutal-yellow': '#FDE047',
                    },
                    boxShadow: {
                        'brutal': '4px 4px 0px 0px #000',
                        'brutal-lg': '8px 8px 0px 0px #000',
                    },
                    borderWidth: {
                        '3': '3px',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Lexend', sans-serif;
            background-color: #F1F5F9;
            background-image: radial-gradient(#000 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .brutal-card {
            background: white;
            border: 3px solid black;
            box-shadow: 8px 8px 0px 0px #000;
        }
        .brutal-input {
            border: 3px solid black;
            box-shadow: 4px 4px 0px 0px #000;
            transition: all 0.2s;
        }
        .brutal-input:focus {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0px 0px #000;
            outline: none;
        }
        .brutal-btn {
            border: 3px solid black;
            box-shadow: 4px 4px 0px 0px #000;
            transition: all 0.1s;
        }
        .brutal-btn:active {
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0px 0px #000;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="brutal-card p-8 bg-white">
            <div class="text-center mb-8">
                <div class="inline-block p-4 bg-main border-3 border-black shadow-brutal mb-4">
                    <i class="bi bi-play-circle text-4xl"></i>
                </div>
                <h1 class="text-3xl font-black uppercase tracking-tighter">S.T.E.P.S</h1>
                <h5 class="text-1xl font-black lowercase tracking-tighter">Sistema de Tutoriais e POP's</h5>
                <p class="font-bold text-gray-600">Acesso Administrativo</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-400 border-3 border-black p-4 mb-6 shadow-brutal font-bold">
                    <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div>
                    <label for="username" class="block font-black uppercase mb-2">
                        <i class="bi bi-person"></i> Usuário
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="w-full p-3 brutal-input font-bold"
                        placeholder="Digite seu usuário"
                        required
                        autofocus
                    >
                </div>

                <div>
                    <label for="password" class="block font-black uppercase mb-2">
                        <i class="bi bi-lock"></i> Senha
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="w-full p-3 brutal-input font-bold"
                        placeholder="Digite sua senha"
                        required
                    >
                </div>

                <button type="submit" class="w-full p-4 brutal-btn bg-secondary text-white font-black text-xl uppercase tracking-widest hover:bg-indigo-500">
                    Entrar <i class="bi bi-box-arrow-in-right ml-2"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t-3 border-black border-dashed text-center">
                <p class="font-bold text-sm bg-brutal-yellow inline-block p-1 border-2 border-black">
                    <i class="bi bi-info-circle"></i> 
                    admin / admin123
                </p>
            </div>
        </div>
    </div>
</body>
</html>
