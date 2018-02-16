#!/bin/bash

processPng () {
  curDir=`pwd`
  filePath=$1
  echo "Process: $filePath"
  convert $filePath -strip -alpha Remove $filePath.tmp
  mv $filePath.tmp $filePath
}
export -f processPng
PROCESS_PATH=./data/*.png
find -path "$PROCESS_PATH" -prune -type f -exec bash -c 'processPng "$0"' {} \;
