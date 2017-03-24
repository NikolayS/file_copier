#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

http GET "$COPIERSERVICE/?wh=1&md5=1&src=https://lh3.googleusercontent.com/F_rxpKJFEclw3oN6W-ZCnGsu2hb0Tm5cE2FfCKVcdjnyBEosK8b39lVcZX9RFjDivq_4zLCSqT5P=w1920-h1080-rw-no" -h
