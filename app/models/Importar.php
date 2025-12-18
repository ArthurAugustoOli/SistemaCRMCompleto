<?php
// app/models/Importar.php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Models\Despesas;

class Importar
{
    private $db;
    private $despesaModel;

    public function __construct(mysqli $mysqli)
    {
        $this->db = $mysqli;
        require_once __DIR__ . '/Despesas.php';
        $this->despesaModel = new Despesas();
    }

    /**
     * Importa um arquivo contendo as abas "produtos" e "variacoes",
     * mapeia suas colunas pela base fixa, e persiste no banco.
     */
    public function importar(string $filePath): void
    {
        // Cria o reader para .xlsx, .xls, .csv, .ods…
        $reader      = IOFactory::createReaderForFile($filePath);
        $spreadsheet = $reader->load($filePath);

        // Captura as abas
        $sheetP = $spreadsheet->getSheetByName('produtos');
        $sheetV = $spreadsheet->getSheetByName('variacoes');
        if (! $sheetP || ! $sheetV) {
            throw new \Exception("Planilha precisa conter abas 'produtos' e 'variacoes'.");
        }

        // Remove a linha “A, B, C…” para que o cabeçalho verdadeiro fique na linha 1
        $sheetP->removeRow(1, 1);
        $sheetV->removeRow(1, 1);

        // Importa cada aba
        $produtosParaDespesa  = $this->importarProdutos($sheetP);
        $variacoesParaDespesa = $this->importarVariacoes($sheetV);

        // Consolida itens e gera despesa
        $itens = array_merge($produtosParaDespesa, $variacoesParaDespesa);
        if (! empty($itens)) {
            $valorTotal = array_reduce(
                $itens,
                fn($sum, $item) => $sum + ($item['preco_unitario'] * $item['quantidade']),
                0
            );
            $this->despesaModel->createDespesa(
                'Compra de Produtos',
                'Importação automática',
                $valorTotal,
                date('Y-m-d'),
                'pendente',
                $itens
            );
        }
    }

    /**
     * Mapeia dinamicamente cabeçalhos para índices de coluna.
     */
    private function getColumnMapping($sheet, array $required): array
    {
        $highestCol = $sheet->getHighestColumn();
        $maxIndex   = Coordinate::columnIndexFromString($highestCol);
        $map        = [];

        for ($c = 1; $c <= $maxIndex; $c++) {
            $addr = Coordinate::stringFromColumnIndex($c) . '1';
            $raw  = (string)$sheet->getCell($addr)->getValue();
            $norm = strtolower(
                preg_replace('/[^a-z0-9]/', '', iconv('UTF-8','ASCII//TRANSLIT', $raw))
            );
            foreach ($required as $key => $aliases) {
                foreach ($aliases as $alias) {
                    if ($norm === strtolower(preg_replace('/[^a-z0-9]/','', $alias))) {
                        $map[$key] = $c;
                        break 2;
                    }
                }
            }
        }

        $missing = array_diff(array_keys($required), array_keys($map));
        if ($missing) {
            throw new \Exception('Campos faltando: ' . implode(', ', $missing));
        }
        return $map;
    }

