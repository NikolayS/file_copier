<?php
// COPY THIS TO config.php:
//        cp config.local.example.php config.local.php
// 
// ..AND EDIT IT AS YOU NEED

$DEBUG = FALSE;                 // do not use TRUE on production!! It might expose show error details to the world

$BASEPATH = './data';        // where storage lives. Wuthout trailing slashes!
$BASEURI = '/data';          // http URI location of $BASEPATH
$DEPTH = 4;                     // how many dirs will be generated for a file to build dir hierarchy
$SUBDIR_NAME_LENGTH = 2;        // how many bytes (0-F) will be used for generation of subdir names
$SUPPORTED_EXTENSIONS = 0;      // array of supported extensions. To allow everything, set to 0
$SUPPORTED_TYPES = 0;           // array of supported ContentTypes. To allow everything, set to 0

