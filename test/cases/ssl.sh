#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

http GET "$COPIERSERVICE/?wh=1&md5=1&src=https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png" -h 