    /**
     * Importa a aba 'produtos' conforme a base:
     * nome | descricao | foto | estoque_min | estoque_max |
     * localizacao_estoque | preco_custo | preco_venda |
     * estoque_atual | codigo_barras
     */
    private function importarProdutos($sheet): array
    {
        $required = [
            'nome'                => ['nome'],
            'descricao'           => ['descricao'],
            'foto'                => ['foto'],
            'estoque_min'         => ['estoque_min'],
            'estoque_max'         => ['estoque_max'],
            'localizacao_estoque' => ['localizacao_estoque'],
            'preco_custo'         => ['preco_custo'],
            'preco_venda'         => ['preco_venda'],
            'estoque_atual'       => ['estoque_atual'],
            'codigo_barras'       => ['codigo_barras','barcode'],
        ];
        $map    = $this->getColumnMapping($sheet, $required);
        $maxRow = $sheet->getHighestRow();
        $itens  = [];

        for ($r = 2; $r <= $maxRow; $r++) {
            $get = fn($k) => $sheet
                ->getCell(Coordinate::stringFromColumnIndex($map[$k]) . $r)
                ->getValue();

            $nome      = trim((string)$get('nome'));
            $descricao = (string)$get('descricao');
            if ($nome === '') {
                continue;
            }

            $dados = [
                'nome'                => $nome,
                'descricao'           => $descricao,
                'foto'                => (string)$get('foto'),
                'estoque_min'         => (int)$get('estoque_min'),
                'estoque_max'         => (int)$get('estoque_max'),
                'localizacao_estoque' => (string)$get('localizacao_estoque'),
                'preco_custo'         => (float)$get('preco_custo'),
                'preco_venda'         => (float)$get('preco_venda'),
                'estoque_atual'       => (int)$get('estoque_atual'),
                'codigo_barras'       => trim((string)$get('codigo_barras')),
            ];

            // Lógica de SELECT/UPDATE/INSERT em produtos
            $stmt = $this->db->prepare(
                "SELECT id_produto, estoque_atual
                   FROM produtos
                  WHERE codigo_barras = ?
                  LIMIT 1"
            );
            $stmt->bind_param('s', $dados['codigo_barras']);
            $stmt->execute();
            $exist = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exist) {
                $dados['estoque_atual'] += $exist['estoque_atual'];
                $upd = $this->db->prepare(
                    "UPDATE produtos
                        SET nome               = ?,
                            descricao          = ?,
                            foto               = ?,
                            estoque_min        = ?,
                            estoque_max        = ?,
                            localizacao_estoque= ?,
                            preco_custo        = ?,
                            preco_venda        = ?,
                            estoque_atual      = ?
                      WHERE id_produto = ?"
                );
                $upd->bind_param(
                    'sssiisddii',
                    $dados['nome'], $dados['descricao'], $dados['foto'],
                    $dados['estoque_min'], $dados['estoque_max'], $dados['localizacao_estoque'],
                    $dados['preco_custo'], $dados['preco_venda'], $dados['estoque_atual'],
                    $exist['id_produto']
                );
                $upd->execute();
                $upd->close();
                $id = $exist['id_produto'];
            } else {
                $ins = $this->db->prepare(
                    "INSERT INTO produtos
                        (nome,descricao,foto,estoque_min,estoque_max,
                         localizacao_estoque,preco_custo,preco_venda,
                         estoque_atual,codigo_barras)
                     VALUES (?,?,?,?,?,?,?,?,?,?)"
                );
                $ins->bind_param(
                    'sssiisddis',
                    $dados['nome'], $dados['descricao'], $dados['foto'],
                    $dados['estoque_min'], $dados['estoque_max'], $dados['localizacao_estoque'],
                    $dados['preco_custo'], $dados['preco_venda'], $dados['estoque_atual'],
                    $dados['codigo_barras']
                );
                $ins->execute();
                $id = $this->db->insert_id;
                $ins->close();
            }

            if ($dados['estoque_atual'] > 0) {
                $itens[] = [
                    'id_produto'     => $id,
                    'id_variacao'    => null,
                    'quantidade'     => $dados['estoque_atual'],
                    'preco_unitario' => $dados['preco_custo'],
                ];
            }
        }

        return $itens;
    }

    /**
     * Importa a aba 'variacoes' conforme a base:
     * codigo_barras | cor | tamanho | sku | preco_venda | estoque_atual
     */
    private function importarVariacoes($sheet): array
    {
        $required = [
            'parent_barcode' => ['codigo_barras','barcode'],
            'cor'            => ['cor'],
            'tamanho'        => ['tamanho'],
            'sku'            => ['sku'],
            'preco_venda'    => ['preco_venda'],
            'estoque_atual'  => ['estoque_atual'],
        ];
        $map    = $this->getColumnMapping($sheet, $required);
        $maxRow = $sheet->getHighestRow();
        $itens  = [];

        for ($r = 2; $r <= $maxRow; $r++) {
            $get = fn($k) => (string)$sheet
                ->getCell(Coordinate::stringFromColumnIndex($map[$k]) . $r)
                ->getValue();

            $pb   = trim($get('parent_barcode'));
            $cor  = $get('cor');
            $tam  = $get('tamanho');
            $sku  = trim($get('sku'));
            $pv   = (float)$get('preco_venda');
            $qtde = (int)$get('estoque_atual');

            if ($pb === '' || $tam === '') {
                continue;
            }

            // Busca produto-pai para obter preco_custo
            $stmt = $this->db->prepare(
                "SELECT id_produto, preco_custo
                   FROM produtos
                  WHERE codigo_barras = ?
                  LIMIT 1"
            );
            $stmt->bind_param('s', $pb);
            $stmt->execute();
            $pai = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (! $pai) {
                continue;
            }

            // Verifica existência da variação
            $chk = $this->db->prepare(
                "SELECT id_variacao, estoque_atual
                   FROM produto_variacoes
                  WHERE sku = ?
                  LIMIT 1"
            );
            $chk->bind_param('s', $sku);
            $chk->execute();
            $existVar = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existVar) {
                $novoEst = $existVar['estoque_atual'] + $qtde;
                $upd = $this->db->prepare(
                    "UPDATE produto_variacoes
                        SET cor=?, tamanho=?, preco_venda=?, estoque_atual=?
                      WHERE id_variacao=?"
                );
                $upd->bind_param('ssdii', $cor, $tam, $pv, $novoEst, $existVar['id_variacao']);
                $upd->execute();
                $upd->close();
                $idVar = $existVar['id_variacao'];
            } else {
                $ins = $this->db->prepare(
                    "INSERT INTO produto_variacoes
                        (id_produto,cor,tamanho,sku,preco_venda,estoque_atual)
                     VALUES (?,?,?,?,?,?)"
                );
                $ins->bind_param('isssdi', $pai['id_produto'], $cor, $tam, $sku, $pv, $qtde);
                $ins->execute();
                $idVar = $this->db->insert_id;
                $ins->close();
            }

            if ($qtde > 0) {
                $itens[] = [
                    'id_produto'     => $pai['id_produto'],
                    'id_variacao'    => $idVar,
                    'quantidade'     => $qtde,
                    'preco_unitario' => $pai['preco_custo'],
                ];
            }
        }

        return $itens;
    }
}
