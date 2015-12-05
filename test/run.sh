#!/bin/bash
path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )

for f in $(ls "$path/cases/"*.sh)
do
    casename=$(echo "$f" | sed s/\.sh// | sed s%"$path/cases/"%%)
    #echo "Processing test case: \"$casename\""
    #$f
    result=$(diff "$path/cases/$casename.expected" <($f))
    if [ "$result" != "" ]
    then
        >&2 echo "[$(date)] FAILED test case \"$casename\"! See STDOUT for details"
        echo "FAILED test case \"$casename\""
        echo "Details:"
        echo "--------------------"
        echo "$result"
        echo "--------------------"
    else
        echo "PASSED test case \"$casename\""
    fi
done

