<?php

require_once 'vendor/autoload.php';
use Sunra\PhpSimple\HtmlDomParser;

require_once ('shared.php');

global $doi;

//----------------------------------------------------------------------------------------
function get($url, $user_agent='Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405', $content_type = '')
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

//----------------------------------------------------------------------------------------
function resolve($url, $user_agent='', $content_type = '')
{	
	$redirect_url = '';

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
	
	$redirect_url = $info['url'];
	

	curl_close($ch);
	
	return $redirect_url;
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
function get_html($identifier)
{
	global $doi;
	
	$html_content = '';
	
	if (preg_match('/^10/', $identifier))
	{
		$doi = $identifier;	
			
		$url = resolve('https://doi.org/' . $doi);
		$url .= '?tab=ArticleLinkReference';
		
	}
	else
	{
		$doi = '';
		$url = $identifier;
		
		// /10.1600/
		if (preg_match('/(?<doi>10.1600\/[^\.]+)/', $identifier, $m))
		{
			$doi = $m['doi'];
		}	
	}
	
	if ($doi != '')
	{
	
		$safe_filename = $doi;
		$safe_filename = preg_replace('/[^a-z0-9]/i', '-', $safe_filename);
	
		$safe_filename .= '.html';
	
		$safe_filename = dirname(__FILE__) . '/cache/' . $safe_filename;
		
		$doi = strtolower($doi);
		
		if (file_exists($safe_filename))
		{
			$html_content = file_get_contents($safe_filename);
	
		}
		else
		{

			//echo "url=$url\n";
			$html_content = get($url);
		
			// Need to resolve DOIO, and parameter to get refs, then parse...
			//$html_content = get('https://bioone.org/journals/candollea/volume-66/issue-2/c2011v662a5/Calyptranthera-Viridiflava-Ammann-L-Gaut--Klack-Apocynaceae-sl-Secamonoideae/10.15553/c2011v662a5.short?tab=ArticleLinkReference');
		
			file_put_contents($safe_filename, $html_content);
		}	
	}	
	
	return $html_content;
}

//--------------------------------------------------------------------------------------------------
// extract literature cited from BioOne web page
function get_references_from_html($html_content)
{
	global $doi;
	
	$html = HtmlDomParser::str_get_html($html_content);

	$citations = array();
	
	//foreach($html->find('div[class=ref-content cell] li') as $element)
	foreach($html->find('div[class=ref-content cell]') as $element)
	{
	   //echo $element->innertext . "\n";
	   //echo $element->plaintext . "\n";
	   
	   $citation = new stdclass;
	   
		$value = $element->find('a', 0);
		if ($value)
		{
			if (isset($value->id))
			{
				$citation->key = $value->id;		
			}
		}

		$citation->unstructured = $element->plaintext;
		//$citation->unstructured = $element->innertext;
		$citation->unstructured = preg_replace('/\s\s+/u', ' ', $citation->unstructured);
		$citation->unstructured = preg_replace('/\s+,/u', ',', $citation->unstructured);
		$citation->unstructured = preg_replace('/&amp;/u', '&', $citation->unstructured);
		$citation->unstructured = preg_replace('/Google Scholar/u', '', $citation->unstructured);
		$citation->unstructured = preg_replace('/\s+$/u', '', $citation->unstructured);
		

		$value = $element->find('meta', 0);
		if ($value)
		{
			if (isset($value->content))
			{
				$content = $value->content;
									
				$content= trim($content);
				$content = preg_replace('/;\s*$/u', '', $content);
				
				$parts = preg_split('/; citation_/u', $content);
				$citation->parts = $parts;
				
				foreach ($parts as $part)
				{
					//echo $part . "\n";
					if (preg_match('/(?<key>\w+(_\w+)?)=(?<value>.*)$/Uu', trim($part), $m))
					{
						switch ($m['key'])
						{
							case 'title':
							case 'citation_title':
								$citation->{'article-title'} = $m['value'];
								$citation->{'article-title'} = preg_replace('/\.$/u', '', $citation->{'article-title'});
								break;
								
							case 'volume':
								$citation->{'volume'} = $m['value'];
								break;								
								
							case 'firstpage':
								$citation->{'first-page'} = $m['value'];
								break;
								
							case 'lastpage':
								$citation->{'last-page'} = $m['value'];
								break;
								
							case 'publication_date':
								$citation->{'year'} = $m['value'];
								break;
							
							default:
								break;
					
						}
					}
				
				}
				
					
			}
		}

	   $citation->type = 'generic';
	   $citation->author = array();
	   
	   
	   if (isset($citation->{'year'}))
	   {
	   	$pattern = '/;"\s*[\/]?>\s*(?<authorstring>.*)\s+' . $citation->{'year'} . '/Uu';
	   	
	   	//echo $pattern . "\n";
	   	//echo $element->innertext . "\n";
	   	
	   	
	   	if (preg_match($pattern, $element->innertext, $m))
	   	{
	   		$authorstring = $m['authorstring'];
	   		
	   		//echo $authorstring . "\n";
	   		
	   		$authorstring = preg_replace('/^\s+/u', '', $authorstring);
	   		$authorstring = preg_replace('/,?\s+and\s+/u', '|', $authorstring);
	   		$authorstring = preg_replace('/\s*,\s+/u', '|', $authorstring);
	   		$authorstring = preg_replace('/\s+&amp;\s+/u', '|', $authorstring);
	   		$authorstring = preg_replace('/\.([A-Z])/u', '. $1', $authorstring);

	   		//echo $authorstring . "\n";
	   		
	   		$citation->{'author'} = explode('|', $authorstring);
	   		
	   	}
	   	
	   }

		/*
	   if (isset($citation->{'article-title'}))
	   {
	   
	   	$title = $citation->{'article-title'};
	   	$title  = str_replace('(', '\(', $title);
	   	$title  = str_replace(')', '\)', $title);
	   	$title  = str_replace('[', '\[', $title);
	   	$title  = str_replace('[', '\]', $title);
	   	$title  = str_replace('?', '\?)', $title);
	   	
	   	
	   	$pattern = '/' . $title . '\s+<i>(?<journal>[^<]+)\/i>/Uu';
	   	
	   	echo $pattern . "\n";
	   	echo $element->innertext . "\n";
	   	
	   	if (preg_match($pattern, $element->innertext, $m))
	   	{
	   		$citation->{'journal-title'} = $m['journal'];
	   	}
	   	
	   }
	   */

	   if (isset($citation->{'volume'}))
	   {
	   	
	   	$pattern = '/[\.|\?]\s+<i>(?<journal>[^<]+)<\/i>(,.*)?\s+' . $citation->{'volume'} . '/Uu';
	   	
	   	//echo $pattern . "\n";
	   	//echo $element->innertext . "\n";
	   	
	   	if (preg_match($pattern, $element->innertext, $m))
	   	{
	   		$citation->{'journal-title'} = $m['journal'];
	   	}
	   	
	   }
	   
	   
	   
   
		//print_r($citation);
		
		echo citation_to_sql($doi, $citation);
	
		$citations[$citation->key] = $citation;
	}	

	/*
	// old
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
			$citation->year = trim($value->plaintext);		
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
			$citation->publisher = str_replace('&amp;', '&', $citation->publisher);
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
	*/
	
	//print_r($citations);
	
	return $citations;
}

$doi = '10.5252/adansonia2018v40a8';
$doi = '10.5252/a2012n2a1';
$doi = '10.3100/025.015.0216';

$doi = '10.5735/085.047.0307';

// new format
$doi = '10.3417/2012042';
$doi = '10.3417/2010121';

$doi = '10.5733/afin.054.0101';

$doi = '10.15553/c2011v662a5';

$url = 'https://bioone.org/journals/Systematic-Botany/volume-37/issue-1/036364412X616639/A-New-Species-of-iThismia-i-Thismiaceae-from-West-Kalimantan/10.1600/036364412X616639.short?tab=ArticleLinkReference';

$url = 'https://bioone.org/journals/systematic-botany/volume-35/issue-1/036364410790862533/An-Extended-Phylogeny-of-Pseuduvaria-Annonaceae-with-Descriptions-of-Three/10.1600/036364410790862533.pdf?tab=ArticleLinkReference';

$url = 'https://bioone.org/journals/systematic-botany/volume-41/issue-1/036364416X690732/Resurrection-and-New-Species-of-the-Neotropical-Genus-Adelonema-Araceae/10.1600/036364416X690732.full?tab=ArticleLinkReference';

$html = get_html($url);

get_references_from_html($html);

/*

$url = 'http://www.bioone.org/doi/abs/10.5252/adansonia2018v40a8';


$html = get($url);

echo $html;*/

// parse





?>
