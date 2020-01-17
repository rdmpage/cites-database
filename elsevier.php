<?php

require_once(dirname(__FILE__) . '/shared.php');

$filename = 'PII-S0254629916339242.xml';

$xml = file_get_contents($filename);

$dom= new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);

$xpath->registerNamespace(   'ce', 'http://www.elsevier.com/xml/common/dtd');
$xpath->registerNamespace(   'sb', 'http://www.elsevier.com/xml/common/struct-bib/dtd');
$xpath->registerNamespace('prism', 'http://prismstandard.org/namespaces/basic/2.0/');

$guid = '';

$xpath_query = '//prism:doi';
$nodeCollection = $xpath->query ($xpath_query);
foreach($nodeCollection as $node)
{
	$guid = $node->firstChild->nodeValue;
}

$xpath_query = '//ce:bib-reference';
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
    
    /*
    <sb:authors>
	  <sb:author>
		 <ce:given-name>J.E.</ce:given-name>
		 <ce:surname>Victor</ce:surname>
	  </sb:author>
	</sb:authors>
    */
    
    
    // authors
    $nc = $xpath->query ('sb:reference/sb:contribution/sb:authors/sb:author', $node);
	foreach($nc as $n)
	{
		$parts = array();
		
		$ncc = $xpath->query ('ce:given-name', $n);
		foreach($ncc as $nc)
		{
			$parts[] = $nc->firstChild->nodeValue;
    	}
		$ncc = $xpath->query ('ce:surname', $n);
		foreach($ncc as $nc)
		{
			$parts[] = $nc->firstChild->nodeValue;
    	}
		
		$name = join(' ', $parts);
		$name = preg_replace('/\./u', '. ', $name);
		$name = preg_replace('/\s\s+/u', ' ', $name);
		$name = preg_replace('/-\s/u', '-', $name);
	
    	$citation->author[] = $name;
    }
    
    // title
    $nc = $xpath->query ('sb:reference/sb:contribution/sb:title/sb:maintitle', $node);
	foreach($nc as $n)
	{
		$citation->{'article-title'} = $n->nodeValue;
		$citation->{'article-title'} = preg_replace('/\s\s+/u', ' ', $citation->{'article-title'});
    }        
    
    // journal article
    /*
		<sb:host>
		   <sb:issue>
			  <sb:series>
				 <sb:title>
					<sb:maintitle>Molecular Phylogenetics and Evolution</sb:maintitle>
				 </sb:title>
				 <sb:volume-nr>61</sb:volume-nr>
			  </sb:series>
			  <sb:date>2011</sb:date>
		   </sb:issue>
		   <sb:pages>
			  <sb:first-page>593</sb:first-page>
			  <sb:last-page>601</sb:last-page>
		   </sb:pages>
		</sb:host>
	*/

    $nc = $xpath->query ('sb:reference/sb:host/sb:issue', $node);
	foreach($nc as $n)
	{
		$citation->type = 'article-journal';
	
		$ncc = $xpath->query ('sb:date', $n);
		foreach($ncc as $nc)
		{
			$citation->{'year'} = $nc->firstChild->nodeValue;
    	}
    	
		$ncc = $xpath->query ('sb:series/sb:volume-nr', $n);
		foreach($ncc as $nc)
		{
			$citation->{'volume'} = $nc->firstChild->nodeValue;
    	}

		$ncc = $xpath->query ('sb:series/sb:title/sb:maintitle', $n);
		foreach($ncc as $nc)
		{
			$citation->{'journal-title'} = $nc->firstChild->nodeValue;
    	}
    	
 	}
 	
 	/*
	   <sb:host>
		   <sb:book>
			  <sb:edition>2nd edition</sb:edition>
			  <sb:book-series>
				 <sb:series>
					<sb:title>
					   <sb:maintitle>Botanical Survey Mem</sb:maintitle>
					</sb:title>
					<sb:volume-nr>25</sb:volume-nr>
				 </sb:series>
			  </sb:book-series>
			  <sb:date>1951</sb:date>
			  <sb:publisher>
				 <sb:name>Department of Agriculture</sb:name>
				 <sb:location>Pretoria</sb:location>
			  </sb:publisher>
		   </sb:book>
		</sb:host>
	*/
 	
    $nc = $xpath->query ('sb:reference/sb:host/sb:book', $node);
	foreach($nc as $n)
	{
		$citation->type = 'book';
	
		$ncc = $xpath->query ('sb:date', $n);
		foreach($ncc as $nc)
		{
			$citation->{'year'} = $nc->firstChild->nodeValue;
    	}
    	
		$ncc = $xpath->query ('sb:book-series/sb:volume-nr', $n);
		foreach($ncc as $nc)
		{
			$citation->{'volume'} = $nc->firstChild->nodeValue;
    	}

		$ncc = $xpath->query ('sb:book-series/sb:title/sb:maintitle', $n);
		foreach($ncc as $nc)
		{
			$citation->{'series-title'} = $nc->firstChild->nodeValue;
    	}
    	
		$ncc = $xpath->query ('sb:book-series/sb:title/sb:maintitle', $n);
		foreach($ncc as $nc)
		{
			$citation->{'series-title'} = $nc->firstChild->nodeValue;
    	}
    	
		$ncc = $xpath->query ('sb:publisher/sb:name', $n);
		foreach($ncc as $nc)
		{
			$citation->{'publisher'} = $nc->firstChild->nodeValue;
    	}

		$ncc = $xpath->query ('sb:publisher/sb:location', $n);
		foreach($ncc as $nc)
		{
			$citation->{'publoc'} = $nc->firstChild->nodeValue;
    	}   	
    	
 	}
 	
