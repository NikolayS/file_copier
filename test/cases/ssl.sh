#!/bin/bash

# to run this scenario, COPIERHOST should be defined (`export COPIERHOST=http://your.copier.host.name`)

http GET "http://$COPIERHOST/?wh=1&md5=1&src=https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png" -h | grep -v "Date:"

