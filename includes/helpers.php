<?php
function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect($path){
    header('Location: ' . BASE_URL . $path);
    exit;
}
