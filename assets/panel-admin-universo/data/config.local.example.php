<?php
// Copiar este archivo como config.local.php y completar los valores privados.
// config.local.php no se versiona y nunca debe incluirse en un commit.
// En la primera migracion, conservar exactamente los secretos actuales.
// Rotar secretos solo despues de comprobar que la externalizacion funciona.

return [
    'DB_HOST' => '',
    'DB_USER' => '',
    'DB_PASS' => '',
    'DB_NAME' => '',

    'IA_HASH_SALT' => '',
    'OPENAI_API_KEY' => '',
    'OPENAI_KEY_ENCRYPTION_SECRET' => '',

    'CRON_SYNC_TOKEN' => '',
];
