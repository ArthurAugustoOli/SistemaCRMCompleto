<?php 

class Usuario 
{
    private $conn;

    public function __construct(mysqli $conn){
        $this->conn = $conn;
    }

    public function createUsuario(
        string $nome, 
        string $login,
        string $rawPassword
    ): int {

        $sql = "
            INSERT INTO usuarios
              (nome, login, senha, data_cadastro)
            VALUES
              (?, ?, ?, NOW())
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (usuarios): " . $this->conn->error);
        }

        $stmt->bind_param(
            "sss",
            $nome,
            $login,
            $rawPassword
        );

        if (!$stmt->execute()) {
            throw new Exception("Erro na execução (usuarios): " . $stmt->error);
        }

        $novoUsuario = $this->conn->insert_id;
        $stmt->close();
        return $novoUsuario;
    }
}