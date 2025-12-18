<?php
// app/models/Cliente.php

class Cliente {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Retorna todos os clientes da tabela "clientes".
     */
    public function getAll() {
        $sql = "SELECT * FROM clientes ORDER BY nome ASC";
        $result = $this->mysqli->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function searchByName($term) {
    $sql = "SELECT id_cliente, nome FROM clientes WHERE nome LIKE ? LIMIT 10";
    $stmt = $this->mysqli->prepare($sql);
    $stmt->bind_param('s', $term);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Retorna um cliente específico pelo ID.
     */
    public function getById($id) {
        $stmt = $this->mysqli->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Cria um novo cliente na tabela "clientes".
     */
    public function create($data) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO clientes 
            (nome, cpf_cnpj, data_nascimento, telefone, email, cep, logradouro, numero, complemento, bairro, cidade, estado, pontos_fidelidade)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssssssssi",
            $data['nome'],
            $data['cpf_cnpj'],
            $data['data_nascimento'],
            $data['telefone'],
            $data['email'],
            $data['cep'],
            $data['logradouro'],
            $data['numero'],
            $data['complemento'],
            $data['bairro'],
            $data['cidade'],
            $data['estado'],
            $data['pontos_fidelidade']
        );
        return $stmt->execute();
    }

    /**
     * Atualiza os dados de um cliente existente.
     */
    public function update($id, $data) {
        $stmt = $this->mysqli->prepare("
            UPDATE clientes 
            SET 
                nome = ?, 
                cpf_cnpj = ?, 
                data_nascimento = ?, 
                telefone = ?, 
                email = ?, 
                cep = ?, 
                logradouro = ?, 
                numero = ?, 
                complemento = ?, 
                bairro = ?, 
                cidade = ?, 
                estado = ?, 
                pontos_fidelidade = ?
            WHERE id_cliente = ?
        ");
        $stmt->bind_param(
            "ssssssssssssii",
            $data['nome'],
            $data['cpf_cnpj'],
            $data['data_nascimento'],
            $data['telefone'],
            $data['email'],
            $data['cep'],
            $data['logradouro'],
            $data['numero'],
            $data['complemento'],
            $data['bairro'],
            $data['cidade'],
            $data['estado'],
            $data['pontos_fidelidade'],
            $id
        );
        return $stmt->execute();
    }

    /**
     * Exclui um cliente da tabela "clientes".
     */
    public function delete($id) {
        $stmt = $this->mysqli->prepare("DELETE FROM clientes WHERE id_cliente = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    public function getPaginado($offset, $limite) {
        $stmt = $this->mysqli->prepare("SELECT * FROM clientes ORDER BY nome ASC LIMIT ?, ?");
        $stmt->bind_param("ii", $offset, $limite);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getTotalClientes() {
        $result = $this->mysqli->query("SELECT COUNT(*) AS total FROM clientes");
        return $result->fetch_assoc()['total'];
    }

    public function getAniversariantesHoje() {
        $query = "SELECT * FROM clientes WHERE DATE_FORMAT(data_nascimento, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')";
        $result = $this->mysqli->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getAniversariantesProximos() {
        $hoje = date('m-d');
        $proximos = date('m-d', strtotime('+7 days'));
    
        if ($hoje > $proximos) {
            $query = "SELECT * FROM clientes WHERE 
                      DATE_FORMAT(data_nascimento, '%m-%d') >= ? 
                      OR DATE_FORMAT(data_nascimento, '%m-%d') <= ?";
        } else {
            $query = "SELECT * FROM clientes WHERE 
                      DATE_FORMAT(data_nascimento, '%m-%d') >= ? 
                      AND DATE_FORMAT(data_nascimento, '%m-%d') <= ?";
        }
    
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param('ss', $hoje, $proximos);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function getClientesInativos($dias = 60) {
        // A query faz um LEFT JOIN com a tabela vendas e agrupa por cliente,
        // pegando a data máxima (última compra) de cada cliente.
        $sql = "SELECT c.*, MAX(v.data_venda) AS ultima_compra
                FROM clientes c
                LEFT JOIN vendas v ON c.id_cliente = v.id_cliente AND v.status = 'finalizada'
                GROUP BY c.id_cliente
                HAVING (ultima_compra IS NULL OR ultima_compra < DATE_SUB(CURDATE(), INTERVAL ? DAY))";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
        
    public function getTotalInativos(int $dias): int {
        $sql = "
            SELECT COUNT(*) AS total 
              FROM (
                SELECT c.id_cliente 
                  FROM clientes c
                  LEFT JOIN vendas v 
                    ON c.id_cliente = v.id_cliente AND v.status = 'finalizada'
                  GROUP BY c.id_cliente
                  HAVING (MAX(v.data_venda) IS NULL OR MAX(v.data_venda) < DATE_SUB(CURDATE(), INTERVAL ? DAY))
              ) AS sub
        ";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int) $row['total'];
    }
    public function getInativosPaginado(int $dias, int $offset, int $limite): array {
        $sql = "
            SELECT c.*, MAX(v.data_venda) AS ultima_compra
              FROM clientes c
              LEFT JOIN vendas v 
                ON c.id_cliente = v.id_cliente AND v.status = 'finalizada'
             GROUP BY c.id_cliente
            HAVING (MAX(v.data_venda) IS NULL OR MAX(v.data_venda) < DATE_SUB(CURDATE(), INTERVAL ? DAY))
            ORDER BY ultima_compra IS NULL DESC, ultima_compra ASC
            LIMIT ?, ?
        ";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("iii", $dias, $offset, $limite);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
