<?php
namespace App\Models;

use Exception;

class Despesas
{
    private $conn;

    public function __construct()
    {
        require_once __DIR__ . '/../config/config.php';
        global $mysqli;
        $this->conn = $mysqli;
    }

    // --- Funções originais ---

    public function getAllDespesas(): array
    {
        $sql = "SELECT * FROM despesas";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (getAllDespesas): " . $this->conn->error);
        }
        $stmt->execute();
        $despesas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $despesas;
    }

    public function getDespesaById(int $id_despesa): ?array
    {
        $sql = "SELECT * FROM despesas WHERE id_despesa = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (getDespesaById): " . $this->conn->error);
        }
        $stmt->bind_param("i", $id_despesa);
        $stmt->execute();
        $despesa = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($despesa) {
            $sqlProdutos = "SELECT * FROM despesa_produtos WHERE id_despesa = ?";
            $stmtProdutos = $this->conn->prepare($sqlProdutos);
            if (!$stmtProdutos) {
                throw new Exception("Erro no prepare (getDespesaById-produtos): " . $this->conn->error);
            }
            $stmtProdutos->bind_param("i", $id_despesa);
            $stmtProdutos->execute();
            $produtos = $stmtProdutos->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtProdutos->close();
            $despesa['produtos'] = $produtos;
        }

        return $despesa;
    }

    public function createDespesa(string $categoria, string $descricao, float $valor, string $data_despesa, string $status, array $produtos = []): int
    {
        $this->conn->begin_transaction();
        try {
            $sql = "INSERT INTO despesas (categoria, descricao, valor, data_despesa, status) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erro no prepare (createDespesa): " . $this->conn->error);
            }
            $stmt->bind_param("ssdss", $categoria, $descricao, $valor, $data_despesa, $status);
            $stmt->execute();
            $id_despesa = $this->conn->insert_id;
            $stmt->close();

            foreach ($produtos as $produto) {
                $hasVariacao = !empty($produto['id_variacao']);
                if ($hasVariacao) {
                    $sqlProd = "INSERT INTO despesa_produtos (id_despesa, id_produto, id_variacao, quantidade, preco_unitario) VALUES (?, ?, ?, ?, ?)";
                    $stmtProd = $this->conn->prepare($sqlProd);
                    $stmtProd->bind_param("iiiid", $id_despesa, $produto['id_produto'], $produto['id_variacao'], $produto['quantidade'], $produto['preco_unitario']);
                } else {
                    $sqlProd = "INSERT INTO despesa_produtos (id_despesa, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)";
                    $stmtProd = $this->conn->prepare($sqlProd);
                    $stmtProd->bind_param("iiid", $id_despesa, $produto['id_produto'], $produto['quantidade'], $produto['preco_unitario']);
                }
                if (!$stmtProd) {
                    throw new Exception("Erro no prepare (createDespesa-produtos): " . $this->conn->error);
                }
                $stmtProd->execute();
                $stmtProd->close();
            }

            $this->conn->commit();
            return $id_despesa;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function updateDespesa(int $id_despesa, string $categoria, string $descricao, float $valor, string $data_despesa, string $status, array $produtos = []): bool
    {
        $this->conn->begin_transaction();
        try {
            $sql = "UPDATE despesas SET categoria = ?, descricao = ?, valor = ?, data_despesa = ?, status = ? WHERE id_despesa = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erro no prepare (updateDespesa): " . $this->conn->error);
            }
            $stmt->bind_param("ssdssi", $categoria, $descricao, $valor, $data_despesa, $status, $id_despesa);
            $stmt->execute();
            $stmt->close();

            $sqlDel = "DELETE FROM despesa_produtos WHERE id_despesa = ?";
            $stmtDel = $this->conn->prepare($sqlDel);
            $stmtDel->bind_param("i", $id_despesa);
            $stmtDel->execute();
            $stmtDel->close();

            foreach ($produtos as $produto) {
                $hasVariacao = !empty($produto['id_variacao']);
                if ($hasVariacao) {
                    $sqlProd = "INSERT INTO despesa_produtos (id_despesa, id_produto, id_variacao, quantidade, preco_unitario) VALUES (?, ?, ?, ?, ?)";
                    $stmtProd = $this->conn->prepare($sqlProd);
                    $stmtProd->bind_param("iiiid", $id_despesa, $produto['id_produto'], $produto['id_variacao'], $produto['quantidade'], $produto['preco_unitario']);
                } else {
                    $sqlProd = "INSERT INTO despesa_produtos (id_despesa, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)";
                    $stmtProd = $this->conn->prepare($sqlProd);
                    $stmtProd->bind_param("iiid", $id_despesa, $produto['id_produto'], $produto['quantidade'], $produto['preco_unitario']);
                }
                if (!$stmtProd) {
                    throw new Exception("Erro no prepare (updateDespesa-produtos): " . $this->conn->error);
                }
                $stmtProd->execute();
                $stmtProd->close();
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function deleteDespesa(int $id_despesa): bool
    {
        $sql = "DELETE FROM despesas WHERE id_despesa = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (deleteDespesa): " . $this->conn->error);
        }
        $stmt->bind_param("i", $id_despesa);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    // --- Funções para compras com e sem filtro de período ---

    public function getAllComprasProdutos(): array
    {
        $sql = "SELECT 
                    d.id_despesa,
                    dp.id_despesa_produto,
                    d.descricao AS despesa_descricao,
                    d.valor AS despesa_valor,
                    d.data_despesa,
                    p.nome AS produto_nome,
                    p.descricao AS produto_descricao,
                    p.foto AS produto_foto,
                    dp.id_variacao,
                    pv.cor,
                    pv.tamanho,
                    dp.quantidade,
                    dp.preco_unitario
                FROM despesa_produtos dp
                JOIN despesas d ON dp.id_despesa = d.id_despesa
                JOIN produtos p ON dp.id_produto = p.id_produto
                LEFT JOIN produto_variacoes pv ON dp.id_variacao = pv.id_variacao
                ORDER BY d.id_despesa DESC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (getAllComprasProdutos): " . $this->conn->error);
        }
        $stmt->execute();
        $compras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $compras;
    }

    public function getVariacoesByProduto(int $id_produto): array
    {
        $sql = "SELECT id_variacao, CONCAT(cor, ' - ', tamanho) AS descricao 
                FROM produto_variacoes 
                WHERE id_produto = ?
                ORDER BY id_variacao";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (getVariacoesByProduto): " . $this->conn->error);
        }
        $stmt->bind_param("i", $id_produto);
        $stmt->execute();
        $variacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $variacoes;
    }

    // --- Contagem e paginação com filtro de mês ---

    public function getTotalDespesas(string $dataInicio, string $dataFim): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM despesas
                WHERE LOWER(categoria) NOT LIKE 'compra de%'
                  AND data_despesa BETWEEN ? AND ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (getTotalDespesas): " . $this->conn->error);
        }
        $stmt->bind_param("ss", $dataInicio, $dataFim);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) $row['total'];
    }

    public function getDespesasPaginadas(int $offset, int $limite, string $dataInicio, string $dataFim): array
    {
        $sql = "SELECT *
                FROM despesas
                WHERE LOWER(categoria) NOT LIKE 'compra de%'
                  AND data_despesa BETWEEN ? AND ?
                ORDER BY data_despesa DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (getDespesasPaginadas): " . $this->conn->error);
        }
        $stmt->bind_param("ssii", $dataInicio, $dataFim, $limite, $offset);
        $stmt->execute();
        $despesas = $stmt->get_result()
                          ->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $despesas;
    }

    public function getSumDespesas(string $dataInicio, string $dataFim): float
    {
        $sql = "SELECT COALESCE(SUM(valor),0) AS soma
                FROM despesas
                WHERE LOWER(categoria) NOT LIKE 'compra de%'
                  AND data_despesa BETWEEN ? AND ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (getSumDespesas): " . $this->conn->error);
        }
        $stmt->bind_param("ss", $dataInicio, $dataFim);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (float) $row['soma'];
    }

    public function getTotalCompras(string $dataInicio, string $dataFim): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM despesas
                WHERE LOWER(categoria) = 'compra de produtos'
                  AND data_despesa BETWEEN ? AND ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (getTotalCompras): " . $this->conn->error);
        }
        $stmt->bind_param("ss", $dataInicio, $dataFim);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) $row['total'];
    }

    public function getComprasPaginadas(int $offset, int $limite, string $dataInicio, string $dataFim): array
    {
        $sql = "SELECT
                    d.id_despesa,
                    dp.id_despesa_produto,
                    d.descricao AS despesa_descricao,
                    d.valor AS despesa_valor,
                    d.data_despesa,
                    p.nome AS produto_nome,
                    p.descricao AS produto_descricao,
                    p.foto AS produto_foto,
                    dp.id_variacao,
                    pv.cor,
                    pv.tamanho,
                    dp.quantidade,
                    dp.preco_unitario
                FROM despesa_produtos dp
                JOIN despesas d ON dp.id_despesa = d.id_despesa
                JOIN produtos p ON dp.id_produto = p.id_produto
                LEFT JOIN produto_variacoes pv ON dp.id_variacao = pv.id_variacao
                WHERE LOWER(d.categoria) = 'compra de produtos'
                  AND d.data_despesa BETWEEN ? AND ?
                ORDER BY d.id_despesa DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro no prepare (getComprasPaginadas): " . $this->conn->error);
        }
        $stmt->bind_param("ssii", $dataInicio, $dataFim, $limite, $offset);
        $stmt->execute();
        $compras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $compras;
    }
}
