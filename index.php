<?php
define("DEBUG", 1);

$BASEPATH = './storage'; // wuthout trailing slashes!
$DEPTH = 4; // how many dirs will be generated for a file to build dir hierarchy
$SUBDIR_NAME_LENGTH = 2; // how many bytes (0-F) to use for generation of subdir names
$SUPPORTED_EXTENSIONS = 0; // array of supported extensions. To allow everything, set to 0
$SUPPORTED_TYPES = 0; // array of supported ContentTypes. To allow everything, set to 0

set_error_handler(function ($severity, $message, $filepath, $line) {
    throw new Exception($message . " in $filepath, line $line");
}, E_ALL & ~E_STRICT & ~E_NOTICE);

try {
    $src = @$_GET['src'];
    if (!$src) {
        throw new Exception("Required parameter is not set: 'src'.");
    }
    $hash = hash('sha256', $src);
    //$srcExt = preg
    //copy($src, );
    $srcPathInfo = pathinfo(basename($src));
    $ext = @$srcPathInfo['extension'];
    if (($SUPPORTED_EXTENSIONS !== 0) && !$ext) {
        throw new Exception("Files without extension are not allowed (src: '$src').");
    }
    if (($SUPPORTED_EXTENSIONS !== 0) && !in_array($ext, $SUPPORTED_EXTENSIONS)) {
        throw new Exception("File extension '$ext' is not allowed (src: '$src').");
    }
    $headers = get_headers($src);
    $contentType = strtolower(@$headers['content-type']);
    if (($SUPPORTED_TYPES !== 0) && !in_array($contentType, $SUPPORTED_TYPES)) {
        throw new Exception("Content type '$contentType' is not allowed (src: '$src').");
    }
    $dirChunks = array();
    for ($i = 0; $i < $DEPTH; $i++) {
        $dirChunks []= substr($hash, $i * $SUBDIR_NAME_LENGTH, $SUBDIR_NAME_LENGTH);
    }
    $dir = $BASEPATH . '/' . implode('/', $dirChunks);
    if (!file_exists($dir)) {
        mkdir($dir, 0777, TRUE);
    }
    $filename = $hash;
    if ($ext) {
        $filename .= '.' . $ext;
    }
    if (file_exists("$dir/$filename")) {
        // TODO: check md5 of data and compare
    } else {
        copy($src, "$dir/$filename");
    }
    // TODO: return: status, file size, data md5, WxH (in case of img) 
} catch (Exception $e) {
    header("Bad request", true, 400);
    header("X-FILE-COPIER-ERROR: " . str_replace(array("\n", "\r"), array(" ", " "), $e->getMessage()));
    if (defined("DEBUG")) echo $e->getMessage();
    exit;
}

// -- FUNCTIONS --
