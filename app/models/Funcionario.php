<?php
// ====================================================================
// Extensão do Model: Adicionando o método searchByName
// ====================================================================
namespace App\Models;

use Exception;

class Funcionario {
    private $conn;

    public function __construct($mysqli) {
        $this->conn = $mysqli;
    }

    // Retorna todos os funcionários
    public function getAll() {
        try {
            $sql = "SELECT * FROM funcionarios ORDER BY nome ASC";
            $result = $this->conn->query($sql);
            if (!$result) {
                throw new Exception("Erro na query: " . $this->conn->error);
            }
            return $result->fetch_all(\MYSQLI_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Erro em getAll: " . $e->getMessage());
        }
    }

    // Método para buscar funcionários pelo nome
    public function searchByName($name) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM funcionarios WHERE nome LIKE ? ORDER BY nome ASC");
            if (!$stmt) {
                throw new Exception("Erro no prepare (searchByName): " . $this->conn->error);
            }
            $param = "%" . $name . "%";
            $stmt->bind_param("s", $param);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(\MYSQLI_ASSOC);
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            throw new Exception("Erro em searchByName: " . $e->getMessage());
        }
    }

    // Retorna um funcionário pelo id
    public function getById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM funcionarios WHERE id_funcionario = ?");
            if (!$stmt) {
                throw new Exception("Erro no prepare (getById): " . $this->conn->error);
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $funcionario = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $funcionario;
        } catch (Exception $e) {
            throw new Exception("Erro em getById: " . $e->getMessage());
        }
    }

    // Cria um novo funcionário
    public function create(array $data): int {
        try {
            $this->conn->begin_transaction();

            $sql = "INSERT INTO funcionarios 
                (nome, cpf_cnpj, telefone, email, cep, logradouro, numero, complemento, bairro, cidade, estado, data_admissao, comissao_atual, senha)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erro no prepare (create funcionario): " . $this->conn->error);
            }

            $bindStr = "ssssssssssssds";
            $stmt->bind_param(
                $bindStr,
                $data['nome'],
                $data['cpf_cnpj'],
                $data['telefone'],
                $data['email'],
                $data['cep'],
                $data['logradouro'],
                $data['numero'],
                $data['complemento'],
                $data['bairro'],
                $data['cidade'],
                $data['estado'],
                $data['data_admissao'],
                $data['comissao_atual'],
                $data['senha']
            );

            if (!$stmt->execute()) {
                throw new Exception("Erro na execução (create funcionario): " . $stmt->error);
            }

            $idFuncionario = $this->conn->insert_id;
            $stmt->close();

            require_once __DIR__ . '/Usuario.php';
            $usuarioModel = new \Usuario($this->conn);

            $nomeUsuario  = $data['nome'];
            $loginUsuario = $data['nome']; 
            $senhaBruta   = $data['senha'];

            $usuarioModel->createUsuario(
                $nomeUsuario,
                $loginUsuario,
                $senhaBruta
            );

            $this->conn->commit();
            return $idFuncionario;

        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Erro em create (Funcionario + Usuario): " . $e->getMessage());
        }
    }

    // Atualiza um funcionário; se a senha estiver vazia, mantém a atual
    public function update($id, $data) {
        try {
            $sql = "UPDATE funcionarios 
                    SET nome = ?, cpf_cnpj = ?, telefone = ?, email = ?, cep = ?, logradouro = ?, 
                        numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ?, data_admissao = ?, 
                        comissao_atual = ?, senha = ?
                    WHERE id_funcionario = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erro no prepare (update): " . $this->conn->error);
            }
            $bindStr = "ssssssssssssdsi";
            $stmt->bind_param(
                $bindStr,
                $data['nome'],
                $data['cpf_cnpj'],
                $data['telefone'],
                $data['email'],
                $data['cep'],
                $data['logradouro'],
                $data['numero'],
                $data['complemento'],
                $data['bairro'],
                $data['cidade'],
                $data['estado'],
                $data['data_admissao'],
                $data['comissao_atual'],
                $data['senha'],
                $id
            );
            if (!$stmt->execute()) {
                throw new Exception("Erro na execução (update): " . $stmt->error);
            }
            $stmt->close();
            return true;
        } catch (Exception $e) {
            throw new Exception("Erro em update: " . $e->getMessage());
        }
    }

    // Exclui um funcionário
    public function delete($id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM funcionarios WHERE id_funcionario = ?");
            if (!$stmt) {
                throw new Exception("Erro no prepare (delete): " . $this->conn->error);
            }
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception("Erro na execução (delete): " . $stmt->error);
            }
            $stmt->close();
            return true;
        } catch (Exception $e) {
            throw new Exception("Erro em delete: " . $e->getMessage());
        }
    }

    /**
     * Calcula e atualiza a comissão do funcionário com base nas vendas do mês atual.
     * A comissão é 5% do total vendido no mês vigente.
     * Atualiza o campo comissao_atual e registra/atualiza o histórico.
     *
     * @param int $id_funcionario
     * @return float
     */
    public function atualizarComissao($id_funcionario) {
        $mesAtual = date('Y-m');
        $sql = "SELECT SUM(total_venda) as total FROM vendas
                WHERE id_funcionario = ? AND DATE_FORMAT(data_venda, '%Y-%m') = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (atualizarComissao): " . $this->conn->error);
        }
        $stmt->bind_param("is", $id_funcionario, $mesAtual);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $totalVendas = isset($result['total']) ? floatval($result['total']) : 0;
        $comissao = $totalVendas * 0.05;

        $stmt2 = $this->conn->prepare("UPDATE funcionarios SET comissao_atual = ? WHERE id_funcionario = ?");
        if (!$stmt2) {
            throw new Exception("Erro no prepare (update comissao): " . $this->conn->error);
        }
        $stmt2->bind_param("di", $comissao, $id_funcionario);
        $stmt2->execute();
        $stmt2->close();

        $this->registrarHistoricoComissao($id_funcionario, $mesAtual, $totalVendas, $comissao);

        return $comissao;
    }

    /**
     * Registra ou atualiza o histórico da comissão na tabela comissoes_historico.
     *
     * @param int $id_funcionario
     * @param string $mes (YYYY-MM)
     * @param float $totalVendas
     * @param float $comissao
     */
    public function registrarHistoricoComissao($id_funcionario, $mes, $totalVendas, $comissao) {
        $stmt = $this->conn->prepare("SELECT id FROM comissoes_historico WHERE id_funcionario = ? AND mes = ?");
        if (!$stmt) {
            throw new Exception("Erro no prepare (historico select): " . $this->conn->error);
        }
        $stmt->bind_param("is", $id_funcionario, $mes);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result) {
            $stmt2 = $this->conn->prepare("UPDATE comissoes_historico SET total_vendas = ?, comissao = ?, data_registro = NOW() WHERE id = ?");
            if (!$stmt2) {
                throw new Exception("Erro no prepare (historico update): " . $this->conn->error);
            }
            $stmt2->bind_param("ddi", $totalVendas, $comissao, $result['id']);
            $stmt2->execute();
            $stmt2->close();
        } else {
            $stmt2 = $this->conn->prepare("INSERT INTO comissoes_historico (id_funcionario, mes, total_vendas, comissao) VALUES (?, ?, ?, ?)");
            if (!$stmt2) {
                throw new Exception("Erro no prepare (historico insert): " . $this->conn->error);
            }
            $stmt2->bind_param("isdd", $id_funcionario, $mes, $totalVendas, $comissao);
            $stmt2->execute();
            $stmt2->close();
        }
    }

    /**
     * Retorna a comissão atual do funcionário.
     *
     * @param int $id_funcionario
     * @return float
     */
    public function getComissaoAtual($id_funcionario) {
        $stmt = $this->conn->prepare("SELECT comissao_atual FROM funcionarios WHERE id_funcionario = ?");
        if (!$stmt) {
            throw new Exception("Erro no prepare (getComissaoAtual): " . $this->conn->error);
        }
        $stmt->bind_param("i", $id_funcionario);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($result['comissao_atual']) ? floatval($result['comissao_atual']) : 0;
    }

    /**
     * Retorna o histórico de comissões do funcionário.
     *
     * @param int $id_funcionario
     * @return array
     */
    public function getHistoricoComissoes($id_funcionario) {
        $stmt = $this->conn->prepare("SELECT * FROM comissoes_historico WHERE id_funcionario = ? ORDER BY mes DESC");
        if (!$stmt) {
            throw new Exception("Erro no prepare (getHistoricoComissoes): " . $this->conn->error);
        }
        $stmt->bind_param("i", $id_funcionario);
        $stmt->execute();
        $historico = $stmt->get_result()->fetch_all(\MYSQLI_ASSOC);
        $stmt->close();
        return $historico;
    }
}
?>