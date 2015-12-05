#!/bin/bash

# to run this scenario, COPIERHOST should be defined (`export COPIERHOST=http://your.copier.host.name`)

http GET "http://$COPIERHOST/?wh=1&md5=1&src=https://www.sendwithus.com/assets/img/email_guide_img/swu-guide.jpg?rev=201511231449" -h | grep -v "Date:"

