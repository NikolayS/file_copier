# file_copier
Simple file copier: for a file given by URL, saves it locally. Also supports file uploads. Written in PHP.

If you are going to use with to store images, consider using https://github.com/NikolayS/image_resizer which is able to resize images on the fly and is fully compatible with this project.

## Installation
Installation is straightforward:
* configure a new host in your Nginx/Apache/etc
* create config file using `cp config.local.php.SAMPLE config.local.php` and edit it
* create writable data directory and put path to in into config file

That's it!

## Automated Tests
To be able to run automated tests, two additional steps are required:
* install https://github.com/jkbrzt/httpie
* configure `COPIERSERVICE` environmental variable (example: `export COPIERSERVICE=https://your.copier.hostname`, without trailing slash).

To run tests, use:
```
test/run.sh
``` 

It prints detailed output to STDOUT (use `test/run.sh | ts` if you want timestamps, `ts` requires package `moreutils` being installed in your system). In case of any test failure, it also outputs to STDER, one line per each failed test (this line will come with timestamp by default).

## Security Issues
Do not forget, that this tiny microservice comes without any authentication, so make sure that you do not expose it to the world *as is*, overwise your server will be at risk of data bloating (everyone will be able to upload/clone any amount of data and you will eventually run of of the disk space). So either keep it internal-only (using firewall) or add some kind of authentication.

Enjoy!
