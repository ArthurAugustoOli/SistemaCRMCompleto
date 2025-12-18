<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u566100020_sistema_erp_');
define('DB_USER', 'u566100020_roooot');
define('DB_PASS', 'Tavin7belo');
define('AUTH_USER', 'Silvania');
define('AUTH_PASS', '4508'); 
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Erro na conexão com o banco de dados: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8");
