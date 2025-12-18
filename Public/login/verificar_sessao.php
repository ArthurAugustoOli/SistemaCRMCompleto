<?php
session_start();

// Tempo máximo de inatividade em segundos (1 hora = 3600 segundos)
$tempoMaximo = 3600; 

// Verifica se o usuário está logado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login/index.php");
    exit;
}

// Verifica se existe um timestamp de atividade registrado
if (isset($_SESSION['ultimo_acesso'])) {
    $tempoInativo = time() - $_SESSION['ultimo_acesso'];
    
    if ($tempoInativo > $tempoMaximo) {
        // Destroi a sessão e redireciona para a página de login
        session_unset();
        session_destroy();
        header("Location: ../login/index.php?message=Sua sessão expirou. Por favor, faça login novamente.");
        exit;
    }
}

// Atualiza o timestamp de atividade para o tempo atual
$_SESSION['ultimo_acesso'] = time();
