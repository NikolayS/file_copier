#!/bin/bash

# to run this scenario, COPIERHOST should be defined (`export COPIERHOST=http://your.copier.host.name`)

http GET "http://$COPIERHOST/?wh=1&md5=1&src=http://mars.nasa.gov/msl/imgs/2015/10/mars-curiosity-rover-msl-big-sky-selfie-portrait-pia19920-full.jpg" -h | grep -v "Date:"

