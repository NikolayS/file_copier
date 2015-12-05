#!/bin/bash

# to run this scenario, COPIERHOST should be defined (`export COPIERHOST=http://your.copier.host.name`)

path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
http -hf POST "http://$COPIERHOST/?wh=1&md5=1" fileRaw@"$path/upload.sample.png" | grep -v "Date:"

