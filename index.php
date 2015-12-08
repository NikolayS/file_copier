<?php
//
// FILE COPIER
//

if (file_exists("config.local.php")) {
    require_once("config.local.php");
} else {
    trigger_error("Config is missing", E_USER_ERROR); 
}

$TMPFILE = '/var/tmp/' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 16);

set_error_handler(function ($severity, $message, $filepath, $line) {
    global $TMPFILE;
    deleteFile($TMPFILE);
    throw new Exception($message . " in $filepath, line $line");
}, E_ALL & ~E_STRICT & ~E_NOTICE & ~E_USER_NOTICE);

try {
    $isGzipped = FALSE;
    $src = @$_GET['src'];
    if ($src) {
        $headers = copyFileAndGetHeaders($src, $TMPFILE, $TIMEOUT, $USERAGENT);
        if (empty($headers)) {
            throw new Exception("Bad response from server (no headers).");
        }

        if (strtolower(@$headers['content-encoding']) == 'gzip') {
            $isGzipped = TRUE;
        }
        if ($isGzipped) {
            rename($TMPFILE, "$TMPFILE.gz");
            system("gunzip $TMPFILE.gz");
            $isGzipped = FALSE;
        }
        $contentType = strtolower(@$headers['content-type']);
        $hash = hash_file('sha256', $TMPFILE);//slow!
        $srcPathInfo = pathinfo(basename($src));
        $ext = preg_replace("/[#\?].*$/", "", @$srcPathInfo['extension']); // pathinfo() function leaves ?blabla or #blabla in "extension"
        if (!preg_match("/^\w{2-4}$/", $ext)) { // allow only 2-, 3-, or 4-lettered ext names
            $imgType = exif_imagetype($TMPFILE); // slow-2!
            $ext = getExtByImgType($imgType);
        }

        if (($SUPPORTED_EXTENSIONS !== 0) && !$ext) {
            throw new Exception("Files without extension are not allowed (src: '$src').");
        }
        if (($SUPPORTED_EXTENSIONS !== 0) && !in_array($ext, $SUPPORTED_EXTENSIONS)) {
            throw new Exception("File extension '$ext' is not allowed (src: '$src').");
        }
    } elseif (isset($_FILES['fileRaw']) && $fileRaw = $_FILES['fileRaw']) { // file upload
        //$data = file_get_contents($fileRaw['tmp_name']);
        $hash = hash_file('sha256', $fileRaw['tmp_name']);
        $imgType = exif_imagetype($fileRaw['tmp_name']); // TODO:  work not only with images!
        $contentType = image_type_to_mime_type($imgType); // TODO: work not only with images!
        $ext = getExtByImgType($imgType);
        $TMPFILE = $fileRaw['tmp_name'];
    } else {
        throw new Exception("Neither GET 'src' nor FILE 'fileRaw' is provided!");
    }
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
        copy($TMPFILE, "$dir/$filename");
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
    deleteFile($TMPFILE);
    header("X-File-Copier-Size: " . filesize("$dir/$filename"));
    header("X-Location: $uri/$filename");
} catch (Exception $e) {
    deleteFile($TMPFILE);
    header("Bad request", true, 400);
    header("X-FILE-COPIER-ERROR: " . str_replace(array("\n", "\r"), array(" ", " "), $e->getMessage()));
    if ($DEBUG) echo $e->getMessage();
    exit;
}

// -- FUNCTIONS --
/*
 * @see http://stackoverflow.com/questions/4635936/super-fast-getimagesize-in-php
 */
function ranger($url)
{
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

function getExtByImgType($imgType)
{
    $extensions = array(
        IMAGETYPE_GIF => "gif",
        IMAGETYPE_JPEG => "jpg",
        IMAGETYPE_PNG => "png",
        IMAGETYPE_SWF => "swf",
        IMAGETYPE_PSD => "psd",
        IMAGETYPE_BMP => "bmp",
        IMAGETYPE_TIFF_II => "tiff",
        IMAGETYPE_TIFF_MM => "tiff",
        IMAGETYPE_JPC => "jpc",
        IMAGETYPE_JP2 => "jp2",
        IMAGETYPE_JPX => "jpx",
        IMAGETYPE_JB2 => "jb2",
        IMAGETYPE_SWC => "swc",
        IMAGETYPE_IFF => "iff",
        IMAGETYPE_WBMP => "wbmp",
        IMAGETYPE_XBM => "xbm",
        IMAGETYPE_ICO => "ico"
    );
    if (@$extensions[$imgType]) {
        return $extensions[$imgType];
    } else {
        return null;
    }
}

function parseHeaders($headers, $lowerNames = true)
{
    $res = array();
    if (!is_array($headers)) {
        $headers = explode("\n", $headers);
    }
    foreach ($headers as $h) {
        if (preg_match("/^([^\:]+)\:(.*)$/", $h, $matches)) {
            if ($lowerNames) {
                $matches[1] = strtolower($matches[1]);
            }
            $res[$matches[1]] = trim($matches[2]);
            unset($matches);
        }
    }
    return $res;
}

function deleteFile($filename)
{
    if (file_exists($filename)) {
        unlink($filename);
    }
}

function copyFileAndGetHeaders($url, $path, $timeout = 0, $useragent = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    if ($useragent) {
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
    }
    $data = curl_exec($ch);
    $headersLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($data, 0, $headersLen); // what about UTF8??
    $body = substr($data, $headersLen);
    file_put_contents($path, $body);
    
    return parseHeaders($headers);
}


// The following is pretty slow. TODO: speedup
// See also http://stackoverflow.com/questions/11066857/detect-eol-type-using-php
function detectEol($str, $default='')
{
    $res = "\n";
    $pos = mb_strpos($str, $res);
    if (ord($str[$pos - 1]) == 13) {
        $res = "\r\n";
    }
    return $res;
}
