<?php
class Produto
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function adicionarEstoque($id_produto, $quantidade)
    {
        $stmt = $this->mysqli->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id_produto = ?");
        $stmt->bind_param("ii", $quantidade, $id_produto);
        $stmt->execute();
        $stmt->close();
    }
    
    
    
    
    
    public function getAll()
    {
        $sql = "
            SELECT 
                p.*,
                -- pega o maior entre o preco_venda do produto e o MAX(preco_venda) das variações
                GREATEST(
                    p.preco_venda,
                    COALESCE(
                        (SELECT MAX(preco_venda) 
                           FROM produto_variacoes v 
                          WHERE v.id_produto = p.id_produto
                        ), 
                    0)
                ) AS preco_exibicao
            FROM produtos p
            ORDER BY p.nome ASC
        ";
        $result = $this->mysqli->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    public function getById($id_produto)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM produtos WHERE id_produto = ?");
        $stmt->bind_param("i", $id_produto);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    public function getByCode($codigo_barras)
    {
        $stmt = $this->mysqli->prepare("SELECT id_produto, nome, preco_venda FROM produtos WHERE codigo_barras = ?");
        $stmt->bind_param("s", $codigo_barras);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    // Atualiza apenas o custo
public function setPrecoCusto(int $id_produto, float $preco_custo)
{
    $stmt = $this->mysqli->prepare(
      "UPDATE produtos 
         SET preco_custo = ? 
       WHERE id_produto = ?"
    );
    $stmt->bind_param("di", $preco_custo, $id_produto);
    $stmt->execute();
    $stmt->close();
}

// Atualiza apenas o preço de venda
public function setPrecoVenda(int $id_produto, float $preco_venda)
{
    $stmt = $this->mysqli->prepare("UPDATE produtos SET preco_venda = ? WHERE id_produto = ?");
    $stmt->bind_param("di", $preco_venda, $id_produto);
    $stmt->execute();
    $stmt->close();
}


    public function create($dados): mixed
    {
        $sql = "INSERT INTO produtos 
                (nome, descricao, foto, estoque_min, estoque_max, localizacao_estoque, 
                 preco_custo, preco_venda, codigo_barras, estoque_atual)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param(
            "sssiissdsi",
            $dados['nome'],
            $dados['descricao'],
            $dados['foto'],
            $dados['estoque_min'],
            $dados['estoque_max'],
            $dados['localizacao_estoque'],
            $dados['preco_custo'],
            $dados['preco_venda'],
            $dados['codigo_barras'],
            $dados['estoque_atual']
        );
        return $stmt->execute();
    }

    public function update($id_produto, $dados)
    {
        $sql = "UPDATE produtos 
                SET nome = ?, descricao = ?, foto = ?, 
                    estoque_min = ?, estoque_max = ?, localizacao_estoque = ?, 
                    preco_custo = ?, preco_venda = ?, codigo_barras = ?, estoque_atual = ?
                WHERE id_produto = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param(
            "sssiissdsii",
            $dados['nome'],
            $dados['descricao'],
            $dados['foto'],
            $dados['estoque_min'],
            $dados['estoque_max'],
            $dados['localizacao_estoque'],
            $dados['preco_custo'],
            $dados['preco_venda'],
            $dados['codigo_barras'],
            $dados['estoque_atual'],
            $id_produto
        );
        return $stmt->execute();
    }
    public function delete($id_produto)
    {

    
        // Se não houver registros dependentes, exclui o produto
        $stmt = $this->mysqli->prepare("DELETE FROM produtos WHERE id_produto = ?");
        $stmt->bind_param("i", $id_produto);
        $result = $stmt->execute();
        $stmt->close();
    
        return $result;
    }
    
    public function search($term)
    {
        $like = "%{$term}%";
        $sql = "
            SELECT p.*
            FROM produtos p
            LEFT JOIN produto_variacoes v ON p.id_produto = v.id_produto
            WHERE p.nome LIKE ? 
               OR p.codigo_barras LIKE ?
               OR v.sku LIKE ?
            GROUP BY p.id_produto
            ORDER BY p.id_produto DESC
        ";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("sss", $like, $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function atualizarEstoqueAtual($id_produto, $novoEstoque)
    {
        $stmt = $this->mysqli->prepare("UPDATE produtos SET estoque_atual = ? WHERE id_produto = ?");
        $stmt->bind_param("ii", $novoEstoque, $id_produto);
        return $stmt->execute();
    }

    public function getEstoqueAtual(int $id_produto): ?int
    {
        $stmt = $this->mysqli->prepare("
            SELECT estoque_atual 
              FROM produtos 
             WHERE id_produto = ?
        ");
        $stmt->bind_param("i", $id_produto);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['estoque_atual'] : null;
    }
    
    public function baixarEstoque(int $id_produto, int $quantidade): bool
    {
        $stmtSel = $this->mysqli->prepare(
            "SELECT estoque_atual 
               FROM produtos 
              WHERE id_produto = ? 
              FOR UPDATE"
        );
        $stmtSel->bind_param("i", $id_produto);
        $stmtSel->execute();
        $res = $stmtSel->get_result();
        if (!$row = $res->fetch_assoc()) {
            $stmtSel->close();
            return false;
        }
        $estoqueAtual = (int) $row['estoque_atual'];
        $stmtSel->close();

        if ($quantidade > $estoqueAtual) {
            return false;
        }

        $novoEstoque = $estoqueAtual - $quantidade;
        $stmtUpd = $this->mysqli->prepare(
            "UPDATE produtos 
                SET estoque_atual = ? 
              WHERE id_produto = ?"
        );
        $stmtUpd->bind_param("ii", $novoEstoque, $id_produto);
        $resultado = $stmtUpd->execute();
        $stmtUpd->close();

        return $resultado; 
    }
   public function generateCodigoBarras(string $nome): string
{
    // quebre em palavras
    $words = preg_split('/\s+/', trim($nome));
    $code  = '';
    foreach ($words as $w) {
        // remove qualquer caractere que não seja letra
        $w = preg_replace('/[^A-Za-z]/', '', $w);
        if ($w !== '') {
            // pega os 3 primeiros caracteres (ou menos, se a palavra for curta)
            $code .= strtoupper(substr($w, 0, min(3, strlen($w))));
        }
    }
    return $code;
}

public function adicionarEstoqueEAtualizarCusto(int $id_produto, int $quantidade, float $preco_custo): bool
{
    // inicia transação para garantir consistência
    $this->mysqli->begin_transaction();

    try {
        // update único para estoque + custo
        $stmt = $this->mysqli->prepare(
            "UPDATE produtos
               SET estoque_atual = estoque_atual + ?,
                   preco_custo   = ?
             WHERE id_produto = ?"
        );
        $stmt->bind_param("idi", $quantidade, $preco_custo, $id_produto);
        $ok = $stmt->execute();
        $stmt->close();

        if (! $ok) {
            // falhou, desfaz
            $this->mysqli->rollback();
            return false;
        }

        // tudo certo: confirma
        $this->mysqli->commit();
        return true;
    } catch (\Exception $e) {
        // em caso de erro, desfaz tudo
        $this->mysqli->rollback();
        return false;
    }
}
    public function searchByName(string $term): array
{
    $sql = "
      SELECT 
        p.id_produto,
        p.nome,
        p.preco_venda
      FROM produtos p
      WHERE p.nome LIKE ?
      LIMIT 10
    ";
    $stmt = $this->mysqli->prepare($sql);
    $stmt->bind_param('s', $term);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
    return $res->fetch_all(MYSQLI_ASSOC);
    }
    


}

