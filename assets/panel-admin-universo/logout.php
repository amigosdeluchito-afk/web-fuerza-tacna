<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

require_csrf();

session_unset();     // borra variables de sesion
session_destroy();   // destruye la sesion

header('Location: login.php');
exit;
