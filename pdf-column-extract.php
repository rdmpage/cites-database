<?php

// Extract text from PDF with two columns and parse references

require_once(dirname(__FILE__) . '/text_to_refs.php');
require_once(dirname(__FILE__) . '/shared.php');

$debug = false;
$debug = true;

$enhance = false;
//$enhance = true;

$basedir 	= 'raheem2014';
$guid 		= 'x';
$filename 	= 'raheem2014refs.pdf';


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

if (!file_exists($textcolumns_filename))
{
	$new_text = '';

	foreach ($pages as $page)
	{
		$columns = split_column($page);
		
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

print_r($split_pages);


// extract references
$citationsfilename = $basefilename . ".refs.txt";

if (!file_exists($citationsfilename))
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
	$YEAR				= '(?<year>[0-9]{4})[a-z]?';
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
	
	
	// add identifiers
	if ($matched)
	{
		// CrossRef DOI
		if ($enhance)
		{
			$doi = find_doi($citation->unstructured);
			if ($doi != '')
			{
				$citation->{'rdmp_doi'} = strtolower($doi);
			}    
		}	
		
		// local match
		if ($enhance)
		{
			$rdmp_guid = find_local($citation->unstructured);
			if ($rdmp_guid != '')
			{
				$citation->{'rdmp_guid'} = $rdmp_guid;
				
				if (preg_match('/10\.\d+\//', $rdmp_guid))
				{
					$citation->{'rdmp_doi'} = $rdmp_guid;
				}
			}    
		}
	
	}
	
	if ($debug)
	{
		print_r($citation);	
	}
	
	echo citation_to_sql($guid, $citation);	
	
	
	if (!$matched)
	{
		$failed[] = $string;
	}


}

if ($debug)
{
	print_r($failed);	
}





