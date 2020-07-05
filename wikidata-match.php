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

$guid = "10.11646/zootaxa.4154.5.1";
$guid = "http://peckhamia.com/peckhamia/PECKHAMIA_114.1.pdf";
$guid = '10.1080/00305316.1998.10433763';

	// primary reference (assume DOI)
	$primary = new stdclass;
	
	if (preg_match('/10\.\d+\//', $guid))
	{	
		$primary->doi = $guid;	
		$item = wikidata_find_from_anything($primary);	
	}
	
	if (preg_match('/\.pdf$/', $guid))
	{	
		$primary->rdmp_guid = $guid;	
		$item = wikidata_find_from_anything($primary);	
	}
	
	
	if ($item != '')
	{
		echo 'UPDATE cites SET `wikidata` = "' . $item . '" WHERE guid = "' . $guid  . '";' . "\n";	
	}
	
	if ($item == '')
	{
		// GUID not in Wikidata
		
		echo "$guid not found in Wikidata\n";
		exit();	
	}
	


$sql = 'SELECT * FROM cites WHERE guid="' . $guid . '"';

//echo $sql . "\n";

$result = $db->Execute($sql);
if ($result == false) die("failed [" . __LINE__ . "]: " . $sql);



while (!$result->EOF) 
{
	$reference = new stdclass;
	
		
	$keys = array(
'guid',
'key',
'year',
'article-title',
'journal-title',
'issn',
'rdmp_issn',
'volume',
'issue',
'first-page',
'last-page',
'doi',	
'rdmp_doi',
'rdmp_guid'
		
	
	);
	
	foreach ($keys as $key)
	{
		if ($result->fields[$key] != '')
		{
			$reference->{$key} = $result->fields[$key];
		}
	}
	
	//print_r($reference);
	
	
	// cited works
	$item = wikidata_find_from_anything($reference);
	
	if ($item != '')
	{
		echo 'UPDATE cites SET `wikidata-cited` = "' . $item . '" WHERE guid = "' . $reference->guid . '" AND `key` = "' . $reference->key . '";' . "\n";
	
	}
	

	
	$result->MoveNext();
}


	


?>