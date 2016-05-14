#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

http GET "$COPIERSERVICE/?wh=1&md5=1&src=https://www.sendwithus.com/assets/img/email_guide_img/swu-guide.jpg?rev=201511231449" -h

