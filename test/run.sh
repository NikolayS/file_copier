#!/bin/bash
program=http
condition=$(which $program 2>/dev/null | grep -v "not found" | wc -l)
if [ $condition -eq 0 ] ; then
    >&2 echo "\"$program\" tool is missing! install HTTPie (\"pip install --upgrade httpie\" or \"brew install httpie\")"
    exit
fi
if [ "$COPIERSERVICE" == "" ] ; then
    >&2 echo "COPIERSERVICE is missing (use \"export COPIERSERVICE=https://your.copier.hostname\", w/o trailing slash)"
    exit
fi

while getopts ":f:" opt; do
  case $opt in
    f) format="$OPTARG"
    ;;
    \?) echo "Invalid option -$OPTARG" >&2
    ;;
  esac
done

path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )

testscount=$( ls -1 "$path/cases/"*.sh | wc -l )

if [ "$format" == "junit" ] ; then
    echo "<testsuite tests=\"$testscount\">"
fi

for f in $(ls "$path/cases/"*.sh)
do
    casename=$(echo "$f" | sed s/\.sh// | sed s%"$path/cases/"%%)
    #echo "Processing test case: \"$casename\""
    #$f
    result=$(diff -w "$path/cases/$casename.expected" <($f))
    if [ "$result" != "" ]
    then
        if [ "$format" == "junit" ] ; then
            echo "<testcase classname=\"cases/$casename.sh\" name=\"$casename\">"
            echo "  <failure type=\"NotExpectedOutput\">"
            echo "      <![CDATA["
            echo $result
            echo "]]>"
            echo "  </failure>"
            echo "</testcase>"
        else
            >&2 echo "[$(date)] FAILED test case \"$casename\"! See STDOUT for details"
            echo "FAILED test case \"$casename\""
            echo "Details:"
            echo "--------------------"
            echo "$result"
            echo "--------------------"
        fi
    else
        if [ "$format" == "junit" ] ; then
            echo "<testcase classname=\"cases/$casename.sh\" name=\"$casename\"/>"
        else
            echo "PASSED test case \"$casename\""
        fi
    fi
done

if [ "$format" == "junit" ] ; then
    echo "</testsuite>"
fi

