#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

http GET "$COPIERSERVICE/?wh=1&md5=1&src=http://mars.nasa.gov/msl/imgs/2015/10/mars-curiosity-rover-msl-big-sky-selfie-portrait-pia19920-full.jpg" -h

