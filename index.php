<?php
define("DEBUG", 1);

$PATH = './storage'; // wuthout trailing slashes!
$DEPTH = 4; // how many dirs ($DEPTH - 1) will be generated for a file to build dir hierarchy


set_error_handler(function ($severity, $message, $filepath, $line) {
    throw new Exception($message . " in $filepath, line $line");
}, E_ALL & ~E_STRICT & ~E_NOTICE);

try {
    $src = @$_GET['src'];
    if (!$src) {
        throw new Exception("Required parameter is not set: 'src'.");
    }
    $hash = hash('sha256', $src);
    echo $hash; exit;

} catch (Exception $e) {
    header("Bad request", true, 400);
    header("X-FILE-COPIER-ERROR: " . str_replace(array("\n", "\r"), array(" ", " "), $e->getMessage()));
    if (defined("DEBUG")) echo $e->getMessage();
    exit;
}
