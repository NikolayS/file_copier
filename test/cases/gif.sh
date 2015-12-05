#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

http GET "$COPIERSERVICE/?wh=1&md5=1&src=https://media.giphy.com/media/YU92Dp0cTqz3q/giphy.gif" -h | grep -v "Date:"

