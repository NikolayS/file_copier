<?php
//
// FILE COPIER
//
if (file_exists("config.local.php")) {
    require_once("config.local.php");
} else {
    trigger_error("Config is missing", E_USER_ERROR); 
}


set_error_handler(function ($severity, $message, $filepath, $line) {
    global $tmpFileName;
    deleteFile($tmpFileName);
    throw new Exception($message . " in $filepath, line $line");
}, E_ALL & ~E_STRICT & ~E_NOTICE & ~E_USER_NOTICE);

try {
    $isGzipped = FALSE;
    $src = isset($_GET['src']) ? $_GET['src'] : null;

    if (!$src) {
        $src = isset($_POST['src']) ? $_POST['src'] : null;
    }

    if ($src) {
    	if (is_array($src)) {
    		$results = array();
    		foreach ($src as $key => $url) {
    			$results[] = saveFileByURL($url);
    		}
    		$result = mergeImages($results); 
    		
    	} else {
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
        //$TMPFILE = $fileRaw['tmp_name'];
        $tmpFileName = $fileRaw['tmp_name'];
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
        copy($tmpFileName, "$dir/$filename");
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
    deleteFile($tmpFileName);
    header("X-File-Copier-Size: " . filesize("$dir/$filename"));
    header("X-Location: $uri/$filename");
} catch (Exception $e) {
    deleteFile($tmpFileName);
    header("Bad request", true, 400);
    header("X-FILE-COPIER-ERROR: " . str_replace(array("\n", "\r"), array(" ", " "), $e->getMessage()));
    if ($DEBUG) echo $e->getMessage();
    exit;
}

// -- FUNCTIONS --
function saveFileByURL($src)
{
    global $TMPFILE; // not good, but need to keep it global to handle errors/exceptions
    global $TIMEOUT, $USERAGENT, $SUPPORTED_EXTENSIONS, $SUPPORTED_TYPES, $TMP_PATH; // config
    $TMPFILE = $TMP_PATH . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 16);
    $res = array();
    $res['headers'] = copyFileAndGetHeaders($src, $TMPFILE, $TIMEOUT, $USERAGENT);
    if (empty($res['headers'])) {
        throw new Exception("Bad response from server (no headers).");
    }

    if (strtolower(@$res['headers']['content-encoding']) == 'gzip') {
        $res['isGzipped'] = TRUE;
    }
    if ($res['isGzipped']) {
        rename($TMPFILE, "$TMPFILE.gz");
        system("gunzip $TMPFILE.gz");
        $res['isGzipped'] = FALSE;
    }
    $res['tmpFileName'] = $TMPFILE;
    $res['contentType'] = strtolower(@$res['headers']['content-type']);
    if (($SUPPORTED_TYPES !== 0) && !in_array(@$res['contentType'], $SUPPORTED_TYPES)) {
        throw new Exception("Content type '{$res['contentType']}' is not allowed (src: '$src').");
    }
    $res['hash'] = hash_file('sha256', $TMPFILE);//slow!
    $res['srcPathInfo'] = pathinfo(basename($src));
    $res['ext'] = preg_replace("/[#\?].*$/", "", @$res['srcPathInfo']['extension']); // pathinfo() function leaves ?blabla or #blabla in "extension"
    if (!preg_match("/^\w{2-4}$/", $res['ext'])) { // allow only 2-, 3-, or 4-lettered ext names
        $res['imgType'] = exif_imagetype($TMPFILE); // slow-2!
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
    $headersLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($data, 0, $headersLen); // what about UTF8??
    $body = substr($data, $headersLen);
    file_put_contents($path, $body);
    
    return parseHeaders($headers);
}

function mergeImages($files) {
	// load images
	$width = 2147483647;
	foreach ($files as $key => &$file) {
		$imgData = loadImage($file['tmpFileName']);
		$file = array_merge($file, $imgData);
		if ($width > $file['imageInfo'][0]) {
			$width = $file['imageInfo'][0];
		}
	}
	if ($width < MIN_IMAGE_WIDTH) {
		$width = MIN_IMAGE_WIDTH;
	}
	$height = 0;
	foreach ($files as $key => &$file) {
		$file['image'] = resizeImage($file['image'], $file['imageInfo'], $width);
		$height += $file['imageInfo'][1];
	}
	$dstImage = imagecreatetruecolor($width, $height);
	$h=0;
	foreach ($files as $key => &$file) {
	//				      ($dst_image, $src_image,     $dst_x, $dst_y,   $src_x, $src_y, $dst_w, $dst_h,                $src_w,                $src_h) {}	
		imagecopyresampled($dstImage,  $file['image'], 0,      $h,       0,      0,      $width, $file['imageInfo'][1], $file['imageInfo'][0], $file['imageInfo'][1]);
		$h += $file['imageInfo'][1]; 
	}

	header('Content-type: image/jpeg');
	imagejpeg($dstImage);
	
	var_dump($files);
}

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

function resizeImage($originalImage, &$imageInfo, $newWidth) {
	$width = $imageInfo['0'];
	$height = $imageInfo['1'];
	$newHeight = ($newWidth / $width) * $height; 
	$newImage = imagecreatetruecolor($newWidth, $newHeight);
	$res = imagecopyresampled($newImage, $originalImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
	
	$imageInfo[0] = $newWidth;
	$imageInfo[1] = $newHeight;
	return $newImage;	
}