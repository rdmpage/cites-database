<?php

// Parse Pensoft JATS and extract references


require_once(dirname(__FILE__) . '/shared.php');

$filename = '39128.xml';

$xml = file_get_contents($filename);

$dom= new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);


$xpath->registerNamespace(   'xlink', 'http://www.w3.org/1999/xlink');


$guid = '';

$xpath_query = '//article/front/article-meta/article-id[@pub-id-type="doi"]';
$nodeCollection = $xpath->query ($xpath_query);
foreach($nodeCollection as $node)
{
	$guid = $node->firstChild->nodeValue;
}

//echo $guid . "\n";

$xpath_query = '//back/ref-list/ref';
$nodeCollection = $xpath->query ($xpath_query);
foreach($nodeCollection as $node)
{
	if ($node->hasAttributes()) 
	{ 
		$attributes = array();
		$attrs = $node->attributes; 
		
		foreach ($attrs as $i => $attr)
		{
			$attributes[$attr->name] = $attr->value; 
		}
		
		$key = $attributes['id'];
	}
    // reference
    
    $citation = new stdclass;
    
    // default
    $citation->type = 'article-journal';
    
    $citation->key = $key;
    
    $citation->author = array();
    $citation->editor = array();	
    
    // mixed-citation
    
    $citation->unstructured = $node->nodeValue;
    $citation->unstructured = trim($citation->unstructured);

    $nc = $xpath->query ('mixed-citation/person-group/name', $node);
	foreach($nc as $n)
	{
		$parts = array();
		
		$ncc = $xpath->query ('given-names', $n);
		foreach($ncc as $nc)
		{
			$parts[] = $nc->firstChild->nodeValue;
    	}
		$ncc = $xpath->query ('surname', $n);
		foreach($ncc as $nc)
		{
			$parts[] = $nc->firstChild->nodeValue;
    	}
		
		$name = join(' ', $parts);
		$name = preg_replace('/\./u', '. ', $name);
		$name = preg_replace('/\s\s+/u', ' ', $name);
		$name = preg_replace('/-\s/u', '-', $name);
		$name = preg_replace('/([A-Z])([A-Z])\s/u', '$1 $2 ', $name);
	
    	$citation->author[] = $name;
    }
    
    $nc = $xpath->query ('mixed-citation/article-title', $node);
	foreach($nc as $n)
	{
		$citation->{'article-title'} = $n->nodeValue;
    }
    
    $nc = $xpath->query ('mixed-citation/source', $node);
	foreach($nc as $n)
	{
		$citation->{'journal-title'} = $n->firstChild->nodeValue;
    }
    
    $nc = $xpath->query ('mixed-citation/publisher-name', $node);
	foreach($nc as $n)
	{
		$citation->{'publisher'} = $n->nodeValue;
		$citation->type = 'book';
    }

    $nc = $xpath->query ('mixed-citation/publisher-loc', $node);
	foreach($nc as $n)
	{
		$citation->{'publoc'} = $n->nodeValue;
    } 
    
    $nc = $xpath->query ('mixed-citation/year', $node);
	foreach($nc as $n)
	{
		$citation->year = $n->firstChild->nodeValue;
		$citation->year = preg_replace('/[a-z]/', '', $citation->year);
    }
    
    $nc = $xpath->query ('mixed-citation/volume', $node);
	foreach($nc as $n)
	{
		$citation->volume = $n->firstChild->nodeValue;
    }
 
     $nc = $xpath->query ('mixed-citation/issue', $node);
	foreach($nc as $n)
	{
		$citation->issue = $n->firstChild->nodeValue;
    }

    $nc = $xpath->query ('mixed-citation/fpage', $node);
	foreach($nc as $n)
	{
		$citation->spage = $n->firstChild->nodeValue;
    }

    $nc = $xpath->query ('mixed-citation/lpage', $node);
	foreach($nc as $n)
	{
		$citation->epage = $n->firstChild->nodeValue;
    }
   
    $nc = $xpath->query ('mixed-citation/ext-link[@ext-link-type="doi"]/@xlink:href', $node);
	foreach($nc as $n)
	{
		$citation->doi = $n->firstChild->nodeValue;
    }

    $nc = $xpath->query ('mixed-citation/ext-link[@ext-link-type="uri"]/@xlink:href', $node);
	foreach($nc as $n)
	{
		$citation->url = $n->firstChild->nodeValue;
    }
  
    // print_r($citation);
    
    // cleanup
    if (count($citation->editor) == 0)
    {
    	unset($citation->editor);
    }
         
     
    // enhance
    if (0)
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
	
		if (1)
		{
			$doi = find_doi(join(' ', $terms));
			if ($doi != '')
			{
				$citation->{'rdmp_doi'} = strtolower($doi);
			}    
		}
    }
    
    
    //print_r($citation);
    
    echo citation_to_sql($guid, $citation);
    
	
	
}


?>
