<?php

declare(strict_types=1);

# ═══════════════════════════════════════════════
# PSR-1: BASIC CODING STANDARD
# https://www.php-fig.org/psr/psr-1/
# ═══════════════════════════════════════════════

# -----------------------------------------------
# 2.1 - Arquivos devem usar apenas <?php ou <?=
# -----------------------------------------------
arch('PSR-1: arquivos PHP não devem usar short open tags')
    ->expect('App')
    ->toUseStrictTypes(); // Implica uso correto de <?php declare(...)

# -----------------------------------------------
# 2.2 - Arquivos devem usar apenas UTF-8 sem BOM
# (verificado via convenção de strict types)
# -----------------------------------------------
arch('PSR-1: todos os arquivos devem declarar strict types (UTF-8 sem BOM)')
    ->expect('App')
    ->toUseStrictTypes();

# -----------------------------------------------
# 3 - Namespaces e classes devem seguir PSR-4
# -----------------------------------------------
arch('PSR-1: classes devem usar PascalCase (StudlyCaps)')
    ->expect('App')
    ->toHaveProperClassNames(); // Pest Arch valida StudlyCaps automaticamente

# -----------------------------------------------
# 4.1 - Constantes de classe em UPPER_SNAKE_CASE
# -----------------------------------------------
arch('PSR-1: constantes de classe devem ser UPPER_SNAKE_CASE')
    ->expect('App')
    ->toHaveProperConstantNames();

# -----------------------------------------------
# 4.3 - Métodos devem usar camelCase
# -----------------------------------------------
arch('PSR-1: métodos devem usar camelCase')
    ->expect('App')
    ->toHaveProperMethodNames(); // Pest valida que não há snake_case em métodos

# -----------------------------------------------


# -----------------------------------------------
# Namespaces corretos por diretório (PSR-4 + PSR-1)
# -----------------------------------------------
arch('PSR-1: Controllers devem estar no namespace correto')
    ->expect('App')
    ->toBeClasses()
    ->toHaveProperClassNames();