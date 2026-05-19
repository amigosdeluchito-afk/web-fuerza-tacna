<?php
require_once 'config.php';

session_unset();     // borra variables de sesión
session_destroy();   // destruye la sesión

header('Location: login.php');
exit;
