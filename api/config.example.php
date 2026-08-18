<?php
// Copie este arquivo como config.php no servidor. Nunca publique a senha no chat,
// Git ou em arquivos acessíveis pelo navegador.
return [
    'db' => [
        'host' => 'localhost',
        'database' => 'u285543561_smartec_app',
        'username' => 'u285543561_smartec_app',
        'password' => 'COLE_A_SENHA_DO_MYSQL_AQUI',
        'charset' => 'utf8mb4',
    ],
    // Crie uma frase longa e exclusiva. Ela libera apenas a criação do primeiro admin.
    'setup_key' => 'TROQUE_POR_UMA_CHAVE_LONGA_E_ALEATORIA',
];
