#!/bin/sh -ex

# BACKUP YOUR DATA BEFOR USING

# Creates a lookup table between SVN IDs and Git IDs
git rev-list --all --pretty=medium > revlist.txt;

# Now extract the git hash and the svn ID. Then we join lines pair-wise and we have our table
cat revlist.txt | grep git-svn-id | sed -e 's/git-svn-id: [a-z0-9 \#A-Z_\/:\.-]\{1,\}@\([0-9]\{1,4\}\) .\{1,\}/\1/' > svn.txt;
cat revlist.txt | grep ^commit > git.txt;

# Join them and write the lookup table to standard output
paste svn.txt git.txt | sed -e 's/commit //' | sed -e 's/ //g' | sort -n  > lookupTable.txt

# Clean up
rm svn.txt git.txt revlist.txt

