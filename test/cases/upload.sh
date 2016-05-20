#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
http -hf POST "$COPIERSERVICE/?wh=1&md5=1" fileRaw@"$path/upload.sample.jpg"

