#!/bin/bash

# to run this scenario, COPIERHOST should be defined (`export COPIERHOST=http://your.copier.host.name`)

http GET "http://$COPIERHOST/?wh=1&md5=1&src=https://media.giphy.com/media/YU92Dp0cTqz3q/giphy.gif" -h | grep -v "Date:"

