<?php

function citation_to_sql($guid, $csl)
{

	//print_r($csl);

	$keys = array();
	$values = array();

	
	
	if (isset($csl->id))
	{
		$keys[] = '`key`';
		$values[] = '"' . addcslashes($csl->id, '"') . '"';
	}
	else
	{
		if (isset($csl->key))
		{
			$keys[] = '`key`';
			$values[] = '"' . addcslashes($csl->key, '"') . '"';
		}
	}	


	foreach ($csl as $k => $v)
	{
		switch ($k)
		{
			case 'author':
				$keys[] = '`author`';
				$values[] = '"' . addcslashes(join(';', $v), '"') . '"';
				break;		

			case 'article-title':
			case 'first-page':
			case 'journal-title':			
			case 'last-page':
			case 'volume':
			case 'volume-title':
			case 'issue':
			case 'year':
			case 'unstructured':
				$keys[] = '`' . $k . '`';
				$values[] = '"' . addcslashes($v, '"') . '"';
				break;				



			default:
				break;
		}	

	}


	$keys[] = 'guid';
	$values[] = '"' . $guid . '"';				

	$sql = 'REPLACE INTO cites(' . join(',', $keys) . ') values('
		. join(',', $values) . ');';
	echo $sql . "\n\n";

}

?>
