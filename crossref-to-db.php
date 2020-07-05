<?php

// Citation from CrossRef DOI

require_once(dirname(__FILE__) . '/cites.php');

$dois = array(
//	'10.1017/S0960428600003164',
//	'10.1080/03946975.2005.10531220',
//	'10.1080/00305316.1998.10433763',
	'10.1644/1545-1542(2002)083<0408:SOOWDO>2.0.CO;2'
);

foreach ($dois as $doi)
{
	echo "-- $doi\n";
	
	crossref($doi);
}


?>
