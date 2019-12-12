<?php

// Match cites database references to microcitation

require_once(dirname(__FILE__) . '/adodb5/adodb.inc.php');

//----------------------------------------------------------------------------------------
$db = NewADOConnection('mysqli');
$db->Connect("localhost", 
	'root', '', 'microcitation');

// Ensure fields are (only) indexed by column name
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

$db->EXECUTE("set names 'utf8'"); 

//----------------------------------------------------------------------------------------
// get articles from a given journal
$journal = 'Acta Phytotaxonomica et Geobotanica';

$journal = 'Telopea';

$sql = 'SELECT * FROM cites WHERE `journal-title` ="' . $journal . '"'
    . ' AND doi IS NULL AND rdmp_doi Is NULL'
	. ' AND volume IS NOT NULL AND `first-page` IS NOT NULL AND year IS NOT NULL '
	. ' LIMIT 100';

$result = $db->Execute($sql);
if ($result == false) die("failed [" . __LINE__ . "]: " . $sql);


$citations = array();

$include_year = false;


while (!$result->EOF) 
{
	$reference = new stdclass;
	
	$reference->guid = $result->fields['guid'];
	$reference->key = $result->fields['key'];
	
	$reference->title = $result->fields['article-title'];
	$reference->journal = $result->fields['journal-title'];
	$reference->volume = $result->fields['volume'];
	$reference->spage = $result->fields['first-page'];
	
	if ($include_year)
	{
		$reference->year = $result->fields['year'];
	}


	$citations[] = $reference;	
	
	$result->MoveNext();
}

print_r($citations);

// Match to microcitations.publications---------------------------------------------------


$n = count($citations);

for ($i = 0; $i < $n; $i++)
{

	$parameters = array();
	foreach ($citations[$i] as $k => $v)
	{
		switch ($k)
		{
			case 'guid':
			case 'key':
			case 'title':
				break;
				
			default:
				$parameters[] = $k . '="' . addcslashes($v, '"') . '"';
				break;		
		}		
	}
	

	$sql = 'SELECT * FROM publications WHERE ' . join(' AND ', $parameters) 
	
		. ' AND doi IS NOT NULL'
		. ';';
	
	echo $sql . "\n";
	
	$result = $db->Execute($sql);
	if ($result == false) die("failed [" . __LINE__ . "]: " . $sql);
	
	if ($result->NumRows() == 1)
	{
		$citations[$i]->doi = $result->fields['doi'];
	}



}

print_r($citations);

// Update SQL------------------------------------------------------------------------------

foreach ($citations as $citation)
{

	if (isset($citation->doi))
	{
		$sql = 'UPDATE cites SET rdmp_doi="' . $citation->doi . '"'
			. ' AND guid="' . $citation->guid . '" AND `key`="' . $citation->key . '";';
			
		echo $sql . "\n";
	}

}

	


?>