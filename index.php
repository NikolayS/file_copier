<?php
//
// FILE COPIER
//
if (file_exists("config.local.php")) {
    require_once("config.local.php");
} else {
    trigger_error("Config is missing", E_USER_ERROR); 
}

if (! isset($TMP_PATH)) {
	$TMP_PATH = '/var/tmp';
}
$TMPFILES = array();

set_error_handler(function ($severity, $message, $filepath, $line) {
    global $TMPFILES;
    foreach ($TMPFILES as $f) {
        deleteFile($f);
    }
    throw new Exception($message . " in $filepath, line $line");
}, E_ALL & ~E_STRICT & ~E_NOTICE & ~E_USER_NOTICE);

$results = array();
try {
    $isGzipped = FALSE;
    $src = isset($_GET['src']) ? $_GET['src'] : null;

    if (!$src) {
        $src = isset($_POST['src']) ? $_POST['src'] : null;
    }

    if ($src) {
    	if (! is_array($src) && strpos($src, "+") !== FALSE ) {
	    	$src = explode("+", $src);
    	}
    	if (is_array($src) && count($src) > 1) {
    		$results = array();
    		foreach ($src as $key => $url) {
    			if (empty($url)) {
    				continue;
    			}
    			$res = saveFileByURL($url);
    			if (! empty($res)) {
    				$results[] = $res;
    			}
    		}
    		$result = mergeImages($results); 
    	} else {
    		if (is_array($src) && count($src) == 1) {
    			$src = $src[0];
    		}
    		$result = saveFileByURL($src);
    	}
        extract($result);
    } elseif (isset($_FILES['fileRaw']) && $fileRaw = $_FILES['fileRaw']) { // file upload
        //$data = file_get_contents($fileRaw['tmp_name']);
        $hash = hash_file('sha256', $fileRaw['tmp_name']);
        $imgType = exif_imagetype($fileRaw['tmp_name']); // TODO:  work not only with images!
        $contentType = image_type_to_mime_type($imgType); // TODO: work not only with images!
        if (($SUPPORTED_TYPES !== 0) && !in_array($contentType, $SUPPORTED_TYPES)) {
            throw new Exception("Content type '$contentType' is not allowed (src: '$src').");
        }
        $ext = getExtByImgType($imgType);
        $TMPFILES []= $fileRaw['tmp_name'];
    } else {
        throw new Exception("Neither GET 'src' nor FILE 'fileRaw' is provided!");
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
        copy(end($TMPFILES), "$dir/$filename");
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
    foreach ($TMPFILES as $f) {
        deleteFile($f);
    }    
    header("X-File-Copier-Size: " . filesize("$dir/$filename"));
    header("X-Location: $uri/$filename");
    header("Access-Control-Expose-Headers: X-File-Copier-Size, X-Location, X-File-Copier-Img-Height, X-File-Copier-Img-Width, X-File-Copier-Md5");
} catch (Exception $e) {
    foreach ($TMPFILES as $f) {
        deleteFile($f);
    }    
    foreach ($results as $key => $file) {
    	if (isset($file['tmpFileName'])) {
    		deleteFile($file['tmpFileName']);
    	}
    }
    header("Bad request", true, 400);
    header("X-FILE-COPIER-ERROR: " . str_replace(array("\n", "\r"), array(" ", " "), $e->getMessage()));
   	header("Access-Control-Expose-Headers: X-FILE-COPIER-ERROR");    
    if ($DEBUG) echo $e->getMessage();
    exit;
}

// -- FUNCTIONS --
function saveFileByURL($src)
{
    global $TIMEOUT, $USERAGENT, $SUPPORTED_EXTENSIONS, $SUPPORTED_TYPES, $TMP_PATH; // config
    global $TMPFILES;
    $TMPFILES []= $TMP_PATH . '/' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 16);
    $res = array();
    $res['headers'] = copyFileAndGetHeaders($src, end($TMPFILES), $TIMEOUT, $USERAGENT);
    if (empty($res['headers'])) {
        throw new Exception("Bad response from server (no headers related the file $src).");
    }

    if (strtolower(@$res['headers']['content-encoding']) == 'gzip') {
        $res['isGzipped'] = TRUE;
    }
    if (isset($res['isGzipped']) && $res['isGzipped']) {
        $f = end($TMPFILES);
        rename($f, "$f.gz");
        system("gunzip $f.gz");
        $res['isGzipped'] = FALSE;
    }
    $res['tmpFileName'] = end($TMPFILES);
    $res['contentType'] = strtolower(@$res['headers']['content-type']);
    if (($SUPPORTED_TYPES !== 0) && !in_array(@$res['contentType'], $SUPPORTED_TYPES)) {
        throw new Exception("Content type '{$res['contentType']}' is not allowed (src: '$src').");
    }
    $res['hash'] = hash_file('sha256', end($TMPFILES));//slow!
    $res['srcPathInfo'] = pathinfo(basename($src));
    $res['ext'] = preg_replace("/[#\?].*$/", "", @$res['srcPathInfo']['extension']); // pathinfo() function leaves ?blabla or #blabla in "extension"
    if (!preg_match("/^\w{2-4}$/", $res['ext'])) { // allow only 2-, 3-, or 4-lettered ext names
        $res['imgType'] = exif_imagetype(end($TMPFILES)); // slow-2!
        $res['ext'] = getExtByImgType($res['imgType']);
    }

    if (($SUPPORTED_EXTENSIONS !== 0) && !$res['ext']) {
        throw new Exception("Files without extension are not allowed (src: '$src').");
    }
    if (($SUPPORTED_EXTENSIONS !== 0) && !in_array($res['ext'], $SUPPORTED_EXTENSIONS)) {
        throw new Exception("File extension '$ext' is not allowed (src: '$src').");
    }

    return $res;
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
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpcode >= 400) {
    	return array();
    }
    $headersLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($data, 0, $headersLen); // what about UTF8??
    $body = substr($data, $headersLen);
    file_put_contents($path, $body);
    
    return parseHeaders($headers);
}

