<?php

declare(strict_types=1);

namespace App\Controller;

use App\Trait\DatabaseValueNormalizer;
use App\Trait\Response;
use App\Trait\Template;

abstract class Base
{
    use Template, Response, DatabaseValueNormalizer;

    protected function usuarioLogado(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION['user'] ?? ['nome' => '', 'id' => null];
    }
}