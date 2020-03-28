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


$sql = 'SELECT * FROM cites WHERE  `wikidata` IS NOT NULL and `wikidata-cited` IS NOT NULL';

$sql .= ' AND guid="' . $guid . '"';



//echo $sql . "\n";

$result = $db->Execute($sql);
if ($result == false) die("failed [" . __LINE__ . "]: " . $sql);

$quickstatements = '';
$w = array();

while (!$result->EOF) 
{
	$item 	= $result->fields['wikidata'];
	$cited 	= $result->fields['wikidata-cited'];


	$w[] = array('P2860' => $cited);

	
	$result->MoveNext();
}

foreach ($w as $statement)
{
	foreach ($statement as $property => $value)
	{
		$row = array();
		$row[] = $item;
		$row[] = $property;
		$row[] = $value;
	
		$quickstatements .= join("\t", $row) . "\n";
		
	}
}

echo $quickstatements . "\n";




	


?>