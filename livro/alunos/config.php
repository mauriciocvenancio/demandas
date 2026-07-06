<?php
require_once __DIR__ . '/../config.php';

function h($s){ return htmlspecialchars(isset($s) ? $s : '', ENT_QUOTES, 'UTF-8'); }
