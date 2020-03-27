<?php

// Extract text from Zootaxa PDF and parse references

require_once(dirname(__FILE__) . '/text_to_refs.php');
require_once(dirname(__FILE__) . '/shared.php');

$debug = false;
//$debug = true;

$enhance = false;
$enhance = true;

$guid = '10.11646/zootaxa.4154.5.1';
$filename = 'The_Peacock_Spiders_Araneae_Salticidae_M.pdf';

$basefilename = basename($filename, ".pdf");

$textfilename = $basefilename . ".txt";

if (!file_exists($textfilename))
{
	$command = 'pdftotext -enc UTF-8 -layout ' . $filename ;
	system($command);

}


$text = file_get_contents($textfilename);

$pages = explode("\f", $text);

if ($debug)
{
	print_r($pages);
}

$citations_strings = extract_citations($pages);

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
	
	if (!$matched)
	{
		if (preg_match('/(?<authorstring>.*)\s+\((?<year>[0-9]{4})[a-z]?\)\s+(?<title>.*)\.\s+(?<journal>.*),(\s*\((?<series>[^\)]+)\))?\s+(?<volume>\d+)(\s*\((?<issue>[^\)]+)\))?,\s+(?<spage>\d+)([‒|–|-](?<epage>\d+))?\./u', $string, $m))
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





