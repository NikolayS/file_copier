#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

echo '-----011000010111000001101001
Content-Disposition: form-data; name="src"

http://img0.liveinternet.ru/images/attach/c/9/106/906/106906542_3925311_jenshina_za_rylyom.jpg
-----011000010111000001101001
Content-Disposition: form-data; name="md5"

1
-----011000010111000001101001
Content-Disposition: form-data; name="wh"

1
-----011000010111000001101001--' |  \
      http POST "$COPIERSERVICE" \
        cache-control:no-cache \
          content-type:'multipart/form-data; boundary=---011000010111000001101001' \
            -h | grep -v "Date:"
