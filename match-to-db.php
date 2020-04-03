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

$journal = 'Basteria';

$sql = 'SELECT * FROM cites WHERE `journal-title` ="' . $journal . '"'
 	. ' LIMIT 100';

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

for ($i = 0; $i < $n; $i++)
{
	enhance_citation($citations[$i], false);
}



print_r($citations);

// Update SQL------------------------------------------------------------------------------

foreach ($citations as $citation)
{

	if (isset($citation->rdmp_doi))
	{
		$sql = 'UPDATE cites SET rdmp_doi="' . $citation->rdmp_doi . '"'
			. ' AND guid="' . $citation->guid . '" AND `key`="' . $citation->key . '";';
			
		echo $sql . "\n";
	}
	
	if (isset($citation->rdmp_guid))
	{
		$sql = 'UPDATE cites SET rdmp_guid="' . $citation->rdmp_guid . '"'
			. ' AND guid="' . $citation->guid . '" AND `key`="' . $citation->key . '";';
			
		echo $sql . "\n";
	}	

}

	


?>