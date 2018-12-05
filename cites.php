<?php

// Get lists of cited works for articles, e.g via CrossRef, journal page, etc.

//----------------------------------------------------------------------------------------
function get($url, $user_agent='', $content_type = '')
{	
	$data = null;

	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE
	);

	if ($content_type != '')
	{
		$opts[CURLOPT_HTTPHEADER] = array("Accept: " . $content_type);
	}
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}

//----------------------------------------------------------------------------------------
// CrossRef get cited literature
function crossref($doi)
{
	$doi = strtolower($doi);

	$url = 'https://api.crossref.org/v1/works/http://dx.doi.org/' . $doi;
	
	$json = get($url);
	
	
	if ($json != '')
	{
		$obj = json_decode($json);
		
		//print_r($obj);
		
		if (isset($obj->message->reference))
		{
			foreach ($obj->message->reference as $cited)
			{
				$keys = array();
				$values = array();
				
				foreach ($cited as $k => $v)
				{
					$keys[] = '`' . $k . '`';
					$values[] = '"' . addcslashes($v, '"') . '"';				
				}
				
				$keys[] = 'guid';
				$values[] = '"' . $doi . '"';				
			
				$sql = 'REPLACE INTO cites(' . join(',', $keys) . ') values('
					. join(',', $values) . ');';
				echo $sql . "\n\n";
		
			
			}
		
		}
	
	}

}


//----------------------------------------------------------------------------------------
function daisy($url)
{

	$url = 'https://daisy-pine.glitch.me/summary?q=' . urlencode($url);
	
	
	$json = get($url);
	
	
	if ($json != '')
	{
		$obj = json_decode($json);
		
		//print_r($obj);
		
		$guid = $url;
		
		if (isset($obj->DOI))
		{
			$guid = $obj->DOI;
		}
		
		if (isset($obj->reference))
		{
			$count = 1;
			
			foreach ($obj->reference as $cited)
			{
				$keys = array();
				$values = array();
				
				// Make sure we have a local id
				if (!isset($cited->key))
				{
					$cited->key = 'ref' . $count++;
				}				
				
				foreach ($cited as $k => $v)
				{
					switch ($k)
					{
						case 'author':
							$keys[] = '`' . $k . '`';
							$values[] = '"' . addcslashes(join(';', $v), '"') . '"';				
							break;							
					
						default:
							$keys[] = '`' . $k . '`';
							$values[] = '"' . addcslashes($v, '"') . '"';				
							break;
					}
				}
				
				$keys[] = 'guid';
				$values[] = '"' . $guid . '"';				
			
				$sql = 'REPLACE INTO cites(' . join(',', $keys) . ') values('
					. join(',', $values) . ');';
				echo $sql . "\n\n";
		
			
			}
		
		}
	
	}	
	
	
}

//----------------------------------------------------------------------------------------

$doi = '10.1590/0102-33062017abb0368';

$doi = '10.1017/S0960428608005015';

$doi = '10.1007/s12228-008-9070-8';

//crossref($doi);

$url = 'https://doi.org/10.11646/phytotaxa.71.1.10';

daisy($url);




?>
