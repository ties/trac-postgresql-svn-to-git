<?php

/**
 * This script converts the commit references from SVN IDs to GIT IDs, i.e. changing in all tickets
 * [1234] to [a42v2e3] or whatever the corresponding GIT hash is
 *
 * It needs a SVN ID -> GIT ID lookup table file called lookupTable.txt to match IDs.
 *
 * Execute it with php.exe convertTracTickets.php
 *
 * Needs the sqlite3 extension enabled to access the TRAC database.
 **/
error_reporting(E_ALL);

/* CONFIGURATION */

//Postgres database details
//die("Please setup Postgres connection string and default schema");
$pg_string = "host=localhost dbname=trac user=trac password=oLoP4Swa";
$pg_schema = "cleanj";
DEFINE("REPONAME", "");

// Path to lookup table (SVN revision number to GIT revion hash)
$pathLookupTable = "lookupTable.txt";

// Number of characters for the changeset hash. This has to be 4 <= nr <= 40
$nrHashCharacters = 8;

/* END CONFIGURATION */

/**
 * Converts a text with references to an SVN revision [1234] into the corresponding GIT revision
 *
 * @param text Text to convert
 * @param lookupTable Conversion table from SVN ID to Git ID
 * @returns True if conversions have been made
 */
function convertSVNIDToGitID(&$text, $lookupTable, $nrHashCharacters)
{		
	// Extract references to SVN revisions [####]
	$pattern = '/\[([0-9]+)\]/';
	
	if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) > 0)
	{		
		foreach($matches as $match)
		{		
			$svnID = $match[1];
			if (!isSet($lookupTable[$svnID]))
			{
				echo "Warning: unknown GIT hash for SVN revision $svnID\n";
				continue;
			}
			$gitID = substr($lookupTable[$svnID], 0, $nrHashCharacters);
			
			$text = str_replace('[' . $svnID . ']', '[' . $gitID . '] (SVN r' . $svnID . ')', $text);
			$text = str_replace('#!CommitTicketReference repository="' . REPONAME .'" revision="' . $svnID .'"', '#!CommitTicketReference repository="' . REPONAME .'" revision="' . $gitID . '"', $text);
		}
		
		return true;
	}
	
	return false;
}

echo "Creating SVN -> GIT conversion table table...\n";

// Create the lookup table
$lines = file($pathLookupTable);
foreach ($lines as $line)
{	
	if (empty($line)) continue;	
	list ($svnID, $gitID) = explode("\t", trim($line));	
	$lookupTable[$svnID] = $gitID;
}

// Connect to the Postgres/TRAC database
$db = pg_connect($pg_string);
// Select schema
pg_query("SET search_path TO '${pg_schema}'");


echo "Converting table 'ticket_change'...\n";

// Convert table 'ticket_change'
$result = pg_query('SELECT * FROM ticket_change'); 

$i = 1;
while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
{			
	$i++;
	$oldValue = pg_escape_string($row['oldvalue']);
	$newValue = pg_escape_string($row['newvalue']);
	
	// Only update when there is something to be changed, since SQLite isn't the fastest beast around
	if (convertSVNIDToGitID($oldValue, $lookupTable, $nrHashCharacters) || convertSVNIDToGitID($newValue, $lookupTable, $nrHashCharacters))
	{	
		$query = "UPDATE ticket_change SET oldvalue='$oldValue', newvalue='$newValue' WHERE ticket = '${row['ticket']}' AND time = '${row['time']}' AND author='${row['author']}' AND field='${row['field']}'";
		if (!pg_query($query))
		{
			echo "Query failed: " . $query . "\n";
		}		
		
		echo "Updated ticket_change $i\n";
	}
}

echo "Converting table 'ticket'...\n";

// Convert table 'ticket'

$i = 0;

$result = pg_query('SELECT * FROM ticket');
while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
{
	$i++;
	$description = pg_escape_string($row['description']);
	if (convertSVNIDToGitID($description, $lookupTable, $nrHashCharacters))
	{	
		$query = "UPDATE ticket SET description='$description' WHERE id = " . $row['id'];
		pg_query($query);
		
		echo "Updated ticket $i\n";
	}
}

// Done :)
echo "Done!\n";
?>
