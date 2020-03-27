<?php

// Simple parser to extract refernece form text from a PDF


//----------------------------------------------------------------------------------------
function is_name($str) {
	$is_name = false;
	
	if (preg_match('/[A-Z][a-zA-Z]+,\s*([A-Z]\.\s*)+/', $str)) {
		$is_name = true;
	}
	
	return $is_name;
}


//----------------------------------------------------------------------------------------
function is_end_of_citation($str, $mean_line_length) {

	// echo $str . "| len =" . strlen($str) . " [" .  $mean_line_length . "]";

	$is_end = false;
	
	// page range
	if (preg_match('/[­|-|—|–|-|‒|-](\d+)\.?\s*$/u', $str)) {
		$is_end = true;
	}
        // page range with translation
	if (preg_match('/[­|-|—|–|-](\d+)\.?\s*(\[|\))[I|i]n\s+[A-Z][a-z]+(\]|\))$/', $str)) {
		$is_end = true;
	}
	
	// DOI
	//  - doi: 10.1080/00222938809460919
	if (preg_match('/(\s+[-|-]\s+)?doi:\s*10\.\d+\/(.*)$/', $str)) {
		$is_end = true;
	}

	if (preg_match('/pp\.(\s+\w+\.)?$/', $str)) {
		$is_end = true;
	}

	// F C Thompson-style references
	if (preg_match('/([0-9]{2}|\?\?)\]$/', $str)) {
		$is_end = true;
	}

	if (preg_match('/\.\]$/', $str)) {
		$is_end = true;
	}

	// pdf]
	if (preg_match('/pdf\]\s*$/', $str)) {
		$is_end = true;
	}

	if (preg_match('/html\.\s*$/', $str)) {
		$is_end = true;
	}
	
	// Zootaxa web site
	if (preg_match('/accessed\s+\d+\s*\w+\s*[0-9]{4}\)$/', $str)) {
		$is_end = true;
	}	

	if (!$is_end)
	{
		if (preg_match('/\.$/', $str)) {
			if (strlen($str) < $mean_line_length) {
				if (!is_name($str)) {
					$is_end = true;
				}
			}
		}
	}
	
	/*
	if ($is_end)
	{
		echo " y \n";
	
	}
	else
	{
		echo " n \n";
	}
	*/
	
	return $is_end;
}

//----------------------------------------------------------------------------------------
function extract_citations ($pages) {

	$citations = array();
	
	$sum_line_length = 0;
	$num_lines = 0;
	
	foreach ($pages as $page) {
		$lines = explode("\n", $page);
		$num_lines += count($lines);
		
		foreach ($lines as $line)
		{
			$sum_line_length += strlen($line);
		}
	}
	$mean_line_length = $sum_line_length / $num_lines;
	

	$STATE_START = 0;
	$STATE_IN_REFERENCES = 1;
	$STATE_OUT_REFERENCES = 2;
	$STATE_START_CITATION = 3;
	$STATE_END_CITATION = 4;

	$state = $STATE_START;
	//$state = $STATE_IN_REFERENCES;

	$citation = '';
	
	foreach ($pages as $page_num => $page) {
	
		// echo "--- $page_num ---\n";
	
                // case where newlines are escaped, e.g Journal of Hymenoptera Research
 		$lines = explode("\n", $page);
						
		$n = count($lines);
		$line_number = 0;
		
		//print_r($lines);

		// Handle hyphenation
		$hyphens = array();
		$hyphens[0] = 0;

		// Skip running head
		$line_number++;
	
		// Hyphen
		$last_line_had_hyphen = false;

		while (($state != $STATE_OUT_REFERENCES) && ($line_number < $n)) {
		
			//echo $state . "\n";
		
			$line = $lines[$line_number];	
			$line = preg_replace('/^\s+/', '', $line);
			$line = preg_replace('/\s+$/', '', $line);
						
			// Trim and flag hyphenation
			if (preg_match('/[A-Za-z][-|­]\s*$/', $line)) {
				$line = preg_replace('/[-|­]\s*$/', '', $line);
				$hyphens[$line_number] = 1;
			} else {
				$hyphens[$line_number] = 0;
			}
									
			switch ($state) {
				case $STATE_START:
					// Look for references
					if (preg_match('/^\s*(REFERENCES|LITERATURE CITED|ZOOTAXA References)$/i', $line)) {
						// Ignore table of contents
						if (preg_match('/\.?\s*[0-9]$/', $line)) {
						} else {
							$state = $STATE_IN_REFERENCES;
						}
					}
					break;
				
				case $STATE_IN_REFERENCES:
					if (preg_match('/^([A-Z]|\.\s*[0-9]{4})/', $line))
					{
						if (preg_match('/^((Note[s]? added in proof)|(Appendix)|(Buchbesprechungen)|(Figure)|(Index))/i', $line)) {
							$state = $STATE_OUT_REFERENCES;
						} else {
							$state = $STATE_START_CITATION;
							$citation = $line;
							if (is_end_of_citation($line, $mean_line_length)) {
								$citations[] = $citation;
								$state = $STATE_IN_REFERENCES;
							}
						}
					}
					break;
				
				case $STATE_START_CITATION:
					if ($hyphens[$line_number - 1] == 0) {
						$citation .= ' ';
					}
					$citation .= $line;
					if (is_end_of_citation($line, $mean_line_length))
					{
						$citations[] = $citation;
						
						$state = $STATE_IN_REFERENCES;
					}
					break;
				
				default:
					break;
				
			}
			$line_number++;

		}

	}	
	
	
	return $citations;
}


?>
