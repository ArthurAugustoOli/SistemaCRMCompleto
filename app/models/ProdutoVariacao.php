<?php
class ProdutoVariacao
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getAllByProduto($id_produto)
    {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM produto_variacoes 
            WHERE id_produto = ? 
            ORDER BY id_variacao DESC
        ");
        $stmt->bind_param("i", $id_produto);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Busca uma variação pelo ID
    public function getById($id_variacao)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM produto_variacoes WHERE id_variacao = ?");
        $stmt->bind_param("i", $id_variacao);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Cria uma nova variação
    public function create($dados)
        {
            $sql = "INSERT INTO produto_variacoes 
                    (id_produto, cor, tamanho, sku, preco_venda, estoque_atual)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param(
                "isssdi",
                $dados['id_produto'],
                $dados['cor'],
                $dados['tamanho'],
                $dados['sku'],
                $dados['preco_venda'],
                $dados['estoque_atual']
            );
            return $stmt->execute();
        }

    // Atualiza uma variação
    public function update($id_variacao, $dados)
    {
        $sql = "UPDATE produto_variacoes
                SET cor = ?, tamanho = ?, sku = ?, 
                    preco_venda = ?, estoque_atual = ?
                WHERE id_variacao = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param(
            "sssdii",
            $dados['cor'],
            $dados['tamanho'],
            $dados['sku'],
            $dados['preco_venda'],
            $dados['estoque_atual'],
            $id_variacao
        );
        return $stmt->execute();
    }

    public function setPrecoVendaVariacao(int $id_variacao, float $preco_venda)
{
    $stmt = $this->mysqli->prepare("UPDATE produto_variacoes SET preco_venda = ? WHERE id_variacao = ?");
    $stmt->bind_param("di", $preco_venda, $id_variacao);
    $stmt->execute();
    $stmt->close();
}


    // Exclui uma variação
    public function delete($id_variacao)
    {
        $stmt = $this->mysqli->prepare("DELETE FROM produto_variacoes WHERE id_variacao = ?");
        $stmt->bind_param("i", $id_variacao);
        return $stmt->execute();
    }

    public function getEstoque(int $id_variacao): int
    {
        $stmt = $this->mysqli->prepare("
            SELECT estoque_atual 
              FROM produto_variacoes 
             WHERE id_variacao = ? 
             FOR UPDATE
        ");
        $stmt->bind_param("i", $id_variacao);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['estoque_atual'] : 0;
    }

    public function getEstoqueAtual(int $id_variacao): ?int
    {
        $stmt = $this->mysqli->prepare("
            SELECT estoque_atual 
              FROM produto_variacoes 
             WHERE id_variacao = ?
        ");
        $stmt->bind_param("i", $id_variacao);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['estoque_atual'] : null;
    }

    public function temEstoque(int $id_variacao, int $quantidade): bool
    {
        $estoque = $this->getEstoqueAtual($id_variacao);
        if ($estoque === null) {
            return false;
        }
        return $estoque >= $quantidade;
    }
    
    public function baixarEstoque(int $id_variacao, int $quantidade): bool
    {
        // 1) Leitura em modo FOR UPDATE para bloquear a linha enquanto checamos o estoque
        $stmtSel = $this->mysqli->prepare(
            "SELECT estoque_atual
            FROM produto_variacoes
            WHERE id_variacao = ?
            FOR UPDATE"
        );
        $stmtSel->bind_param("i", $id_variacao);
        $stmtSel->execute();
        $res = $stmtSel->get_result();
        if (! $row = $res->fetch_assoc()) {
            // variação não existe
            $stmtSel->close();
            return false;
        }
        $estoqueAtual = (int) $row['estoque_atual'];
        $stmtSel->close();

        // 2) Se não houver estoque suficiente, já retorna false
        if ($quantidade > $estoqueAtual) {
            return false;
        }

        // 3) Calcula o novo estoque e faz o UPDATE
        $novoEstoque = $estoqueAtual - $quantidade;
        $stmtUpd = $this->mysqli->prepare(
            "UPDATE produto_variacoes
                SET estoque_atual = ?
            WHERE id_variacao = ?"
        );
        $stmtUpd->bind_param("ii", $novoEstoque, $id_variacao);
        $resultado = $stmtUpd->execute();
        $stmtUpd->close();

        return $resultado;
    }


    // Busca uma variação pelo SKU
public function getBySKU($sku)
{
    $stmt = $this->mysqli->prepare("SELECT * FROM produto_variacoes WHERE sku = ?");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

public function adicionarEstoque($id_produto, $qtd) {
    global $mysqli;
    $stmt = $mysqli->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id_produto = ?");
    $stmt->bind_param("ii", $qtd, $id_produto);
    $stmt->execute();
    $stmt->close();
}
    
public function adicionarEstoqueVariacao($id_variacao, $qtd) {
    global $mysqli;
    $stmt = $mysqli->prepare("UPDATE produto_variacoes SET estoque_atual = estoque_atual + ? WHERE id_variacao = ?");
    $stmt->bind_param("ii", $qtd, $id_variacao);
    $stmt->execute();
    $stmt->close();
}

   public function generateSku(string $nomeProduto, string $tamanho, string $cor): string
    {
        // 1) iniciais do nome
        $words = preg_split('/\s+/', trim($nomeProduto));
        $pref  = '';
        foreach ($words as $w) {
            $w = preg_replace('/[^A-Za-z]/', '', $w);
            if ($w !== '') {
                $pref .= strtoupper(substr($w, 0, 1));
            }
        }
        // 2) tamanho (limpo)
        $t = strtoupper(preg_replace('/[^A-Za-z0-9]/','', $tamanho));
        // 3) primeira letra da cor
        $c = '';
        if (!empty($cor)) {
            $c = strtoupper(substr(preg_replace('/[^A-Za-z]/','', $cor), 0, 1));
        }
        return $pref . $t . $c;
    }
    public function adicionarEstoqueEAtualizarCustoVariacao(int $id_variacao, int $quantidade, float $preco_custo): bool
{
    // abre transação
    $this->mysqli->begin_transaction();
    try {
        // 1) Busca o id_produto dessa variação
        $stmt1 = $this->mysqli->prepare(
            "SELECT id_produto FROM produto_variacoes WHERE id_variacao = ? FOR UPDATE"
        );
        $stmt1->bind_param("i", $id_variacao);
        $stmt1->execute();
        $res = $stmt1->get_result();
        if (! $row = $res->fetch_assoc()) {
            $stmt1->close();
            $this->mysqli->rollback();
            return false;
        }
        $id_produto = (int)$row['id_produto'];
        $stmt1->close();

        // 2) Atualiza estoque da variação
        $stmt2 = $this->mysqli->prepare(
            "UPDATE produto_variacoes
               SET estoque_atual = estoque_atual + ?
             WHERE id_variacao = ?"
        );
        $stmt2->bind_param("ii", $quantidade, $id_variacao);
        if (! $stmt2->execute()) {
            $stmt2->close();
            $this->mysqli->rollback();
            return false;
        }
        $stmt2->close();

        // 3) Atualiza o preço de custo no produto-pai
        $stmt3 = $this->mysqli->prepare(
            "UPDATE produtos
               SET preco_custo = ?
             WHERE id_produto = ?"
        );
        $stmt3->bind_param("di", $preco_custo, $id_produto);
        if (! $stmt3->execute()) {
            $stmt3->close();
            $this->mysqli->rollback();
            return false;
        }
        $stmt3->close();

        // 4) confirma transação
        $this->mysqli->commit();
        return true;
    } catch (\Exception $e) {
        $this->mysqli->rollback();
        return false;
    }
}
 }

