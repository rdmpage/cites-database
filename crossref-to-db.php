<?php

// Citation from CrossRef DOI

require_once(dirname(__FILE__) . '/cites.php');

$dois = array(
	'10.1017/S0960428600003164',
);

foreach ($dois as $doi)
{
	echo "-- $doi\n";
	
	crossref($doi);
}


?>
