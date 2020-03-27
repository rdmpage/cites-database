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
	
	$item = wikidata_find_from_anything($reference);
	
	if ($item != '')
	{
		echo 'UPDATE cites SET wikidata-cited="' . $item . '" WHERE guid = "' . $reference->guid . '" AND `key` = "' . $reference->key . '";' . "\n";
	
	}
	

	
	$result->MoveNext();
}


	


?>