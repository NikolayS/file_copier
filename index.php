<?php
set_error_handler(function ($severity, $message, $filepath, $line) {
    throw new Exception($message . " in $filepath, line $line");
}, E_ALL & ~E_STRICT & ~E_NOTICE);

// These params may be overwritten in config.local.php. See config.***.php files! 
// Params are also commented there.
$DEBUG = FALSE;
$BASEPATH = './storage';
$BASEURI = '/storage';
$DEPTH = 4; 
$SUBDIR_NAME_LENGTH = 2; 
$SUPPORTED_EXTENSIONS = 0; 
$SUPPORTED_TYPES = 0; 

if (file_exists("config.local.php")) {
    require_once("config.local.php");
}

try {
    $src = @$_GET['src'];
    if (!$src) {
        throw new Exception("Required parameter is not set: 'src'.");
    }
    $hash = hash('sha256', $src);
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
    $uri = $BASEURI . '/' . implode('/', $dirChunks);
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
    //$requestHeaders = http_get_request_headers();
    if (@$_GET['md5'] || @$_POST['md5']) {
        $md5 = md5_file("$dir/$filename");
        header("X-File-Copier-Md5: $md5");
    }
    if (@$_GET['wh'] || @$_POST['wh']) {
        $wh = getimagesize("$dir/$filename");
        header("X-File-Copier-Img-Width: {$wh[0]}");
        header("X-File-Copier-Img-Height: {$wh[1]}");
    }
    header("X-File-Copier-Size: " . filesize("$dir/$filename"));
    header("X-Location: $uri/$filename");
    // TODO: return: status, file size, data md5, WxH (in case of img) 
} catch (Exception $e) {
    header("Bad request", true, 400);
    header("X-FILE-COPIER-ERROR: " . str_replace(array("\n", "\r"), array(" ", " "), $e->getMessage()));
    if ($DEBUG) echo $e->getMessage();
    exit;
}

// -- FUNCTIONS --
/*
 * @see http://stackoverflow.com/questions/4635936/super-fast-getimagesize-in-php
 */
function ranger($url){
    $headers = array(
        "Range: bytes=0-32768"
    );

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    curl_close($curl);
    return $data;
}
