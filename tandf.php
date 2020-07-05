<?php


// Extract more citation data from TandF website and update existing data from CrossRef 
// CrossRef metadata tends to be just the triple to try and get a DOI, whereas the web 
// site has more info that we can use to find the reference.


require_once 'vendor/autoload.php';
use Sunra\PhpSimple\HtmlDomParser;

require_once ('shared.php');

global $doi;

//----------------------------------------------------------------------------------------
function get_nice($url)
{	
	$data = null;

	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_COOKIEJAR		=> 'cookie.txt'
	);


	
	$opts[CURLOPT_HTTPHEADER] = array(
		'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
		/*'Accept-encoding: gzip, deflate',*/
		'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36',
		'Accept-Language: en-gb',
	);	
	
	
	
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
function get_references_from_html($html_content)
{
	global $doi;
	
	$html = HtmlDomParser::str_get_html($html_content);

	$citations = array();
	
	foreach($html->find('ul[class=references numeric-ordered-list] li') as $element)
	{
	   //echo $element->innertext . "\n";
	   //echo $element->plaintext . "\n";
	   
	   $citation = new stdclass;
   
	   $citation = new stdclass;
	   $citation->key = $element->id;
	   $citation->unstructured = $element->plaintext;
	   $citation->unstructured = preg_replace('/\s\s+/u', ' ', $citation->unstructured);
	   $citation->unstructured = preg_replace('/\s\./u', '.', $citation->unstructured);
	   $citation->unstructured = preg_replace('/\s\–/u', '–', $citation->unstructured);
	   $citation->unstructured = preg_replace('/\.\./u', '.', $citation->unstructured);
	   
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
		//unset($citation->type);
		
		//print_r($citation);
	
		$citations[$citation->key] = $citation;
	}
	
	
	//print_r($citations);
	
	return $citations;
}


$doi = '10.1080/03946975.2005.10531220';
$doi = '10.1080/00305316.1998.10433763';

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
	$url = 'https://www.tandfonline.com/doi/ref/' . $doi;
	$html_content = get_nice($url);
	file_put_contents($safe_filename, $html_content);
}



$citations = get_references_from_html($html_content);

print_r($citations);

//echo $html;

foreach ($citations as $citation)
{
	// update
	
	$terms = array();
	
	if (isset($citation->unstructured))
	{
		$terms[] = 'unstructured="' . addcslashes($citation->unstructured, '"') . '"';
	}
	
	if (isset($citation->{'article-title'}))
	{
		$terms[] = '`article-title`="' . addcslashes($citation->{'article-title'}, '"') . '"';
	}

	if (isset($citation->{'last-page'}))
	{
		$terms[] = '`last-page`="' . addcslashes($citation->{'last-page'}, '"') . '"';
	}
	
	
	echo 'UPDATE cites SET ' . join(',', $terms) . ' WHERE guid="' . $doi . '" AND `key`="' . $citation->key . '";' . "\n";


}






?>
