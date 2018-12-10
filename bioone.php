<?php

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
		$html_content = get('http://dx.doi.org/' . $doi);
		
		file_put_contents($safe_filename, $html_content);
	}
	
	//echo $html_content;
	
	$html = HtmlDomParser::str_get_html($html_content);

	$citations = array();

	foreach($html->find('div.refnumber') as $element)
	{
	   //echo $element->innertext . "\n";
   
	   //print_r($element);
   
	   //exit();
   
	   $citation = new stdclass;
	   $citation->key = $element->id;
	   $citation->unstructured = $element->outertext;
	   $citation->type = 'generic';
	   $citation->author = array();
	   
		$value = $element->find('span.NLM_year', 0);
		if ($value)
		{
			$citation->year = $value->plaintext;		
			$citation->year = preg_replace('/[a-z]$/i', '', $citation->year);
		}
		
		foreach($element->find('span[class=NLM_string-name]') as $span)
		{
			$citation->author[] = trim($span->plaintext);
		}
	
		$value = $element->find('span.NLM_article-title', 0);
		if ($value)
		{
			$citation->type = 'article';
			$citation->{'article-title'} = $value->plaintext;
			$citation->{'article-title'} = preg_replace('/\.\s*$/', '', $citation->{'article-title'});
		}

		$value = $element->find('span.citation_source-journal', 0);
		if ($value)
		{
			$citation->type = 'article';
			$citation->{'journal-title'} = $value->plaintext;
		}
		
		if (!isset($citation->{'journal-title'}))
		{		
			if (preg_match('/<span class="NLM_article-title">(.*)<\/span>\s*<i>(?<journal>.*)<\/i>(?<collation>.*)<span class="NLM_fpage">/Uu', $element->innertext, $m))
			{
				//print_r($m);
				$citation->{'journal-title'} = trim($m['journal']);			
			
				//27 (1): 
				if (preg_match('/(?<volume>\d+)(\s*\((?<issue>.*)\))?\s*:/', $m['collation'], $mm))
				{
				
					$citation->volume = $mm['volume'];
					if ($mm['issue'] != '')
					{
						$citation->issue = $mm['issue'];				
					}
				}
			}
		}
			
		$value = $element->find('span.citation_source-book', 0);
		if ($value)
		{
			$citation->type = 'book';
			$citation->{'volume-title'} = $value->plaintext;
		}
	
		$value = $element->find('span.NLM_publisher-name', 0);
		if ($value)
		{
			$citation->type = 'book';		
			$citation->publisher = $value->plaintext;
		}
	
		$value = $element->find('span.NLM_publisher-loc', 0);
		if ($value)
		{
			$citation->type = 'book';
			$citation->publoc = $value->plaintext;
		}
	
		// start page
		$value = $element->find('span.NLM_fpage', 0);
		if ($value)
		{
			$citation->{'first-page'} = trim($value->plaintext);
		
			// volume not marked up (sigh)
			if (preg_match('/<\/span>\s+(?<volume>\d+):<span class="NLM_fpage">/', $element->innertext, $m))
			{
				$citation->volume = $m['volume'];
			}
		}   	
	
		// end page
		$value = $element->find('span.NLM_lpage', 0);
		if ($value)
		{
			$citation->{'last-page'} .= trim($value->plaintext);
		}
	
		/*
	
		echo $element->find('span.NLM_article-title', 0)->plaintext . "\n";
		echo $element->find('span.citation_source-journal', 0)->plaintext . "\n";
		echo $element->find('span.citation_source-book', 0)->plaintext . "\n";
		echo $element->find('span.NLM_publisher-name', 0)->plaintext . "\n";
		echo $element->find('span.NLM_publisher-loc', 0)->plaintext . "\n";
		echo $element->find('span.NLM_fpage', 0)->plaintext . "\n";
		echo $element->find('span.NLM_lpage', 0)->plaintext . "\n";
		*/
	
		// <a class="ext-link" href="
	
		// link
		$link = $element->find('a[class=ext-link]', 0)->href;
		if ($link != '')
		{
			$link = urldecode($link);
			if (preg_match('/https?:\/\/(dx\.)?doi.org\/(?<doi>.*)/', $link, $m))
			{
				$citation->doi = $m['doi'];
			}
		}	
		
		$link = $element->find('a', 0)->href;
		if ($link != '')
		{
			$link = urldecode($link);
			if (preg_match('/key=(?<doi>.*)$/', $link, $m))
			{
				$citation->doi = $m['doi'];			
			}
			// BioOne DOI
			if (preg_match('/https?:\/\/www.bioone.org\/doi\/(?<doi>.*)/', $link, $m))
			{
				$citation->doi = $m['doi'];			
			}
			
		}	
		
		// <a class="google-scholar"
		$link = $element->find('a[class=google-scholar]', 0)->href;
		if ($link != '')
		{
			$link = urldecode($link);
			if (preg_match_all('/author=(?<author>.*)&/Uu', $link, $m))
			{
				$citation->author = $m['author'];
			}
		}	
				
		
	
		$value = $element->find('span.citation_source-book', 0);
		if ($value)
		{
			$citation->type = 'book';
			$citation->title = $value->plaintext;
		}
	
		// try and fix up things we've missed
		if ($citation->type == 'book')
		{
			if (!isset($citation->title))
			{
				if (preg_match('/<span class="NLM_year">\d+<\/span>(?<title>.*)\s*<span/Uu', $citation->html, $m))
				{
					$citation->title = $m['title'];
					$citation->title = preg_replace('/^\./', '', $citation->title);
					$citation->title = preg_replace('/^\s+/', '', $citation->title);
					$citation->title = preg_replace('/\.$/', '', $citation->title);
				}
			}
		}
	
		if (count($citation->authors) == 0)
		{
			unset($citation->authors);
		}
		unset($citation->type);
		
		print_r($citation);
	
		$citations[$citation->id] = $citation;
	}
	
	return $citations;
}

$doi = '10.5252/adansonia2018v40a8';
$doi = '10.5252/a2012n2a1';

get_references_from_html($doi);

/*

$url = 'http://www.bioone.org/doi/abs/10.5252/adansonia2018v40a8';


$html = get($url);

echo $html;*/

// parse





?>
