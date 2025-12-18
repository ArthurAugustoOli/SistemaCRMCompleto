<?php
class Troca {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    public function registrar(
        int $id_venda,
        int $id_item,
        int $old_produto,
        ?int $old_variacao,
        int $new_produto,
        ?int $new_variacao,
        string $usuario_login
    ) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO trocas
              (id_venda, id_item,
               old_id_produto, old_id_variacao,
               new_id_produto, new_id_variacao,
               usuario_login)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'iiiiiis',
            $id_venda,
            $id_item,
            $old_produto,
            $old_variacao,
            $new_produto,
            $new_variacao,
            $usuario_login
        );
        $stmt->execute();
        $stmt->close();
    }

    public function getAll(): array {
        $sql = "
          SELECT t.*,
                 DATE_FORMAT(t.data_troca, '%d/%m/%Y %H:%i') AS data_formatada,
                 v.id_cliente,
                 c.nome AS nome_cliente,
                 f.nome AS nome_funcionario
            FROM trocas t
            JOIN vendas v ON t.id_venda = v.id_venda
            JOIN clientes c ON v.id_cliente = c.id_cliente
            LEFT JOIN funcionarios f ON v.id_funcionario = f.id_funcionario
           ORDER BY t.data_troca DESC
        ";
        $res = $this->mysqli->query($sql);
        return $res->fetch_all(MYSQLI_ASSOC);
    }
}
