<?php

namespace App\Controller;

class Home extends Base
{
    public function home($request, $response)
    {
                $dadosTemplate = [
            'titulo' => 'Página inicial'
        ];
        return $this->getTwig()
            ->render($response, $this->setView('home'), $dadosTemplate)
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
}
