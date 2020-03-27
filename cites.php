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
function parse_unstructured(&$cited)
{
	$matched = false;
	
	if (!$matched)
	{
		// Springer
		if (preg_match('/(?<authorstring>.*)\s+\((?<year>[0-9]{4})[a-z]?\)\.\s+(?<title>.*)[\.|\?]\s+(?<journal>.*)\s+(?<volume>\d+)(\s+\((?<issue>.*)\))?:\s+(?<spage>\d+)(\s*[-|–]\s*(?<epage>\d+))?\b/Uu', $cited->unstructured, $m))
		{
			$matched = true;
			
			$keymap = array(
				'authorstring' 	=> 'author',
				'year' 			=> 'year',
				'title' 		=> 'article-title',
				'journal' 		=> 'journal-title',
				'volume'	 	=> 'volume',
				'issue'			=> 'issue',
				'spage'			=> 'first-page',
				'epage'			=> 'last-page'
			);
		
			foreach ($m as $k => $v)
			{
				if (!is_numeric($k))
				{
					if ($v != '')
					{
						$cited->{$keymap[$k]} = $v;					
					}
				}
			}
		
		
		}
	}	


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
				// Handle unstructured 
				if (isset($cited->unstructured))
				{
					parse_unstructured($cited);
				}
				
				$cited->source = 'crossref';	
				
				$keys = array();
				$values = array();
				
				//print_r($cited);
				
				foreach ($cited as $k => $v)
				{
					switch ($k)
					{
						case 'ISSN':
							$v = str_replace('http://id.crossref.org/issn/', '', $v);
							$keys[] = '`' . $k . '`';
							$values[] = '"' . addcslashes($v, '"') . '"';											
							break;
							
						// eat
						case 'issn-type':
							break;
							
						case 'year':
							$keys[] = '`' . $k . '`';
							$v = preg_replace('/[a-z]$/', '', $v);
							$values[] = '"' . addcslashes($v, '"') . '"';											
							break;
						
							
						default:					
							$keys[] = '`' . $k . '`';
							$values[] = '"' . addcslashes($v, '"') . '"';				
							break;
					}
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
	$u = 'https://daisy-pine.glitch.me/summary?q=' . urlencode($url);
	
	$json = get($u);
	
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
				
				$cited->source = $url;			
				
				// can we parse unstructured?
				if (preg_match('/(?<authorstring>[^\(|0-9)]+)\s*\(?(?<year>[0-9]{4})[a-z]?\)?\.?\s+(?<title>.*)\.\s+(?<journal>.*)\s+(?<volume>\d+)(\s*\((?<issue>.*)\))?:\s*(?<spage>\d+)(–(?<epage>\d+))?\b/u', $cited->unstructured, $m))
				{
					$keymap = array(
						'authorstring' 	=> 'author',
						'year' 			=> 'year',
						'title' 		=> 'article-title',
						'journal' 		=> 'journal-title',
						'volume'	 	=> 'volume',
						'issue'			=> 'issue',
						'spage'			=> 'first-page',
						'epage'			=> 'last-page'
					);
				
					foreach ($m as $k => $v)
					{
						if (!is_numeric($k))
						{
							if ($v != '')
							{
								$cited->{$keymap[$k]} = $v;					
							}
						}
					}
					
					// can we parse author string?
					if (isset($cited->author))
					{
						$authorstring = $cited->author;
					
						$authorstring = preg_replace('/\.,\s+/u', '.|', $authorstring);
						$authorstring = preg_replace('/\s+&\s+\s*/u', '|', $authorstring);
					
						$authors = explode('|', $authorstring);
						$cited->author = join(';', $authors);
					}				
				
				}
				
				
				foreach ($cited as $k => $v)
				{
					switch ($k)
					{
						case 'author':
							$keys[] = '`' . $k . '`';
							if (is_array($v))
							{
								$values[] = '"' . addcslashes(join(';', $v), '"') . '"';				
							}
							else
							{
								$values[] = '"' . addcslashes($v, '"') . '"';				
							}							
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
// Convert a semi-structured citation to SQL
function citation_to_sql($csl)
{
	$keys = array();
	$values = array();

	$keys[] = '`key`';
	$values[] = '"' . addcslashes($csl->id, '"') . '"';

	foreach ($csl as $k => $v)
	{
		switch ($k)
		{		
			case 'source_guid':
				$keys[] = '`' . 'guid' . '`';
				$values[] = '"' . addcslashes($v, '"') . '"';
				break;				
				
			case 'source':
				$keys[] = '`' . $k . '`';
				$values[] = '"' . addcslashes($v, '"') . '"';
				break;				
		
			case 'author':
				$authors = array();

				foreach ($v as $a)
				{
					$terms = array();
	
					if (isset($a->given))
					{
						$terms[] = $a->given;
					}

					if (isset($a->family))
					{
						$terms[] = $a->family;
					}

					$authors[] = join(' ', $terms);				
				}

				$keys[] = '`author`';
				$values[] = '"' . addcslashes(join(';', $authors), '"') . '"';
				break;		

			case 'volume':
			case 'issue':
			case 'unstructured':
				$keys[] = '`' . $k . '`';
				$values[] = '"' . addcslashes($v, '"') . '"';
				break;				

			case 'DOI':
				$keys[] = '`doi`';
				$values[] = '"' . addcslashes($v, '"') . '"';
				break;

			case 'page-first':
				$keys[] = '`first-page`';
				$values[] = '"' . addcslashes($v, '"') . '"';
				break;	

			case 'page':
				$keys[] = '`pages`';
				$values[] = '"' . addcslashes($v, '"') . '"';

				if (preg_match('/-(?<epage>.*)/', $v, $m))
				{				
					$keys[] = '`last-page`';
					$values[] = '"' . addcslashes($m['epage'], '"') . '"';				
				}
				break;	

			case 'title':
				switch ($csl->type)
				{
					case 'article-journal':
						$keys[] = '`article-title`';
						$values[] = '"' . addcslashes($v, '"') . '"';
						break;

					case 'book':
						$keys[] = '`volume-title`';
						$values[] = '"' . addcslashes($v, '"') . '"';
						break;

					case 'chapter':
						$keys[] = '`volume-title`';
						$values[] = '"' . addcslashes($v, '"') . '"';
						break;


					default:
						break;
				}
				break;

			case 'container-title':
				switch ($csl->type)
				{
					case 'article-journal':
						$keys[] = '`journal-title`';
						$values[] = '"' . addcslashes($v, '"') . '"';
						break;

					case 'chapter':
						$keys[] = '`series-title`';
						$values[] = '"' . addcslashes($v, '"') . '"';
						break;


					default:
						break;
				}
				break;

			case 'issued':
				if (isset($csl->issued->{'date-parts'}))
				{
					$keys[] = '`year`';
					$values[] = '"' . $csl->issued->{'date-parts'}[0][0] . '"';				
				}
				break;
				
			

			default:
				break;
		}	

	}

	$sql = 'REPLACE INTO cites(' . join(',', $keys) . ') values('
		. join(',', $values) . ');';
	echo $sql . "\n\n";
}



//----------------------------------------------------------------------------------------
function jats_xml($url, $doi = '')
{
	$url = 'https://glow-pajama.glitch.me/feed?q=' . urlencode($url);
	
	$json = get($url);

	//echo $json;

	$obj = json_decode($json);

	if (isset($obj->cites))
	{
		foreach ($obj->cites as $csl)
		{
			$csl->source = $url;
		
		
			if ($doi != '')
			{
				$csl->source_guid = $doi;
				$csl->source = $doi;
			}
			else
			{		
				$csl->source_guid = $url;
				$csl->source = $url;	
			}
			
			
			
			
			citation_to_sql($csl);
		}
	}
}

//----------------------------------------------------------------------------------------


// From JATS XML
if (1)
{
	$url = 'https://zookeys.pensoft.net/article/28800/download/xml/';

	$url = 'https://phytokeys.pensoft.net/article/32821/download/xml/';
	$doi = '10.3897/phytokeys.143.32821';
	
	// Wasps
	$url = 'https://zookeys.pensoft.net/article/39128/download/xml/';
	$doi = '10.3897/zookeys.920.39128';
	
	
	jats_xml($url, $doi);
	
}


// From DOI
if (0)
{
	$doi = '10.1007/s12225-012-9414-0';
	$doi = '10.1663/0006-8101(2002)068[0004:peaeia]2.0.co;2';
	$doi = '10.1017/s0960428615000281';

	$doi = '10.1007/s12225-017-9687-4';

	$doi = '10.1663/0007-196x(2003)055[0205:drzans]2.0.co;2';

	$doi = '10.1111/j.1756-1051.2011.01060.x';

	$doi ='10.1007/S12228-011-9192-2';


	$doi ='10.1017/S0960428600003164';


	crossref($doi);
}

// From URL
if (0)
{
	daisy('https://doi.org/10.11646/phytotaxa.177.4.6');
}

if (0)
{
	//daisy('https://doi.org/10.11646/phytotaxa.26.1.2');
	//daisy('https://doi.org/10.11646/phytotaxa.405.3.3');
	
	//daisy('https://doi.org/10.11646/phytotaxa.376.6.2');
	
	//daisy('https://doi.org/10.5852/ejt.2017.281');
	
	//daisy('https://doi.org/10.11646/phytotaxa.230.2.8');
	daisy('https://doi.org/10.11646/phytotaxa.365.3.7');
}


?>
