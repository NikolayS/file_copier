# file_copier
Simple file copier: for a file given by URL, saves it locally. Written in PHP

Installtion is straightforward:
* configure a new host in tour Nginx/Apache/etc
* create config file using `cp config.local.php.SAMPLE config.local.php` and edit it
* create writable data directory and put path to in into config file

That's it!

For automated testing, use `test/run.sh` (https://github.com/jkbrzt/httpie should be installed). It prints to STDOUT (use `test/run.sh | ts` if you want timestamps) details. In case of any test failurem it outputs to STDER, one line per each failed test (this line will come with timestamp by default).

Do not forget, that this tiny microservice comes without any authentication, so make sure that you do not expose it to the world /as is/, overwise your server will be at risk of data bloating. So either keep it internal-only (using firewall) or add some kind of authentication.

Enjoy!
