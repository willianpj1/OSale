<?php

declare(strict_types=1);

session_start();

define('ROOT', dirname(__FILE__, 3));
#DIRETÓRIO DAS VIEWS
define('DIR_VIEWS', ROOT . '/App/view');
#EXTENSÃO PADRÃO DAS VIEWS
define('EXT_VIEWS', '.html');
#chave secreta para geração de tokens
define('SECRET_KEY','ccc4b12d-2dee-4531-ba1c-9e9d9ffa406f');
