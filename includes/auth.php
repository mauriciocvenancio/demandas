<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function auth_user(){
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login(){
    if (!auth_user()) {
        redirect('/login.php');
    }
}

function login_user($userRow){
    $_SESSION['user'] = array(
        'id' => (int)$userRow['id'],
        'nome' => $userRow['nome'],
        'email' => $userRow['email'],
        'tipo' => $userRow['tipo'],
        'id_cliente' => $userRow['id_cliente']
    );
}

function logout_user(){
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
}
