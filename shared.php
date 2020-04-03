<?php


require_once(dirname(__FILE__) . '/wikidata.php');

mb_internal_encoding("UTF-8");


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
function find_local($string)
{
	$guid = '';
	
	$url = 'http://localhost/~rpage/microcitation/www/api_openurl.php?rft.dat=' . urlencode($string);
	
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
		
		// print_r($obj);
		
		if ($obj->found)
		{
			// do we have DOI?
			if ($guid == '')
			{
				if (isset($obj->results[0]->doi))
				{
					$guid = $obj->results[0]->doi;
				}
			}
		
			if ($guid == '')
			{
				$guid = $obj->results[0]->guid;
			}
		}
		
	}
	
	return $guid;
			
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
			case 'rdmp_guid':
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


//----------------------------------------------------------------------------------------
// Try to locate an item using any identifier or metadata that we have
function wikidata_find_from_anything ($citation)
{
	// Do we have this already in wikidata?
	$item = '';
	
	// DOI
	if (isset($citation->doi))
	{
		$item = wikidata_item_from_doi($citation->doi);
	}
	if (isset($citation->rdmp_doi))
	{
		$item = wikidata_item_from_doi($citation->rdmp_doi);
	}

	// JSTOR
	if ($item == '')
	{
		if (isset($citation->rdmp_jstor))
		{
			$item = wikidata_item_from_jstor($citation->rdmp_jstor);
		
		}
	}	

	/*
	// BioStor
	if ($item == '')
	{
		if (isset($citation->BIOSTOR))
		{
			$item = wikidata_item_from_biostor($citation->BIOSTOR);
		}
	}	
	*/	

/*
	// PDF
	if ($item == '')
	{
		if (isset($citation->link))
		{
			foreach ($citation->link as $link)
			{
				if ($link->{'content-type'} == 'application/pdf')
				{
					$item = wikidata_item_from_pdf($link->URL);
				}
			}
		}
	}	
	*/
	
	// url or pdf
	if ($item == '')
	{
		if (isset($citation->rdmp_guid))
		{
			$item = wikidata_item_from_pdf($citation->rdmp_guid);		
		}
	}		
	
	if ($item == '')
	{
		if (isset($citation->rdmp_guid))
		{
			$item = wikidata_item_from_url($citation->rdmp_guid);		
		}
	}		
	
	// OpenURL
	if ($item == '')
	{
		$terms = array();
				
		$issn = $volume = $spage = '';
		
		if (isset($citation->issn))
		{
			$terms[] = $citation->issn;
		}		

		if (isset($citation->rdmp_issn))
		{
			$terms[] = $citation->rdmp_issn;
		}		
		
		if (isset($citation->volume))
		{
			$terms[] = $citation->volume;
		}

		if (isset($citation->{'page-first'}))
		{
			$terms[] = $citation->{'page-first'};
		}
			
		if (count(terms) == 3)
		{
			foreach ($terms[0] as $issn)
			{
				$hit = wikidata_item_from_openurl($issn, $terms[1], $terms[2]);
				if ($hit <> '')
				{
					$item = $hit;
				}
			}
		}

	}	
	
	return $item;	


}

//----------------------------------------------------------------------------------------
// String representation of citation for searching
function citation_to_string($citation)
{
	$string = '';
	
	if (isset($citation->unstructured))
	{
		$string = $citation->unstructured;
	}
	else
	{
		$terms = array();
	
		$keys = array(
			'author',
			'year',
			'article-title',
			'journal-title',
			'volume',
			'first-page',
			'last-page'
		);
	
		foreach ($keys as $k)
		{
			if (isset($citation->{$k}))
			{
				switch ($k)
				{
					case 'author':
						$terms[] = join(', ', $citation->{$k});
						break;
			
					default:
						$terms[] = $citation->{$k};
						break;
				}
			}
	
		}
		
		$string = join (' ', $terms);
			
	}
	
	return $string;


}

//----------------------------------------------------------------------------------------
// Try to add identifiers to citation
function enhance_citation (&$citation, $do_crossref_search = true)
{
	if (isset($citation->doi))
	{
		return;
	}
	
	if ($do_crossref_search)
	{
		$doi = find_doi(citation_to_string($citation));
		if ($doi != '')
		{
			echo "-- Found DOI $doi\n\n"; 
				
			$citation->{'rdmp_doi'} = strtolower($doi);
		}   
	} 
	
	if (!isset($citation->{'rdmp_doi'}))
	{
	
		$rdmp_guid = find_local(citation_to_string($citation));
		if ($rdmp_guid != '')
		{
			echo "-- Found local GUID $rdmp_guid\n\n"; 
		
			$citation->{'rdmp_guid'} = $rdmp_guid;
			
			if (preg_match('/10\.\d+\//', $rdmp_guid))
			{
				$citation->{'rdmp_doi'} = $rdmp_guid;
			}
		}    
	}	
}

?>
