<?php

// Taiwania

require (dirname(__FILE__) . '/shared.php');

//----------------------------------------------------------------------------------------
function get_html($url)
{
	$data = null;
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_HTTPHEADER => array(
			"User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405" 
	  	) ,
	  CURLOPT_COOKIEJAR		=> 'cookie.txt'	  
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}


//----------------------------------------------------------------------------------------
function get_redirect($url, $userAgent = '', $content_type = '')
{
	$redirect = '';
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => FALSE,
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
	
	$http_code = $info['http_code'];
	
	if ($http_code == 303)
	{
		$redirect = $info['redirect_url'];
	}
	
	if ($http_code == 302)
	{
		$redirect = $info['redirect_url'];
	}
	return $redirect;
}

//----------------------------------------------------------------------------------------
function post($url, $post_data)
{
	$redirect = '';
	
	$opts = array(
	  CURLOPT_URL => $url,
	  CURLOPT_POST => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_POSTFIELDS => $post_data,
	  CURLOPT_HTTPHEADER => array(
			"User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405",
		  	'Content-Type: application/x-www-form-urlencoded',
	  	) ,
	  CURLOPT_COOKIEJAR		=> 'cookie.txt'	  
	  	
 
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
	
	//print_r($info);

}



$dois = array(
'10.6165/tai.2016.61.319',
//'10.6165/tai.2013.58.251',
//'10.6165/tai.2017.62.105',
);

foreach ($dois as $doi)
{
	$url = get_redirect('https://doi.org/' . $doi);
		
	$html = get_html($url);
		
	$parameters = str_replace('http://www.airitilibrary.com/Publication/alDetailedMesh?', '', $url);
	
	$json = post('http://www.airitilibrary.com/Publication/ReferInfo', $parameters);
	
	$obj = json_decode($json);
	
	
	$count = 1;
	
	foreach ($obj as $reference)
	{
		$csl = new stdclass;
		$csl->id = $count++;
		
		$parsed = false;
		
		//echo $reference . "\n";
		
		if (!$parsed)
		{
			if (preg_match('/(?<authorstring>.*)\.\s+(?<year>[0-9]{4})[a-z]?\.\s+(?<title>.*)\.\s+(?<journal>.*)\s+(?<volume>\d+)(\s*\((?<issue>.*)\))?:\s+(?<spage>\d+)(\s*–(?<epage>\d+))?\.\s*https?:\/\/(dx.)?doi.\s*org\/(?<doi>.*)\s+<br\/>/Uu', $reference, $m))
			{
				//print_r($m);
				
				$parsed = true;
				
				$m['journal'] = str_replace('– ', '', $m['journal']);
			
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
					if (isset($m[$k]))
					{
						$csl->{$v} = $m[$k];
					}
				}
				
				$authorstring = $m['authorstring'];
				
				$authorstring = preg_replace('/,\s+/u', '|', $authorstring);
				$authorstring = preg_replace('/\s+and\s+/u', '|', $authorstring);
				
				$csl->author = explode("|", $authorstring);
			}
		}
		
		if (!$parsed)
		{
			if (preg_match('/(?<authorstring>.*)\.\s+(?<year>[0-9]{4})[a-z]?\.\s+(?<title>.*)\.\s+(?<journal>.*)\s+(?<volume>\d+)(\s*\((?<issue>.*)\))?:\s+(?<spage>\d+)(\s*–(?<epage>\d+))?\./Uu', $reference, $m))
			{
				//print_r($m);
				
				$parsed = true;
				
				$m['journal'] = str_replace('– ', '', $m['journal']);
			
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
					if (isset($m[$k]))
					{
						$csl->{$v} = $m[$k];
					}
				}
				
				$authorstring = $m['authorstring'];
				
				$authorstring = preg_replace('/,\s+/u', '|', $authorstring);
				$authorstring = preg_replace('/\s+and\s+/u', '|', $authorstring);
				
				$csl->author = explode("|", $authorstring);
			}
		}		
		
		
		if (!$parsed)
		{
			$csl->unstructured = strip_tags($reference);
			
			//print_r($csl);
		
		}
		
		citation_to_sql($doi, $csl);
		
		
	
	}
	
	
	
}



?>
