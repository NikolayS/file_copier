#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

http GET "$COPIERSERVICE/?wh=1&md5=1&src=https://habrastorage.org/getpro/habr/post_images/fe7/5a1/3bb/fe75a13bbbd36c015b34a41bb0df990b.jpg" -h

