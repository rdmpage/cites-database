<?php

// Extract text from PDF with two columns and parse references

require_once(dirname(__FILE__) . '/text_to_refs.php');
require_once(dirname(__FILE__) . '/shared.php');

// Are we going to start from scratch?
$force = false;
//$force = true;

// Aree we debugging?
$debug = false;
//$debug = true;

// Are we going to look for identifiers?
$enhance_doi = true;
$enhance_local = false;


$basedir 	= 'raheem2014';
$guid 		= '9786165518000';
$filename 	= 'raheem2014refs.pdf';

$basedir 	= 'frost';
$guid 		= '10.1206/0003-0090(2006)297[0001:tatol]2.0.co;2';
$filename 	= 'frostetal.pdf';

$basedir 	= 'raffles';
$guid 		= 'CAABF079-BFA8-48C9-986C-2BD100B3CB7E';
$filename 	= '62rbz330-338.pdf';


$basefilename = $basedir . '/' . basename($filename, ".pdf");


// extract text
$textfilename = $basefilename . ".txt";

if (!file_exists($textfilename))
{
	$command = 'pdftotext -enc UTF-8 -layout ' . $basedir . '/' . $filename ;
	system($command);
}

$text = file_get_contents($textfilename);

// get pages
$pages = explode("\f", $text);

if ($debug)
{
	print_r($pages);
}

// extract text from columns
$textcolumns_filename = $basefilename . ".columns.txt";

if (!file_exists($textcolumns_filename) || $force)
{
	$new_text = '';

	foreach ($pages as $page)
	{
		$columns = split_column($page, true);
		
		// blank first line as "header"
		$new_text .= "\n";
	
		$new_text .= join("\n", $columns[0]) . "\n" . join("\n", $columns[1]) . "\n";
		
		$new_text .= "\f";
	}

	file_put_contents($textcolumns_filename, $new_text);
}

$text = file_get_contents($textcolumns_filename);

// extract reference strings
$split_pages = explode("\f", $text);


if ($debug && 0)
{
	print_r($split_pages);
}



// extract references
$citationsfilename = $basefilename . ".refs.txt";

if (!file_exists($citationsfilename) || $force)
{
	$citations_strings = extract_citations($split_pages);
    file_put_contents($citationsfilename, join("\n", $citations_strings ));
}

$citations_text = file_get_contents($citationsfilename);
$citations_strings = explode("\n", $citations_text);

if ($debug)
{
	print_r($citations_strings);
}

$failed = array();

$count = 1;

foreach ($citations_strings as $string)
{

	//echo $string . "\n";

	$matched = false;
	
	$citation = new stdclass;
	$citation->key = "ref" . $count++;
	$citation->unstructured = $string;
	
	// paterns
	$YEAR				= '(?<year>[0-9]{4})[a-z]?(\s+‘‘[0-9]{4}’’)?';
	$PAGES				= '(?<spage>[e|D]?\d+)([‒|–|-](?<epage>\d+))?';
	$LOCATION			= '(?<volume>\d+(\.\d+)?)(\s*\((?<issue>[^\)]+)\))?';
	
	// Zootaxa
	if (!$matched)
	{
		if (preg_match('/(?<authorstring>.*)\s+\((?<year>[0-9]{4})[a-z]?\)\s+(?<title>.*)[\.|\?]\s+(?<journal>.*),(\s*\((?<series>[^\)]+)\))?\s+(?<volume>\d+)(\s*\((?<issue>[^\)]+)\))?,\s+(?<spage>[e|D]?\d+)([‒|–|-](?<epage>\d+))?\./u', $string, $m))
		{
			//print_r($m);
			$matched = true;
			
			$key_map = array(
				//'authorstring' 	=> 'author',
				'year'			=> 'year',
				'title'			=> 'article-title',
				'journal'		=> 'journal-title',
				'volume'		=> 'volume',
				'issue'			=> 'issue',
				'spage'			=> 'first-page',
				'epage'			=> 'last-page',
				'doi'			=> 'doi',				
			);
			
			foreach ($key_map as $k => $v)
			{
				if (isset($m[$k]) && $m[$k] != '')
				{
					$citation->{$v} = $m[$k];
				}
			}
			
			$authorstring = $m['authorstring'];

			$authorstring = preg_replace('/\.([A-Z])\./u', '. $1.', $authorstring);
			
			$authorstring = preg_replace('/\.,\s+/u', '.|', $authorstring);
			$authorstring = preg_replace('/\s+&\s+/u', '|', $authorstring);
			
			$citation->author = explode("|", $authorstring);
			
			if ($debug)
			{
				print_r($citation);	
			}
		
		}
	
	}
	
	if (!$matched)
	{
		if (preg_match('/(?<authorstring>.*)\s+' . $YEAR . '\.\s+(?<title>.*)\.\s+(?<journal>.*)\s+' . $LOCATION . ':\s+' . $PAGES . '\b/u', $string, $m))
		{
			//print_r($m);
			$matched = true;
			
			$key_map = array(
				//'authorstring' 	=> 'author',
				'year'			=> 'year',
				'title'			=> 'article-title',
				'journal'		=> 'journal-title',
				'volume'		=> 'volume',
				'issue'			=> 'issue',
				'spage'			=> 'first-page',
				'epage'			=> 'last-page',
				'doi'			=> 'doi',				
			);
			
			foreach ($key_map as $k => $v)
			{
				if (isset($m[$k]) && $m[$k] != '')
				{
					$citation->{$v} = $m[$k];
				}
			}
			
			$authorstring = $m['authorstring'];

			$authorstring = preg_replace('/\.([A-Z])\./u', '. $1.', $authorstring);
			
			$authorstring = preg_replace('/\.,\s+/u', '.|', $authorstring);
			$authorstring = preg_replace('/\s+&\s+/u', '|', $authorstring);
			$authorstring = preg_replace('/\s+and\s+/u', '|', $authorstring);
			
			$citation->author = explode("|", $authorstring);
			
			if ($debug)
			{
				print_r($citation);	
			}
		
		}
	
	}
	
	
	
	
	// cleanup
	if (isset($citation->{'journal-title'}))
	{
		$citation->{'journal-title'} = preg_replace('/,$/', '', $citation->{'journal-title'});
	}
	
	
	// add identifiers
	if (isset($citation->unstructured))
	{
		// CrossRef DOI
		if ($enhance_doi)
		{
			$doi = find_doi($citation->unstructured);
			if ($doi != '')
			{
				$citation->{'rdmp_doi'} = strtolower($doi);
				
				echo $doi . "\n";
			}    
		}	
		
		// local match
		if ($enhance_local)
		{
			$rdmp_guid = find_local($citation->unstructured);
			if ($rdmp_guid != '')
			{
				$citation->{'rdmp_guid'} = $rdmp_guid;
				
				if (preg_match('/10\.\d+\//', $rdmp_guid))
				{
					$citation->{'rdmp_doi'} = $rdmp_guid;
				}
				
				echo $rdmp_guid . "\n";
			}    
		}
	
	}
	
	if ($debug)
	{
		print_r($citation);	
	}
	
	//echo citation_to_sql($guid, $citation);	
	
	
	if (!$matched)
	{
		$failed[] = $string;
	}


}

if ($debug)
{
	print_r($failed);	
}





