#!/bin/bash

#
# How to run (examples)
# ./test/generate_expected.sh gif     - regenerates 'test/cases/gif.expected'
# test/generate_expected.sh ALL       - regenerates AL expected data ('test/cases/*.expected')
#

program=http
condition=$(which $program 2>/dev/null | grep -v "not found" | wc -l)
if [ $condition -eq 0 ] ; then
    >&2 echo "\"$program\" tool is missing! install HTTPie (\"pip install --upgrade httpie\" or \"brew install httpie\")"
    exit 1
fi
if [ "$COPIERSERVICE" == "" ] ; then
    >&2 echo "COPIERSERVICE is missing (use \"export COPIERSERVICE=https://your.copier.hostname\", w/o trailing slash)"
    exit 1
fi

path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )

if [ "$1" == "" ] ; then
    >&2 echo "Case name is missing"
    exit 1
elif [ "$1" == "ALL" ] ; then
    for f in $(ls "$path/cases/"*.sh) ; do
        $($f | grep -vi "^Date:" | grep -vi "^X-Powered-By:" | grep -vi "^Vary:" | grep -vi "^Keep-Alive:" | grep -vi "^Connection:" | grep -vi "^Content-Type:" | grep -vi "^Content-Encoding:"  | grep -vi "Server:" | grep -v "Content-Length:" > "${f/.sh/.expected}") 
    done
else
    $("$path"/cases/"$1".sh | grep -vi "^Date:" | grep -vi "^X-Powered-By:" | grep -vi "^Vary:" | grep -vi "^Keep-Alive:" | grep -vi "^Connection:" | grep -vi "^Content-Type:" | grep -vi "^Content-Encoding:"  | grep -vi "Server:" | grep -v "Content-Length:" > "$path"/cases/"$1".expected) 
fi
