<?php

// J-Stage

require_once ('shared.php');

require_once 'vendor/autoload.php';
use Sunra\PhpSimple\HtmlDomParser;

//----------------------------------------------------------------------------------------
function get($url, $user_agent='', $content_type = '')
{	
	$data = null;

	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_COOKIEJAR		=> 'cookie.txt'
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



//--------------------------------------------------------------------------------------------------
// extract literature cited from BioOne web page
function get_references_from_html($doi)
{
	$safe_filename = $doi;
	$safe_filename = preg_replace('/[^a-z0-9]/i', '-', $safe_filename);
	
	$safe_filename .= '.html';
	
	$safe_filename = dirname(__FILE__) . '/cache/' . $safe_filename;
	
	if (file_exists($safe_filename))
	{
		$html_content = file_get_contents($safe_filename);
	
	}
	else
	{
		$html_content = get('https://doi.org/' . $doi);
		
		file_put_contents($safe_filename, $html_content);
	}
	
	//echo $html_content;
	
	$dom = HtmlDomParser::str_get_html($html_content);

	$citations = array();
	
	$metas = $dom->find('meta');

	
	foreach ($metas as $meta)
	{
		//echo $meta->name . " " . $meta->content . "\n";
	}
	
	$count = 1;
	
	foreach ($dom->find('meta[name=citation_reference]') as $meta)
	{
		$citation = new stdclass;
	  	$citation->id = $count++;
	
		$content = $meta->content;
		
		//echo $content . "\n";
		
		$parts = preg_split('/;\s+/u', $content);
		
		//print_r($parts);
		
		if (count($parts) == 1)
		{
			$citation->unstructured = $content;
		}
		else
		{
			foreach ($parts as $part)
			{
				$kv = explode('=', $part);
				
				$k = $kv[0];
				$v = $kv[1];
				
				switch ($k)
				{
				
					case 'citation_author':
						$citation->author[] = $v;
						break;

					case 'citation_publication_date':
						$citation->{'year'} = $v;
						break;
				
				
					case 'citation_title':
						$citation->{'article-title'} = $v;
						break;

					case 'citation_journal_title':
						$citation->{'journal-title'} = $v;
						break;

					case 'citation_volume':
						$citation->{'volume'} = $v;
						break;

					case 'citation_issue':
						$citation->{'issue'} = $v;
						break;

					case 'citation_firstpage':
						$citation->{'first-page'} = $v;
						break;

					case 'citation_lastpage':
						$citation->{'last-page'} = $v;
						break;

					case 'citation_publisher':
						$citation->{'publisher'} = $v;
						break;

					case 'citation_doi':
						$citation->{'doi'} = $v;
						break;
				
			
			
					default:
						break;
				}
			}
			
			
		}
		//print_r($citation);
		
		citation_to_sql($doi, $citation);


	}	
	
	
	/*
	foreach($html->find('table[class=references] tbody tr') as $element)
	{
	   //echo $element->innertext . "\n";
	   
	   $citation = new stdclass;
	   $citation->id = $element->id;
	   $citation->unstructured = $element->outertext;
	   
	   $matched = false;
	   
	   if (!$matched)
	   {
	   
		   if (preg_match('/"top">\s+(?<authorstring>.*)\s+\((?<year>[0-9]{4})[a-z]?\):\s+(?<title>.*)[\.|\?] - <i>(?<journal>.*)<\/i>.*<b>(?<volume>\d+)<\/b>(\((?<issue>\d+)\))?:\s+(?<spage>\d+)(-(?<epage>\d+))?\./u', $element->innertext, $m))
		   {
		   		$citation->author = authors_from_string($m['authorstring']);
		   		   
				$citation->{'year'} = $m['year'];
				$citation->{'article-title'} = $m['title'];
				$citation->{'journal-title'} = $m['journal'];
				$citation->{'volume'} = $m['volume'];
				if($m['issue'] != '')
				{
					$citation->{'issue'} = $m['issue'];
				}
				
				$citation->{'first-page'} = $m['spage'];
				if($m['epage'] != '')
				{
					$citation->{'last-page'} = $m['epage'];
				}
				
				$matched = true;
		   }
		}
		
	   if (!$matched)
	   {
	   
		   if (preg_match('/"top">\s+(?<authorstring>.*)\s+\((?<year>[0-9]{4})[a-z]?\):\s+(?<title>.*)[\.|\?]\s+-\s+/u', $element->innertext, $m))
		   {
		   		$citation->author = authors_from_string($m['authorstring']);
		   		
		   
				$citation->{'year'} = $m['year'];
				$citation->{'article-title'} = $m['title'];
				
				$matched = true;
		   }
		}
		
		
		
		foreach ($element->find('a') as $a)
		{
			if (preg_match ('/https?:\/\/akademiai.com\/doi\/(?<doi>.*)/', $a->href, $m))
			{
				$citation->DOI = $m['doi'];
			}

			if (preg_match ('/&amp;key=(?<doi>.*)/', $a->href, $m))
			{
				$citation->DOI = urldecode($m['doi']);
			}
			
			
		}

		if (count($citation->author) == 0)
		{
			unset($citation->author);
		}

		
		$citation->unstructured = strip_tags($element->innertext);
		$citation->unstructured = preg_replace('/Google Scholar/', '', $citation->unstructured);
		$citation->unstructured = preg_replace('/Link,/', '', $citation->unstructured);
		$citation->unstructured = preg_replace('/Crossref,/', '', $citation->unstructured);
		$citation->unstructured = trim($citation->unstructured);	   
 		
	
		$citations[$citation->id] = $citation;
		
		citation_to_sql($doi, $citation);
	}
	
	*/
	
	//print_r($citations);
	
	
	
	return $citations;
}

$doi = '10.18942/apg.KJ00004623238';

$doi = '10.18942/apg.KJ00004623252';

//$doi = '10.18942/apg.KJ00004623254';



get_references_from_html($doi);





?>
