<?php
//
// FILE COPIER
//
$start = microtime(true);

if (file_exists("config.local.php")) {
  require_once("config.local.php");
} else {
  trigger_error("Config is missing", E_USER_ERROR);
}

if (isset($_GET['DEBUG']) && $_GET['DEBUG']) {
  $DEBUG = 1;
}
if (!isset($DEBUG)) {
  $DEBUG = 0;
}

if (! isset($TMP_PATH)) {
  $TMP_PATH = '/var/tmp';
}
$TMPFILES = array();


if ($DEBUG) {
  $errFlags = E_ALL;
  header("X-File-Copier-Debug-Mode: Debug mode is on. See the log for details.");
} else {
  $errFlags = E_ALL & ~E_STRICT & ~E_NOTICE & ~E_USER_NOTICE;
}
set_error_handler(function ($severity, $message, $filepath, $line) {
  global $TMPFILES;
  foreach ($TMPFILES as $tmpfile) {
    deleteFile($tmpfile);
  }
  logTime("Error handler triggered: $message");
  throw new Exception($message . " in $filepath, line $line");
}, $errFlags);

logTime("Start at line " . ' ' . __LINE__);
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
        logTime("Invoke (in loop) saveFileByURL($url)" . ' ' . __LINE__);
        $res = saveFileByURL($url);
        if (! empty($res)) {
          $results[] = $res;
        }
      }
      logTime("Invoke mergeImages(..)" . ' ' . __LINE__);
      $result = mergeImages($results);
    } else {
      if (is_array($src) && count($src) == 1) {
        $src = $src[0];
      }
      logTime("Invoke saveFileByURL($src)" . ' ' . __LINE__);
      $result = saveFileByURL($src);
    }
    logTime("Saving phase completed" . ' ' . __LINE__);
    extract($result);
  } elseif (isset($_FILES['fileRaw']) && $fileRaw = $_FILES['fileRaw']) { // file upload
    //$data = file_get_contents($fileRaw['tmp_name']);
    if (!isset($_FILES['fileRaw']['tmp_name']) || empty($_FILES['fileRaw']['tmp_name'])) {
      throw new Exception("File upload failed (tmp_name is missing)");
    }
    $hash = hash_file('sha256', $fileRaw['tmp_name']);
    $imgType = exif_imagetype($fileRaw['tmp_name']);
    $contentType = image_type_to_mime_type($imgType);
    if (($SUPPORTED_TYPES !== 0) && !in_array($contentType, $SUPPORTED_TYPES)) {
      throw new Exception("Content type '$contentType' is not allowed (src: '$src').");
    }
    logTime("Invoke getExtByImgType($imgType)" . ' ' . __LINE__);
    $ext = getExtByImgType($imgType);
    $TMPFILES []= $fileRaw['tmp_name'];
  } elseif (isset($_POST['fileBase64'])) {
    logTime("Receive image as base64 code" . ' ' . __LINE__);
    $fileName = $TMP_PATH . '/' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 16);
    $data = base64_decode($_POST['fileBase64'], TRUE);
    if (!$data) {
        throw new Exception("Base46 content decode failed.");
    }
    $TMPFILES[] = $fileName;
    file_put_contents($fileName, $data);
    $hash = hash_file('sha256', $fileName);
    $imgType = exif_imagetype($fileName);
    $contentType = image_type_to_mime_type($imgType);
    if (($SUPPORTED_TYPES !== 0) && !in_array($contentType, $SUPPORTED_TYPES)) {
      throw new Exception("Content type '$contentType' is not allowed (src: '$src').");
    }
    logTime("Invoke getExtByImgType($imgType)" . ' ' . __LINE__);
    $ext = getExtByImgType($imgType);
  } else {
    throw new Exception("Neither GET 'src' nor FILE 'fileRaw' is provided!");
  }

  logTime("Start dir/file management" . ' ' . __LINE__);
  assert(isset($hash));
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
    logTime("File exists/found" . ' ' . __LINE__);
  } else {
    logTime("Copy tmp file to final destination $dir/$filename" . ' ' . __LINE__);
    copy(end($TMPFILES), "$dir/$filename");
  }
  //$requestHeaders = http_get_request_headers();
  if ((isset($_GET['md5']) && $_GET['md5']) || (isset($_POST['md5']) && $_POST['md5'])) {
    $md5 = md5_file("$dir/$filename");
    header("X-File-Copier-Md5: $md5");
  }
  if ((isset($_GET['wh']) && $_GET['wh']) || (isset($_POST['wh']) && $_POST['wh'])) {
    $wh = getimagesize("$dir/$filename");
    header("X-File-Copier-Img-Width: {$wh[0]}");
    header("X-File-Copier-Img-Height: {$wh[1]}");
  }
  logTime("Cleanup" . ' ' . __LINE__);
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
  if ($DEBUG) {
    logTime("EXCEPTION: " . $e->getMessage() . ' ' . __LINE__);
  }
}

logTime("All done." . ' ' . __LINE__);

