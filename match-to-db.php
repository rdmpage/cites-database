<?php

// Match cites database references to microcitation 

require_once(dirname(__FILE__) . '/adodb5/adodb.inc.php');
require_once(dirname(__FILE__) . '/shared.php');

//----------------------------------------------------------------------------------------
$db = NewADOConnection('mysqli');
$db->Connect("localhost", 
	'root', '', 'microcitation');

// Ensure fields are (only) indexed by column name
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

$db->EXECUTE("set names 'utf8'"); 

//----------------------------------------------------------------------------------------
// get articles from a given journal

$journal = 'Journal of the Malayan Branch of the Royal Asiatic Society';

$sql = 'SELECT * FROM cites WHERE `journal-title` ="' . $journal . '"'
 	. ' LIMIT 100';
 	
// specific guid

$sql = 'SELECT * FROM cites WHERE guid="10.1080/00305316.1998.10433763"';
$sql = 'SELECT * FROM cites WHERE guid="10.1644/1545-1542(2002)083<0408:SOOWDO>2.0.CO;2"';

$sql = 'SELECT * FROM cites WHERE guid="10.3161/15081109acc2017.19.1.001"';

$result = $db->Execute($sql);
if ($result == false) die("failed [" . __LINE__ . "]: " . $sql);


$citations = array();


while (!$result->EOF) 
{
	$reference = new stdclass;
	
	$reference->guid = $result->fields['guid'];
	$reference->key = $result->fields['key'];
	
	$keys = array(
		'unstructured',
		
		'author',
		
		'article-title',
		'journal-title',
		'volume',
		'first-page',
		'last-page',
		'year',
	
		'doi',
		'rdmp_doi',
		'rdmp_guid',
	
	);
	
	foreach ($keys as $k)
	{
		if ($result->fields[$k] != '')
		{
			$reference->{$k} = $result->fields[$k];
		}
	
	}

	$citations[] = $reference;	
	
	$result->MoveNext();
}

print_r($citations);


// Match to microcitations.publications---------------------------------------------------


$n = count($citations);

$do_crossref_search = true;

for ($i = 0; $i < $n; $i++)
{
	enhance_citation($citations[$i], $do_crossref_search);
}



print_r($citations);

// Update SQL------------------------------------------------------------------------------

foreach ($citations as $citation)
{

	if (isset($citation->rdmp_doi))
	{
		$sql = 'UPDATE cites SET rdmp_doi="' . $citation->rdmp_doi . '"'
			. ' WHERE guid="' . $citation->guid . '" AND `key`="' . $citation->key . '";';
			
		echo $sql . "\n";
	}
	
	if (isset($citation->rdmp_guid))
	{
		$sql = 'UPDATE cites SET rdmp_guid="' . $citation->rdmp_guid . '"'
			. ' WHERE guid="' . $citation->guid . '" AND `key`="' . $citation->key . '";';
			
		echo $sql . "\n";
	}	

}

	


?>