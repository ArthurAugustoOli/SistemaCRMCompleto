    <?php
    session_start();
    require_once '../../app/config/config.php'; // Ajuste o caminho conforme necessário


    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $login = trim($_POST['login'] ?? '');
        $senha = trim($_POST['senha'] ?? '');

        if (empty($login) || empty($senha)) {
            $message = 'Preencha todos os campos.';
        } else {
            $stmt = $mysqli->prepare("SELECT id_usuario, nome, login, senha, type FROM usuarios WHERE login = ?");
            $stmt->bind_param("s", $login);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $usuario = $result->fetch_assoc();

                if ($senha === $usuario['senha']) { // Troque isso por password_verify se usar hash
                    // Sucesso no login - Armazenar sessão
                    $_SESSION['id_usuario'] = $usuario['id_usuario'];
                    $_SESSION['nome'] = $usuario['nome'];
                    $_SESSION['login'] = $usuario['login'];
                    $_SESSION['senha']     = $usuario['senha'];
                    $_SESSION['type'] = $usuario['type'];
                    // Redirecionar para o dashboard
                    header("Location: ../produtos/index.php");
                    exit;
                } else {
                    $message = 'Login ou senha incorretos.';
                }
            } else {
                $message = 'Login ou senha incorretos.';
            }
            $stmt->close();
        }
    }
    ?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login | Sistema de Gestão</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5" style="max-width: 400px;">
        <h2 class="mb-4 text-center">Login</h2>
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger"> <?= htmlspecialchars($message) ?> </div>
        <?php endif; ?>
        <form action="" method="POST">
            <div class="mb-3">
                <label for="login" class="form-label">Usuário</label>
                <input type="text" class="form-control" id="login" name="login" required>
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Senha</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
    </div>
</body>
</html>
