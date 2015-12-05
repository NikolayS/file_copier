#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

http GET "$COPIERSERVICE/?wh=1&md5=1&src=http://img0.liveinternet.ru/images/attach/c/9/106/906/106906542_3925311_jenshina_za_rylyom.jpg" -h | grep -v "Date:"