/**
 * Resize and merger images to one image by min width
 * @param $files array with file paths
 * @return new file data array
 */
function mergeImages($files) {
    global $TMPFILES;
    global $MIN_IMAGE_WIDTH, $TMP_PATH; // from config
	$res = array();
	$TMPFILES []= $TMP_PATH . '/' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 16);
	$res['tmpFileName'] = end($TMPFILES);
	
	// load images
	$width = 2147483647;
	foreach ($files as $key => &$file) {
		$imgData = loadImage($file['tmpFileName']);
		if (empty($imgData)) {
			throw new Exception("Bad image.");			
		}
		$file = array_merge($file, $imgData);
		$file['width'] = $file['imageInfo'][0];
		$file['height'] = $file['imageInfo'][1];
		if ($width > $file['width']) {
			$width = $file['width'];
		}
	}
	if ($width < $MIN_IMAGE_WIDTH) {
		$width = $MIN_IMAGE_WIDTH;
	}
	$height = 0;
	foreach ($files as $key => &$file) {
		$file['newWidth'] = $width;
		if ($file['width']) {
			$file['newHeight'] = ($width / $file['width']) * $file['height'];
			$height += $file['newHeight'];
		}
	}
	$height += count($files) - 1;
	$dstImage = imagecreatetruecolor($width, $height);
	imagefill($dstImage, 0, 0, 0xFFFFFF);
	$h=0;
	foreach ($files as $key => &$file) {
		imagecopyresampled($dstImage, $file['image'], 0, $h, 0, 0, $width, $file['newHeight'], $file['width'], $file['height']);
		$h += $file['newHeight'] + 1;
		imagedestroy($file['image']);
		deleteFile($file['tmpFileName']);
	}

	imagepng($dstImage, $res['tmpFileName']);
	$res['contentType'] = 'image/png';
	$res['hash'] = hash_file('sha256', $res['tmpFileName']);
	$res['srcPathInfo'] = pathinfo(basename($src));
	$res['ext'] = 'png';
	$res['imgType'] = 3;
	imagedestroy($dstImage);
	return $res;
}

/**
 * Load image from file
 * @param $filename path to file
 * @return array with image handler, image type and imageInfo array
 */
function loadImage($filename) {
	$image = false;
	$imageInfo = getimagesize($filename);
	$imageType = '';
	$imageType = $imageInfo[2];
	if( $imageType == IMAGETYPE_JPEG ) {
		$image = imagecreatefromjpeg($filename);
	} elseif( $imageType == IMAGETYPE_GIF ) {
		$image = imagecreatefromgif($filename);
	} elseif( $imageType == IMAGETYPE_PNG ) {
		$image = imagecreatefrompng($filename);
	}	
	return $image ? array('image' => $image, 'imgType' => $imageType, 'imageInfo' => $imageInfo) : array();
}
