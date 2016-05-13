#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

http --form POST "$COPIERSERVICE/?wh=1&md5=1" \
      cache-control:no-cache \
        content-type:application/x-www-form-urlencoded \
          postman-token:ecd808df-a9ac-9847-9b6a-6620f4ee5ca1 \
            src='https://www.google.ru/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png+http://www.underconsideration.com/brandnew/archives/facebook_2015_logo_detail.png+http://images2.fanpop.com/image/photos/10300000/apple-logo-apple-inc-10332560-299-313.jpg' -h | grep -v "Date:"

