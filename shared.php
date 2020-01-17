<?php

//----------------------------------------------------------------------------------------
function get($url, $accept = 'application/json')
{
	$data = null;
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_HTTPHEADER => array('Accept: ' . $accept) ,
	  CURLOPT_COOKIEJAR		=> 'cookie.txt'	  
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}

//----------------------------------------------------------------------------------------
function find_doi($string)
{
	$doi = '';
	
	$url = 'https://mesquite-tongue.glitch.me/search?q=' . urlencode($string);
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	if ($data != '')
	{
		$obj = json_decode($data);
		
		//print_r($obj);
		
		if (count($obj) == 1)
		{
			if ($obj[0]->match)
			{
				$doi = $obj[0]->id;
			}
		}
		
	}
	
	return $doi;
			
}	

//----------------------------------------------------------------------------------------
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

			case 'editor':
				$keys[] = '`editor`';
				$values[] = '"' . addcslashes(join(';', $v), '"') . '"';
				break;	
				
				

			case 'article-title':
			case 'first-page':
			case 'journal-title':			
			case 'last-page':
			case 'volume':
			case 'volume-title':
			case 'series-title':
			case 'publisher':
			case 'publoc':
			case 'issue':
			case 'year':
			case 'unstructured':
			case 'doi':
			
			case 'rdmp_doi':
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
