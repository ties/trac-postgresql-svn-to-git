#!/bin/sh -ex

# BACKUP YOUR DATA BEFOR USING

usage="\nUsage:\n   "$(basename $0)" path-to-git-repository path-to-trac-db"


[ "x$1" != "x" ] || {
  echo "no Git repo specified!!!\n\n"
  echo $usage
  exit
}

[ "x$2" != "x" ] || {
  echo "no trac database path specified!!!\n\n"
  echo $usage
  exit
}




export GIT_DIR=$1

# Creates a lookup table between SVN IDs and Git IDs
git rev-list --all --pretty=medium > revlist.txt;

# Now extract the git hash and the svn ID. Then we join lines pair-wise and we have our table
cat revlist.txt | grep git-svn-id | sed -e 's/git-svn-id: [a-z0-9 \#A-Z_\/:\.-]\{1,\}@\([0-9]\{1,4\}\) .\{1,\}/\1/' > svn.txt;
cat revlist.txt | grep ^commit > git.txt;

# Join them and write the lookup table to standard output
paste svn.txt git.txt | sed -e 's/commit //' | sed -e 's/ //g' | sort -n  > lookupTable.txt


php convertTracTickets.php $2


# Clean up
rm svn.txt git.txt revlist.txt lookupTable.txt

