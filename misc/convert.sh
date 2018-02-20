#!/bin/bash

processImg () {
  echo "Processing $1";
  filePath=$1
  php ./misc/optim.php $filePath
}
cd ..
export -f processImg
PROCESS_PATH=./data/*.png
find -path "$PROCESS_PATH" -prune -type f -exec bash -c 'processImg "$0"' {} \;
PROCESS_PATH=./data/*.jpg
find -path "$PROCESS_PATH" -prune -type f -exec bash -c 'processImg "$0"' {} \;