// -- FUNCTIONS --
function saveFileByURL($src)
{
  global $TIMEOUT, $USERAGENT, $SUPPORTED_EXTENSIONS, $SUPPORTED_TYPES, $TMP_PATH; // config
  global $TMPFILES;
  $TMPFILES []= $TMP_PATH . '/' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 16);
  $res = array();
  $src = trim($src);
  if (substr($src, 0, 2) === '//') {
    $src = "http:$src";
  }
  if (strpos($src, " ") !== false) {
    $src = str_replace(" ", "%20", $src);
  }
  logTime("saveFileByURL: Invoke copyFileAndGetHeaders($src, ...)" . ' ' . __LINE__);
  $res['headers'] = copyFileAndGetHeaders($src, end($TMPFILES), $TIMEOUT, $USERAGENT);
  logTime("saveFileByURL: End of copyFileAndGetHeaders($src, ...)" . ' ' . __LINE__);
  if (empty($res['headers'])) {
    throw new Exception("Bad response from server (no headers related the file $src).");
  }

  if (isset($res['headers']) && isset($res['headers']['content-encoding']) && strtolower($res['headers']['content-encoding']) == 'gzip') {
    $res['isGzipped'] = TRUE;
  }
  if (isset($res['isGzipped']) && $res['isGzipped']) {
    logTime("saveFileByURL: File is gzipped, gunzip it" . ' ' . __LINE__);
    $tmpfile = end($TMPFILES);
    rename($tmpfile, "$tmpfile.gz");
    system("gunzip $tmpfile.gz");
    $res['isGzipped'] = FALSE;
    logTime("saveFileByURL: gunzip done" . ' ' . __LINE__);
  }
  if (isset($res['headers']['content-type']) && $res['headers']['content-type'] == 'image/webp') {
    logTime("saveFileByURL: Begin work with webp image $tmpfile" . ' ' . __LINE__);
    $tmpfile = end($TMPFILES);
    $im = imagecreatefromwebp($tmpfile);
    $TMPFILES[]= $TMP_PATH . '/' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 16);
    imagejpeg($im, end($TMPFILES), 100);
    imagedestroy($im);
    logTime("saveFileByURL: Complete work with webp image $tmpfile" . ' ' . __LINE__);
  }
  $res['tmpFileName'] = end($TMPFILES);
  $res['contentType'] = strtolower(@$res['headers']['content-type']);
  if (($SUPPORTED_TYPES !== 0) && !in_array(@$res['contentType'], $SUPPORTED_TYPES)) {
    throw new Exception("Content type '{$res['contentType']}' is not allowed (src: '$src').");
  }
  logTime("saveFileByURL: Invoke hash_file('sha256', ...) (slow!)" . ' ' . __LINE__);
  $res['hash'] = hash_file('sha256', end($TMPFILES));//slow!
  logTime("saveFileByURL: hash_file('sha256', ...) completed" . ' ' . __LINE__);
  $res['srcPathInfo'] = pathinfo(basename($src));
  $res['ext'] = preg_replace("/[#\?].*$/", "", @$res['srcPathInfo']['extension']); // pathinfo() function leaves ?blabla or #blabla in "extension"
  if (!preg_match("/^\w{2-4}$/", $res['ext'])) { // allow only 2-, 3-, or 4-lettered ext names
    logTime("saveFileByURL: Invoke exif_imagetype(..) (slow!)" . ' ' . __LINE__);
    $res['imgType'] = exif_imagetype(end($TMPFILES)); // slow-2!
    logTime("saveFileByURL: exif_imagetype(..) completed, invoke getExtByImgType({$res['imgType']})" . ' ' . __LINE__);
    $res['ext'] = getExtByImgType($res['imgType']);
    logTime("saveFileByURL: getExtByImgType({$res['imgType']}) completed" . ' ' . __LINE__);
  }

  if (($SUPPORTED_EXTENSIONS !== 0) && !$res['ext']) {
    throw new Exception("Files without extension are not allowed (src: '$src').");
  }
  if (($SUPPORTED_EXTENSIONS !== 0) && !in_array($res['ext'], $SUPPORTED_EXTENSIONS)) {
    throw new Exception("File extension '$ext' is not allowed (src: '$src').");
  }

  logTime("saveFileByURL: Done, returning result" . ' ' . __LINE__);
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
  }

  return null;
}

function parseHeaders($headers, $lowerNames = true)
{
  $res = array();
  if (!is_array($headers)) {
    $headers = explode("\n", $headers);
  }
  foreach ($headers as $hdr) {
    if (preg_match("/^([^\:]+)\:(.*)$/", $hdr, $matches)) {
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
  $chan = curl_init();
  curl_setopt($chan, CURLOPT_URL, $url);
  curl_setopt($chan, CURLOPT_HEADER, 1);
  curl_setopt($chan, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($chan, CURLOPT_RETURNTRANSFER, 1);
  //curl_setopt($chan, CURLOPT_BINARYTRANSFER, true);
  curl_setopt($chan, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($chan, CURLOPT_CONNECTTIMEOUT, $timeout);
  if ($useragent) {
    curl_setopt($chan, CURLOPT_USERAGENT, $useragent);
  }
  $data = curl_exec($chan);
  $httpcode = curl_getinfo($chan, CURLINFO_HTTP_CODE);
  if ($httpcode >= 400) {
    return array();
  }
  $headersLen = curl_getinfo($chan, CURLINFO_HEADER_SIZE);
  curl_close($chan);
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
  $hgh=0;
  foreach ($files as $key => &$file) {
    imagecopyresampled($dstImage, $file['image'], 0, $hgh, 0, 0, $width, $file['newHeight'], $file['width'], $file['height']);
    $hgh += $file['newHeight'] + 1;
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

function logTime($message) {
  global $DEBUG;
  if (@$DEBUG) {
    global $LOG_DIR, $start;
    if (!isset($LOG_DIR)) {
      throw new Exception("Cannot write to the log: \$LOG_DIR is not set.");
    }
    $logFile = "$LOG_DIR/file_copier.log";
    if (!is_writable($logFile)) {
      throw new Exception("Cannot write to the log: file '$logFile' is not writable.");
    }
    file_put_contents(
      "$LOG_DIR/file_copier.log",
      date("c") . " [" . posix_getpid() . "] Duration from start: " . (round(microtime(true) - $start, 4)) . " \t" . $message . "\n",
      FILE_APPEND
    );
  }
}