/*
	<sb:host>
	   <sb:edited-book>
		  <sb:editors>
			 <sb:editor>
				<ce:given-name>J.</ce:given-name>
				<ce:surname>Manning</ce:surname>
			 </sb:editor>
			 <sb:editor>
				<ce:given-name>P.</ce:given-name>
				<ce:surname>Goldblatt</ce:surname>
			 </sb:editor>
		  </sb:editors>
		  <sb:title>
			 <sb:maintitle>Plants of the Greater Cape Floristic Region 1: The Core Cape Flora</sb:maintitle>
		  </sb:title>
		  <sb:book-series>
			 <sb:series>
				<sb:title>
				   <sb:maintitle>Strelitzia</sb:maintitle>
				</sb:title>
				<sb:volume-nr>29</sb:volume-nr>
			 </sb:series>
		  </sb:book-series>
		  <sb:date>2012</sb:date>
		  <sb:publisher>
			 <sb:name>South African National Biodiversity Institute</sb:name>
			 <sb:location>Pretoria</sb:location>
		  </sb:publisher>
	   </sb:edited-book>

*/ 	


                              
                              
    $nc = $xpath->query ('sb:reference/sb:host/sb:edited-book', $node);
	foreach($nc as $n)
	{
		$citation->type = 'chapter';
		
		// editors
		$editors = $xpath->query ('sb:editors/sb:editor', $n);
		foreach($editors as $editor)
		{
			$parts = array();
		
			$tags = $xpath->query ('ce:given-name', $editor);
			foreach($tags as $tag)
			{
				$parts[] = $tag->firstChild->nodeValue;
			}
			$tags = $xpath->query ('ce:surname', $editor);
			foreach($tags as $tag)
			{
				$parts[] = $tag->firstChild->nodeValue;
			}
		
			$name = join(' ', $parts);
			$name = preg_replace('/\./u', '. ', $name);
			$name = preg_replace('/\s\s+/u', ' ', $name);
			$name = preg_replace('/-\s/u', '-', $name);
	
			$citation->editor[] = $name;
		}		
	
		$ncc = $xpath->query ('sb:date', $n);
		foreach($ncc as $nc)
		{
			$citation->{'year'} = $nc->firstChild->nodeValue;
    	}
    	
		$ncc = $xpath->query ('sb:book-series/sb:series/sb:volume-nr', $n);
		foreach($ncc as $nc)
		{
			$citation->{'volume'} = $nc->firstChild->nodeValue;
    	}

/*
<sb:host>
                           <sb:edited-book>
                              <sb:editors>
                                 <sb:editor>
                                    <ce:given-name>W.T.</ce:given-name>
                                    <ce:surname>Thiselton-Dyer</ce:surname>
                                 </sb:editor>
                              </sb:editors>
                              <sb:title>
                                 <sb:maintitle>Asclepiadeae</sb:maintitle>
                              </sb:title>
                              <sb:book-series>
                                 <sb:series>
                                    <sb:title>
*/

		$ncc = $xpath->query ('sb:book-series/sb:series/sb:title/sb:maintitle', $n);
		foreach($ncc as $nc)
		{
			$citation->{'series-title'} = $nc->firstChild->nodeValue;
    	}	
    	
		$ncc = $xpath->query ('sb:publisher/sb:name', $n);
		foreach($ncc as $nc)
		{
			$citation->{'publisher'} = $nc->firstChild->nodeValue;
    	}

		$ncc = $xpath->query ('sb:publisher/sb:location', $n);
		foreach($ncc as $nc)
		{
			$citation->{'publoc'} = $nc->firstChild->nodeValue;
    	} 
    	
		$ncc = $xpath->query ('sb:title/sb:maintitle', $n);
		foreach($ncc as $nc)
		{
			$citation->{'volume-title'} = $nc->firstChild->nodeValue;
    	}
	}

 	
	// pages
    $nc = $xpath->query ('sb:reference/sb:host/sb:pages', $node);
	foreach($nc as $n)
	{
		$ncc = $xpath->query ('sb:first-page', $n);
		foreach($ncc as $nc)
		{
			$citation->{'first-page'} = $nc->firstChild->nodeValue;
    	}

		$ncc = $xpath->query ('sb:last-page', $n);
		foreach($ncc as $nc)
		{
			$citation->{'last-page'} = $nc->firstChild->nodeValue;
    	}

     }

    
    // cleanup
    if (count($citation->editor) == 0)
    {
    	unset($citation->editor);
    }
         
     
    // enhance
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
    
    //print_r($citation);
    
    echo citation_to_sql($guid, $citation);
    
}




?>
