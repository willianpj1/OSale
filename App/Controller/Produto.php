<?php

namespace app\controller;

use app\database\builder\InsertQuery;
use app\database\builder\SelectQuery;
use app\database\builder\UpdateQuery;

class Produto extends Base
{

    public function lista($request, $response)
    {
        $dadosTemplate = [
            'titulo' => 'Lista de Produtos'
        ];
        return $this->getTwig()
            ->render($response, $this->setView('listproduto'), $dadosTemplate)
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    public function cadastro($request, $response)
    {
        try {
            $dadosTemplate = [
                'acao' => 'c',
                'titulo' => 'Cadastro'
            ];
            return $this->getTwig()
                ->render($response, $this->setView('produto'), $dadosTemplate)
                ->withHeader('Content-Type', 'text/html')
                ->withStatus(200);
        } catch (\Exception $e) {
            var_dump($e);
        }
    }
    public function insert($request, $response)
    {
        try {
            $form = $request->getParsedBody();

            $FieldAndValues = [
                'nome'            => $form['nome'],
                'codigo_barra'    => $form['codigo_barra'],
                'descricao_curta' => $form['descricao_curta'],
                'valor'           => (float)str_replace(['.', ','], ['', '.'], $form['valor'])
            ];
            $IsSave = InsertQuery::table('product')->save($FieldAndValues);
            if (!$IsSave) {
                return $this->SendJson($response, ['status' => false, 'msg' => 'Erro ao salvar no banco.'], 403);
            }
            $produto = SelectQuery::select('id')->from('product')->order('id', 'desc')->fetch();
            return $this->SendJson($response, [
                'status' => true,
                'msg' => 'Produto salvo com sucesso!',
                'id' => $produto['id']
            ], 201);
        } catch (\Exception $e) {
            return $this->SendJson($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }
    public function listproductdata($request, $response)
    {
        $form = $request->getParsedBody();
        $term = $form['term'] ?? null;
        $query = SelectQuery::select('id, codigo_barra, nome')->from('product');
        if ($term != null) {
            $query->where('codigo_barra', 'ILIKE', "%{$term}%", 'or')
                ->where('nome', 'ILIKE', "%{$term}%");
        }
        $data = [];
        $results = $query->fetchAll();
        foreach ($results as $key => $item) {
            $data['results'][$key] = [
                'id' => $item['id'],
                'text' => $item['nome'] . ' - Cód. barra: ' . $item['codigo_barra']
            ];
        }
        return $this->SendJson($response, $data);
    }
    public function listproduto($request, $response)
    {
        #Captura todas a variaveis de forma mais segura VARIAVEIS POST.
        $form = $request->getParsedBody();
        #Qual a coluna da tabela deve ser ordenada.
        $order = $form['order'][0]['column'];
        #Tipo de ordenação
        $orderType = $form['order'][0]['dir'];
        #Em qual registro se inicia o retorno dos registros, OFFSET
        $start = $form['start'];
        #Limite de registro a serem retornados do banco de dados LIMIT
        $length = $form['length'];
        $fields = [
            0 => 'id_produto',
            1 => 'nome',
            2 => 'estoque_atual',
        ];
        #Capturamos o nome do campo a ser odernado.
        $orderField = $fields[$order];
        #O termo pesquisado
        $term = $form['search']['value'];
        $query = SelectQuery::select()->from('mvw_estoque');
        if (!is_null($term) && ($term !== '')) {
            $query
                ->where('id_produto', 'ilike', "%{$term}%")
                ->where('nome', 'ilike', "%{$term}%", 'or')
                ->where('estoque_atual', 'ilike', "%{$term}%", 'or');
        }
        $product = $query
            ->order($orderField, $orderType)
            ->limit($length, $start)
            ->fetchAll();
        $produtoData = [];
        foreach ($product as $key => $value) {
            $produtoData[$key] = [
                $value['id_produto'],
                $value['nome'],
                $value['estoque_atual'],
                "<div class='d-flex gap-2'>
                    <a href='/produto/alterar/{$value['id_produto']}' class='btn btn-warning'>
                        <i class='bi bi-pencil-square'></i> Alterar
                    </a>
                    <button type='button' onclick='AjustarEstoque({$value['id_produto']});' class='btn btn-info'>
                        <i class='bi bi-trash-fill'></i> Estoque
                    </button>
                    <button type='button' onclick='Delete({$value['id_produto']});' class='btn btn-danger'>
                        <i class='bi bi-trash-fill'></i> Excluir
                    </button>
                </div>"
            ];
        }
        $data = [
            'status' => true,
            'recordsTotal' => count($product),
            'recordsFiltered' => count($product),
            'data' => $produtoData
        ];
        $payload = json_encode($data);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
    public function alterar($request, $response, $args)
    {
        try {
            $id = $args['id'];
            $produto = SelectQuery::select()->from('product')->where('id', '=', $id)->fetch();
            $dadosTemplate = [
                'acao' => 'e',
                'id' => $id,
                'titulo' => 'Cadastro e edição',
                'produto' => $produto
            ];
            return $this->getTwig()
                ->render($response, $this->setView('produto'), $dadosTemplate)
                ->withHeader('Content-Type', 'text/html')
                ->withStatus(200);
        } catch (\Exception $e) {
            var_dump($e);
        }
    }
    public function delete($request, $response)
    {
        try {
            $id = $_POST['id'];
            $IsDelete = UpdateQuery::table('product')
                ->set(['excluido' => true])
                ->where('id', '=', $id)
                ->update();
            if (!$IsDelete) {
                echo json_encode(['status' => false, 'msg' => $IsDelete, 'id' => $id]);
                die;
            }
            echo json_encode(['status' => true, 'msg' => 'Removido com sucesso!', 'id' => $id]);
            die;
        } catch (\Throwable $th) {
            echo "Erro: " . $th->getMessage();
            die;
        }
    }
    public function update($request, $response)
    {
        try {
            $form = $request->getParsedBody();
            $id = $form['id'];
            if (is_null($id) || empty($id)) {
                return $this->SendJson($response, ['status' => false, 'msg' => 'Por favor informe o ID', 'id' => 0], 500);
            }
            $FieldAndValues = [
                'nome' => $form['nome'],
                'descricao_curta' => $form['descricao_curta'],
                'valor' => $form['valor']
            ];
            $IsUpdate = UpdateQuery::table('product')->set($FieldAndValues)->where('id', '=', $id)->update();
            if (!$IsUpdate) {
                return $this->SendJson($response, ['status' => false, 'msg' => 'Restrição: ' . $IsUpdate, 'id' => 0], 403);
            }
            return $this->SendJson($response, ['status' => true, 'msg' => 'Atualizado com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->SendJson($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function selecionarestoque($request, $response)
    {
        try {
            $form = $request->getParsedBody();
            $id = $form['id'];
            $product = SelectQuery::select('estoque_atual')->from('mvw_estoque')->where('id_produto', '=', $id)->fetch();
            $estoqueAtual = $product['estoque_atual'] ?? 0;
            if (!isset($form['nova_quantidade']) || $form['nova_quantidade'] === '') {
                return $this->SendJson($response, [
                    'status' => true,
                    'estoque_atual' => $estoqueAtual
                ]);
            }
            $novoEstoqueDesejado = (float)$form['nova_quantidade'];
            $estoqueAtualNumerico = (float)$estoqueAtual;
            $quantidadeAjuste = $novoEstoqueDesejado - $estoqueAtualNumerico;
            if ($quantidadeAjuste == 0) {
                return $this->SendJson($response, ['status' => true, 'msg' => 'O estoque já é este valor.']);
            }
            $FieldsAndValues = [
                'id_produto'         => $id,
                'quantidade_entrada' => ($quantidadeAjuste > 0) ? $quantidadeAjuste : 0,
                'quantidade_saida'   => ($quantidadeAjuste < 0) ? abs($quantidadeAjuste) : 0,
                'observacao'         => 'AJUSTE MANUAL',
                'tipo'               => ($quantidadeAjuste > 0) ? 'ENTRADA' : 'SAIDA',
                'origem_movimento'   => 'COMPRA'
            ];
            $IsSave = InsertQuery::table('stock_movement')->save($FieldsAndValues);
            if (!$IsSave) {
                return $this->SendJson($response, ['status' => false, 'msg' => 'Erro ao salvar ajuste.'], 403);
            }
            return $this->SendJson($response, [
                'status' => true,
                'msg' => "Estoque ajustado! Movimentação de " . abs($quantidadeAjuste) . " unidades."
            ]);
        } catch (\Exception $e) {
            return $this->SendJson($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }
}
