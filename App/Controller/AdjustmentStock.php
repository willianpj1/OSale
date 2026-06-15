<?php

namespace app\controller;

use app\database\builder\SelectQuery;

class AdjustmentStock extends Base
{

    public function lista($request, $response)
    {
        $dadosTemplate = [
            'titulo' => 'Lista de Estoque'
        ];
        return $this->getTwig()
            ->render($response, $this->setView('listadjustmentstock'), $dadosTemplate)
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    public function listajusteestoque($request, $response)
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
            0 => 'id',
            1 => 'nome',
            2 => 'quantidade',
            3 => 'total_bruto',
        ];
        #Capturamos o nome do campo a ser odernado.
        $orderField = $fields[$order];
        #O termo pesquisado
        $term = $form['search']['value'];
        $query = SelectQuery::select()->from('item_purchase');
        if (!is_null($term) && ($term !== '')) {
            $query
                ->where('id', 'ilike', "%{$term}%")
                ->where('nome', 'ilike', "%{$term}%", 'or')
                ->where('quantidade', 'ilike', "%{$term}%", 'or')
                ->where('total_bruto    ', 'ilike', "%{$term}%", 'or');        
        }
        $estoque = $query
            ->order($orderField, $orderType)
            ->limit($length, $start)
            ->fetchAll();
        $produtoData = [];
        foreach ($estoque as $key => $value) {
            $produtoData[$key] = [
                $value['id'],
                $value['nome'],
                $value['quantidade'],
                $value['total_bruto'],
                "<div class='d-flex gap-2'>
                    <button type='button' class='btn btn-primary btn-sm px-2 shadow-sm' style='white-space: nowrap; font-weight: 500;' data-bs-toggle='modal' data-bs-target='#modalstock'>
                        <i class='bi bi-plus-circle'></i> Ajustar
                    </button>"
                ];
                }
                $data = [
            'status' => true,
            'recordsTotal' => count($estoque),
            'recordsFiltered' => count($estoque),
            'data' => $produtoData
        ];
        $payload = json_encode($data);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}