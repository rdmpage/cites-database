<?php

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
function authors_from_string($authorstring)
{
	$authors = array();
	
	// Strip out suffix
	$authorstring = preg_replace("/,\s*Jr./u", "", trim($authorstring));
	$authorstring = preg_replace("/,\s*jr./u", "", trim($authorstring));
	
	$authorstring = preg_replace("/,$/u", "", trim($authorstring));
	$authorstring = preg_replace("/&/u", "|", $authorstring);
	$authorstring = preg_replace("/;/u", "|", $authorstring);
	$authorstring = preg_replace("/ and /u", "|", $authorstring);
	$authorstring = preg_replace("/ y /u", "|", $authorstring);
	$authorstring = preg_replace("/\.,/Uu", "|", $authorstring);				
	$authorstring = preg_replace("/\|\s*\|/Uu", "|", $authorstring);				
	$authorstring = preg_replace("/\|\s*/Uu", "|", $authorstring);				
	$authors = explode("|", $authorstring);
	
	for ($i = 0; $i < count($authors); $i++)
	{
		$authors[$i] = preg_replace('/\.([A-Z])/u', ". $1", $authors[$i]);
		$authors[$i] = preg_replace('/^\s+/u', "", $authors[$i]);
		$authors[$i] = preg_replace('/\,$/u', "", $authors[$i]);
		$authors[$i] = preg_replace('/\.$/u', "", $authors[$i]);
		$authors[$i] = mb_convert_case($authors[$i], MB_CASE_TITLE, 'UTF-8');
	}

	return $authors;
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
		$html_content = get('https://akademiai.com/doi/ref/' . $doi);
		
		file_put_contents($safe_filename, $html_content);
	}
	
	//echo $html_content;
	
	$html = HtmlDomParser::str_get_html($html_content);

	$citations = array();
	

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
	
	//print_r($citations);
	
	
	
	return $citations;
}

$doi = '10.1556/ABot.47.2005.1-2.3';



get_references_from_html($doi);





?>
