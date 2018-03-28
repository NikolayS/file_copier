<?php

if (isset($argv[1]) && file_exists($argv[1])) {
    print "Processing: {$argv[1]}\n";
    require './vendor/autoload.php';

    $factory = new \ImageOptimizer\OptimizerFactory();
    $optimizer = $factory->get();

    $optimizer->optimize($argv[1]);
}
