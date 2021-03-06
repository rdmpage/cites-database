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
function is_end_of_citation($str, $mean_line_length, $debug = false) {

	if ($debug)
	{
		echo $str . "| len =" . strlen($str) . " [" .  $mean_line_length . "]";
	}

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

	// .pdf
	if (preg_match('/\.pdf\s*$/', $str)) {
		$is_end = true;
	}
	
	// YouTube
	//v=9GgAbyYDFeg
	if (preg_match('/v=[0-9A-Za-z\-]+$/', $str)) {
		$is_end = true;
	}
	
	// plates
	// plate XVII.
	if (preg_match('/plate [ixvl]+\.$/i', $str)) {
		$is_end = true;
	}

	if (preg_match('/html\.\s*$/', $str)) {
		$is_end = true;
	}
	
	// Zootaxa web site
	if (preg_match('/accessed\s+\d+\s*\w+\s*[0-9]{4}\)\s*$/', $str)) {
		$is_end = true;
	}
	
	// DOI in Zootaxa
	// https://doi.org/10.1038/494035c
	if (preg_match('/https:\/\/doi.org\/10\.\d+\/[^\b]+$/', $str)) {
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
	
	if ($debug)
	{
		if ($is_end)
		{
			echo " y \n";
	
		}
		else
		{
			echo " n \n";
		}
	}
	
	return $is_end;
}

//----------------------------------------------------------------------------------------
function extract_citations ($pages, $debug = false) {
	
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
	
	$STATE_START 			= 0;
	$STATE_IN_REFERENCES 	= 1;
	$STATE_OUT_REFERENCES 	= 2;
	$STATE_START_CITATION 	= 3;
	$STATE_END_CITATION 	= 4;

	$state = $STATE_START;
	//$state = $STATE_IN_REFERENCES;

	$citation = '';
	
	foreach ($pages as $page_num => $page) {
	
		if ($debug)
		{
		 	echo "--- $page_num ---\n";
		}
	
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
		
		
			$line = $lines[$line_number];	
			
			//$line = preg_replace('/^\s+/', '', $line);
			
			$line = preg_replace('/\s+$/', '', $line);
						
			// Trim and flag hyphenation
			if (preg_match('/[A-Za-z][-|­]\s*$/', $line)) {
				$line = preg_replace('/[-|­]\s*$/', '', $line);
				$hyphens[$line_number] = 1;
			} else {
				$hyphens[$line_number] = 0;
			}
			
			if ($debug)
			{
				echo str_pad($line_number, 3, ' ');
				echo " [$state] ";
				echo $line;
				echo "\n";
			}
			
			switch ($state) {
				case $STATE_START:
					// Look for references
					if (preg_match('/^\s*(REFERENCES|LITERATURE CITED|ZOOTAXA References)$/iu', $line)) {
						// Ignore table of contents
						if (preg_match('/\.?\s*[0-9]$/', $line)) {
						} else {
							$state = $STATE_IN_REFERENCES;
						}
					}
					break;
				
				case $STATE_IN_REFERENCES:
					if (preg_match('/^\s*((bin|de|le|van|von)\s+)?(\p{Lu}|\.\s*[0-9]{4})/u', $line, $m))
					{
						if (preg_match('/^((Note[s]? added in proof)|(Appendix)|(Buchbesprechungen)|(Figure)|(Index)|(Supplementary))/i', $line)) {
							$state = $STATE_OUT_REFERENCES;
						} else {
							$state = $STATE_START_CITATION;
							$citation = $line;
							if (is_end_of_citation($line, $mean_line_length, $debug)) {
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
					if (is_end_of_citation($line, $mean_line_length, $debug) 
					|| (preg_match('/^(\p{Lu})/u', $lines[$line_number+1]))
					)
					{
						$citation = preg_replace('/\s\s+/u', ' ', $citation);
					
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

//----------------------------------------------------------------------------------------
function split_column($page, $debug = false)
{
	$sum_line_length = 0;
	$num_lines = 0;
	
	$lines = explode("\n", $page);
	$num_lines += count($lines);
	
	foreach ($lines as $line)
	{
		$sum_line_length += mb_strlen($line);
	}
	
	$mean_line_length = $sum_line_length / $num_lines;

	// estimate the halfway point in a line	
	$half = $mean_line_length / 2;
	
	/*
	foreach ($lines as $line)
	{
		echo substr($line, 0, $half) . "|" . substr($line, $half+1) . "\n";
	}
	*/
	
	// get frequncy of position of gap between columns
	$column_count = array();
	
	foreach ($lines as $line)
	{
		//echo substr($line, 0, $half) . "|" . substr($line, $half+1) . "\n";
		if (preg_match('/[^\s]\s{5,}(?<two>[^\s])/u', $line, $m, PREG_OFFSET_CAPTURE))
		{
			if (!isset($column_count[$m['two'][1]]))
			{
				$column_count[$m['two'][1]] = 0;
			}
			$column_count[$m['two'][1]]++;
		}
	}
	
	// Sort counts, if we have indented references then #2 is the start of right column
	arsort($column_count);
	
	//print_r($column_count);
	
	// raheem
	$column_pos = array_keys($column_count)[0];
	$column_pos = array_keys($column_count)[1];
	
	// amnh
	ksort($column_count);
	$column_pos = array_keys($column_count)[0];

	if ($debug)
	{
		foreach ($lines as $line)
		{
			echo mb_substr($line, 0, $column_pos) . "|" . mb_substr($line, $column_pos) . "\n";
		}
	}
			
    $column_text = array();
    $column_text[0] = array();
   	$column_text[1] = array();
   	
   	$n = count($lines);
   	
   	for ($i = 0; $i < $n; $i++)
   	{
  		$left_text 	= mb_substr($lines[$i], 0, $column_pos);
   		$right_text = mb_substr($lines[$i], $column_pos);
   	
   		$skip = false;   		
   		if ($i == 0)
   		{
   			$skip = true;
   		}
   		
   		if (!$skip)
   		{
			if (trim($left_text) != '')
			{
				$column_text[0][] = $left_text;
			}
 
 			if (trim($right_text) != '')
			{
				$column_text[1][] = $right_text;
			}
   		}
  
   	
   	}
     
    //print_r($column_text);
    
    return $column_text;
  
}



// tests
if (0)
{
	$pages = array(
	'Peckhamia 114.1  Maratus from Cape Le Grand  38

	indistinguishable, common elements in the display repertoire, and detailed characters of both legs III and
	the opisthosomal plate of males. It may be useful to assign more species to this group in the future, but
	for the present we simply define this as a clade containing both M. pardus and M. volans, and not
	containing any of the other clades that have been previously designated in the genus Maratus.

														   Acknowledgments

	We thank David Knowles for his assistance in locating this new species, and Martyn Robinson for
	information related to the brood size of M. volans. We also thank G. B. Edwards and David B. Richman for
	their reviews of our manuscript, the Department of Parks and Wildlife of Western Australia for
	permission to collect specimens in Cape Le Grand National Park (License number SF008869), and the
	National Parks and Wildlife Service of New South Wales for permission to collect specimens in Ku-ring-
	gai Chase National Park (License number SL100390). Unless otherwise indicated, all photographs
	presented here are copyright © J. C. Otto.

																References

	Butler, L. S. G. 1933. The common and conspicuous spiders of Melbourne. Victorian Naturalist 49: 271-292.
	Dunn, R. A. 1947. A new salticid spider from Victoria. Memoirs of the National Museum of Victoria 15: 82-85.
	Girard, M. B., M. M. Kasumovic and D. O. Elias. 2011. Multi-Modal Courtship in the Peacock Spider, Maratus volans (O.P.-

		  Cambridge, 1874). PLoS ONE 6 (9): e25390: 1―10 (doi:10.1371/journal.pone.0025390). Online with supplemental
		  material including videos at: http://www.plosone.org/article/info%3Adoi%2F10.1371%2Fjournal.pone.0025390
	Hill, D. E. 2009. Euophryine jumping spiders that extend their third legs during courtship (Araneae: Salticidae: Euophryinae:
		  Maratus, Saitis). Peckhamia 74.1: 1-27.
	Hill, D. E. and J. C. Otto. 2011. Visual display by male Maratus pavonis (Dunn 1947) and Maratus splendens (Rainbow 1896)
		  (Araneae: Salticidae: Euophryinae). Peckhamia 89.1: 1-41.
	Karsch, F. 1878. Diagnoses Attoidarum aliquot novarum Novae Hollandiae collectionis Musei zoologici Berolinensis
		  [Descriptions of several new salticids from Australia in the collection of the Berlin Museum]. Mittheilungen des
		  Münchener Entomologischen Vereins 2 (1): 22-32.
	Otto, J. C. 2011. "Peacock spider". Video at YouTube®: https://www.youtube.com/watch?v=9GgAbyYDFeg
	Otto, J. C. 2014. Peacock spider 11. Video at YouTube®: https://www.youtube.com/watch?v=wLosEnZ-ms0
	Otto, J. C. and D. E. Hill. 2011. An illustrated review of the known peacock spiders of the genus Maratus from Australia, with
		  description of a new species (Araneae: Salticidae: Euophryinae). Peckhamia 96.1: 1-27.
	Otto, J. C. and D. E. Hill. 2012a. Two new Australian peacock spiders that display inflated and extended spinnerets (Araneae:
		  Salticidae: Euophryinae: Maratus Karsch 1878). Peckhamia 104.1: 1-28.
	Otto, J. C. and D. E. Hill. 2012b. Notes on Maratus Karsch 1878 and related jumping spiders from Australia, with five new
		  species (Araneae: Salticidae: Euophryinae), version 2. Peckhamia 103.2: 1-82.
	Otto, J. C. and D. E. Hill. 2013. Three new Australian peacock spiders (Araneae: Salticidae: Maratus). Peckhamia 108.1: 1-39.
	Otto, J. C. and D. E. Hill. 2014. Spiders of the mungaich group from Western Australia (Araneae: Salticidae: Euophryinae:
		  Maratus), with one new species from Cape Arid. Peckhamia 112.1: 1-35.
	Pickard-Cambridge, O. 1874. On some new genera and species of Araneida. The Annals and Magazine of Natural History.
		  Series 4, volume 14, Issue Number 81, Paper 24: 169―183, plate XVII.
	Rainbow, W. J. 1896. Descriptions of some new Araneidae of New South Wales. No. 7. Proceedings of the Linnean Society of
		  New South Wales 21: 628-633.
	Ridewood, W. G. 1913. Guide to the exhibition of specimens illustrating the modification of the structure of animals in
		  relation to flight. British Museum of Natural History, Special Guide 6: i-viii, 1-80.
	Simon, E. 1901. Histoire naturelle des Araignées, Paris 2: 381-668.
	Waldock, J. M. 2007. What\'s in a name? Or: why Maratus volans (Salticidae) cannot fly. Western Australian Museum. Online
		  at: http://www.australasian-arachnology.org/download/Maratus_cannot_fly.pdf
	Waldock, J. M. 2008. A new species of Maratus (Araneae: Salticidae) from southwestern Australia. Records of the Western
		  Australian Museum 24: 369-373.
	Żabka, M. 1987. Salticidae (Araneae) of Oriental, Australian and Pacific Regions, II. Genera Lycidas and Maratus. Annales
		  Zoologici 40(11): 451-482.
	Żabka, M. 1991. Studium taksonomiczno-zoogeograficzne nad Salticidae (Arachnida: Araneae) Australii. Wyższa Szkola
		  Rolniczo-Pedagogiczna W Siedlcach. Rozprawa Naukowa 32: 1-110.'
		  );
		  
	$pages = array(
	'
	REFERENCES
	 Vences, M., J. Kosuch, R. Boistel, C.F.B. Haddad,
    E. La Marca, and S. Lo¨tters. 2003b. Convergent
    evolution of aposematic coloration in Neotrop-
    ical poison frogs: a molecular phylogenetic per-
    spective. Organisms, Diversity & Evolution 3:
    215–226.
 Vences, M., J. Kosuch, F. Glaw, W. Bo¨hme, and
    M. Veith. 2003c. Molecular phylogeny of hy-
    peroliid treefrogs: biogeographic origin of Mal-
    agasy and Seychellean taxa and re-analysis of
    familial paraphyly. Journal of Zoological Sys-
    tematics and Evolutionary Research 41: 205–
    213.
 Vences, M., J. Kosuch, S. Lo¨tters, A. Widmer,
    K.H. Jungfer, J. Ko¨hler, and M. Veith. 2000b.
    Phylogeny and classiﬁcation of poison frogs
    (Amphibia: Dendrobatidae), based on mito-
    chondrial 16S and 12S ribosomal RNA gene
    sequences. Molecular Phylogenetics and Evo-
    lution 15: 34–40.
 Vences, M., D.R. Vieites, F. Glaw, H. Brinkmann,
    J. Kosuch, M. Veith, and A. Meyer. 2003d.
    Multiple overseas dispersal in amphibians. Pro-
    ceedings of the Royal Society of London. Se-
    ries B, Biological Sciences 270: 2435–2442.
 Vences, M., S. Wanke, G. Odierna, J. Kosuch, and
    M. Veith. 2000c. Molecular and karyological
    data on the south Asian ranid genera Indirana,
    Nyctibatrachus and Nannophrys (Anura: Rani-
    dae). Hamadryad 25: 75–82.
 Wagler, J.G. 1828. Vorla¨uﬁge Uebersicht des Ger-
    uftes, sowie Untungigung feines Systema am-
    phibiorum. Isis von Oken 21: 859–861.
'
);	 

$pages=array('
258  BULLETIN AMERICAN MUSEUM OF NATURAL HISTORY      NO. 297

   atus (Boulenger) (amphibien, anoure). Annales         found in the plains of India. Records of the In-
   de la Faculte des Sciences du Cameroun. Ya-           dian Museum 15: 24–40.
   ounde´ 4: 53–71.                                   Anonymous. 1956. Opinion 417. Rejection for
Amiet, J.-L. 1971 ‘‘1970’’. Les batraciens oro-          nomenclatorial purposes of volume 3 (Zoolo-
   philes du Cameroun. Annales de la Faculte des         gie) of the work by Lorenz Oken entitled
   Sciences du Cameroun. Yaounde´ 5: 83–102.             ‘‘Okens Lehrbuch der Naturgeschichte’’ pub-
Amiet, J.-L. 1973 ‘‘1972’’. Compte rendu d’une           lished in 1815–1816. Opinions and Declara-
   mission batachologique dans le Nord-Came-             tions Rendered by the International Commis-
   roun. Annales de la Faculte des Sciences du           sion on Zoological Nomenclature 14: 1–42.
   Cameroun. Yaounde´ 12: 63–78.                      Anonymous. 1977. Opinion 1071. Emendation
Amiet, J.-L. 1973. Caracteres diagnostiques de           under the plenary powers of Liopelmatina to
   Petropedetes perreti, nov. sp. et notes sur les       Leiopelmatidae (Amphibia, Salientia). Bulletin
   autres espe`ces camerounaises du genre (amphi-        of Zoological Nomenclature 33: 167–169.
   biens anoures). Bulletin de l’Institut Fondamen-   Anonymous. 1990. Opinion 1604. Ichthyophiidae
   tal d’Afrique Noire, Se´rie A, Sciences Naturel-      Taylor, 1968 (Amphibia, Gymnophiona): con-
   les 35: 462–474.                                      served. Bulletin of Zoological Nomenclature
Amiet, J.-L. 1981. Ecologie, ethologie et devel-         47: 166–167.
   oppement de Phrynodon sandersoni Parker,           Anonymous. 1996. Opinion 1830. Caeciliidae
   1939 (Amphibia, Anura, Ranidae). Amphibia-            Kolbe, 1880 (Insecta, Psocoptera): spelling
   Reptilia 2: 1–13.                                     emended to Caeciliusidae, so removing the
Amiet, J.-L. 1983. Une espe`ce meconnue de Pe-           homonymy with Caeciliidae Raﬁnesque, 1814
   tropedetes du Cameroun: Petropedetes parkeri          (Amphibia, Gymnophiona). Bulletin of Zoolog-
   n. sp. (Amphibia, Anura, Ranidae, Phrynoba-           ical Nomenclature 53: 68–69.
   trachinae). Revue Suisse de Zoologie 90: 457–      Aplin, K.P., and M. Archer. 1987. Recent advanc-
   468.                                                  es in marsupial systematics with a new syn-
Amiet, J.-L. 1989. Quelques aspects de la biologie       cretic classiﬁcation. In M. Archer (editor), vol.
   des amphibiens anoures du Cameroun. Anne´e            1. Possums and opossums: studies in evolution:
   Biologique. Paris 28: 73–116.                         xv–lxxii. Chipping Norton, Australia: Surrey
Amiet, J.-L., and J.-L. Perret. 1969. Contributions      Beatty.
   a` la faune de la re´gion de Yaounde´ (Cameroun    Archey, G. 1922. The habitat and life history of
   II. Amphibiens anoures. Annales de la Faculte         Liopelma hochstetteri. Records of the Canter-
   des Sciences du Cameroun. Yaounde´ 1969:              bury Museum 2: 59–71.
   117–137.                                           Archibald, J.D. 1994. Metataxon concepts and as-
Anders, C.C. 2002. Class Amphibia (amphibians).          sessing possible ancestry using phylogenetic
   In H.H. Schleich and W. Ka¨stle (editors), Am-        systematics. Systematic Biology 43: 27–40.
   phibians and reptiles of Nepal: biology, system-   Ardila-Robayo, M.C. 1979. Status sistematico del
   atics, ﬁeld guide: 133–340. Ruggell: A.R.G.           genero Geobatrachus Ruthven, 1915 (Amphib-
   Gantner.                                              ia: Anura). Caldasia 12: 383–495.
Anderson, J. 1871. A list of the reptilian accession  Austin, J.D., S.C. Lougheed, K. Tanner, A.A.
   to the Indian Museum, Calcutta from 1865 to           Chek, J.P. Bogart, and P.T. Boag. 2002. A mo-
   1870, with a description of some new species.         lecular perspective on the evolutionary afﬁni-
   Journal of the Asiatic Society of Bengal 40:          ties of an enigmatic Neotropical frog, Allophry-
   12–39.                                                ne ruthveni. Zoological Journal of the Linnean
Anderson, J.S. 2001. The phylogenetic trunk:             Society. London 134: 335–346.
   maximal inclusion of taxa with missing data in     Ba´ez, A.M., and N.G. Basso. 1996. The earliest
   an analysis of the Lepospondyli (Vertebrata,          known frogs of the Jurassic of South America:
   Tetrapoda). Systematic Biology 50: 170–193.           review and cladistic appraisal of their relation-
Andreone, F., M. Vences, D.R. Vieites, F. Glaw,          ships. Mu¨nchner Geowissenschaftliche Abhan-
   and A. Meyer. 2004 ‘‘2005’’. Recurrent ecolog-        dlungen. Reihe A. Geologie und Pala¨ontologie
   ical adaptations revealed through a molecular         30: 131–158.
   analysis of the secretive cophyline frogs of       Ba´ez, A.M., and L.A. Pugener. 2003. Ontogeny
   Madagascar. Molecular Phylogenetics and Evo-          of a new Paleogene pipid frog from southern
   lution 34: 315–322.                                   South America and xenopodinomorph evolu-
Annandale, N. 1918. Some undescribed tadpoles            tion. Zoological Journal of the Linnean Society.
   from the hills of southern India. Records of the      London 139: 439–476.
   Indian Museum 15: 17–23.                           Ba´ez, A.M., and L. Trueb. 1997. Redescription of
Annandale, N., and C.R.N. Rao. 1918. The tad-            the Paleogene Shelania pascuali from Patagon-
   poles of the families Ranidae and Bufonidae           ia and its bearing on the relationships of fossil
'
);

$pages = array(
'                   RAHEEM ET AL.          – LAND SNAILS OF THE WESTERN GHATS                                       253

Reeve,  L.A. 1859-1862.    Conchologia    Iconica:   Or,     Sherborn, C.D. 1922-1932. Index Animalium 1801-
   Illustrations of the Shells of Molluscous Animals.            1850. British Museum (Natural History), London.
   Volume    13.   Containing     Monographs     of  the     Sherley,  G. 2000.   Invasive    species in the  Pacific: a
   Genera  Terebratula,   Rynchonella,    Crania, Orbi-          technical review and draft regional strategy. South
   cula, Lingula, Cybium, Cyclostoma, Cyclophorus,               Pacific Regional Environment Programme, Samoa.
   Leptopoma,    Vitrina,  Simpulopsis,    Phasianella,      Simone,   L.R.L.    2013.  Anatomy     of   predator  snail
   Trochus.  Lovell   Reeve  &   Co., Henrietta   Street,        Huttonella bicolor, an invasive species in Amazon
   Covent Garden, London.                                        rainforest,    Brazil  (Pulmonata,      Streptaxidae).
Régnier, C., Fontaine, B. and Bouchet, P. 2009. Not              Papéis Avulsos de Zoologia, 53: 47-58.
   knowing,    not recording,  not  listing:  numerous       Smith, B.J. and Stanisic, J. 1998. Pulmonata Introduc-
   unnoticed    mollusc    extinctions.   Conservation           tion. In: Beesly, P.L., Ross, G.J.B. and Wells, A.
   Biology, 23 (5): 1214-1221.                                   (Eds), Mollusca, The Southern Synthesis, Fauna
Robin, V.V., Sinha, A. and Ramakrishnan, U. 2010.                of  Australia,  Volume    5, Part  B, pp.   1037-1061.
   Ancient   Geographical    Gaps  and    Paleo-Climate          CSIRO Publishing, Melbourne.
   Shape the Phylogeography of an Endemic Bird in            Sowerby, G.B. 1842-1850. Thesaurus Conchyliorum,
   the Sky Islands of Southern India. PLoS ONE, 5                or Monographs of Genera of Shells. Volume 1.
   (10): e13321.                                                 Sowerby,    70,  Great Russell  Street,    Bloomsbury,
Robinson D.G., Hovestadt, A., Fields, A. and Breure,             London.
   A.S.H.  2009.   The  land   Mollusca   of  Dominica       Sowerby, G.B. 1843a. Descriptions of New Species of
   (Lesser Antilles), with notes on some enigmatic or            Cyclostoma.      Proceedings    of    the   Zoological
   rare species. Zoologische Mededelingen, 83: 615-              Society of London, proceedings for 1843: 59-66.
   650.                                                      Sowerby, G.B. 1843b. On new species of Cyclostoma
Rowson, B., Tattersfield, P. and Symondson, W.O.C.               in the collection of H. Cuming, Esq. Proceedings
   2011.   Phylogeny   and   biogeography    of  tropical        of the Zoological Society of London, proceedings
   carnivorous   land-snails  (Pulmonata:    Streptaxoi-         for 1843: 29-31.
   dea) with particular reference to East Africa and         Srinivasulu, C. and Das, I. 2008. The herpetofauna of
   the Indian Ocean. Zoological Scripta, 40: 85-98.              Nallamala Hills, Eastern Ghats, India: an annota-
Ruhoff, F.A. 1980. Index to the species of Mollusca              ted   checklist, with   remarks    on   nomenclature,
   introduced   from   1850    to  1870.   Smithsonian           taxonomy,   habitat   use,   adaptive types   and bio-
   Contributions to Zoology, 294: 1-640.                         geography. Asiatic Herpetological Research, 11:
Santos S. B. dos, Viana T.A. and Fonseca F.C. 2008.              110-131.
   First  record   of the  micro-predator     Huttonella     Srivastava, P.D. 1992. Problem of Land Snail Pests in
   bicolor (Hutton, 1834) (Gastropoda, Streptaxidae)             Agriculture: A Study of the Giant African Snail.
   on Rio de Janeiro city, Brazil. Biociências, Porto            Concept Publishing Company, New Delhi.
   Alegre, 16: 145-148.                                      Stoliczka, F. 1871. Notes on terrestrial Mollusca from
Schwartzberg, J.E. 1992. A Historical Atlas of South             the   neighbourhood    of    Moulmein,     (Tenasserim
   Asia. Second impression, with additional material.            Provinces),    with   descriptions   of new    species.
   Oxford  University   Press, New    York   &  Oxford.          Journal of the Asiatic Society of Bengal, 40 (2):
   Available for reference online through the Digital            217-259.
   South Asia Library: http://dsal.uchicago.edu [acc-        Stoliczka, F. 1873. Descriptions of two new species of
   essed 21 February 2014].                                      Indian Landshells. Journal of the Asiatic Society
Seddon,   M.B.  1992.  The   distribution of  Pupoides           of Bengal, 42 (2): 169-171.
   coenopictus (Hutton, 1834) in N.W. Africa (Gas-           Subba Rao, N.V. and Mitra, S.C. 1979. On land and
   tropoda, Pupillidae). Journal of Conchology, 34:              freshwater  molluscs   of  Pune   district Maharashtra.
   149-158.                                                      Records of the Zoological Survey of India, 75: 1-37.
Sen, S., Ravikanth, G. and Aravind, N.A. 2012. Land          Subba Rao, N.V. and Mitra, S.C. 1991. Land molluscs
   snails  (Mollusca:  Gastropoda)    of  India:  status,        of  Andaman      and   Nicobar    Islands.  Zoological
   threats and   conservation  strategies.   Journal of          Survey of India, Calcutta.
   Threatened Taxa, 4: 3029-3037.                            Subba Rao, N.V., Mitra, S.C., Dey, A. and Maitra, S.
Shea,  M.  2007.   Exotic snails  and   slugs found   in         1995. Molluscs of Meghalaya. Fauna of Megha-
   Australia. Australian Shell News, 131: 3-11.                  laya, State  Fauna    Series  (Zoological    Survey   of
Sherborn,  C.D.  1902.  Index  Animalium   1758-1800.            India), 4 (8): 1-88.
   Cambridge University Press, Cambridge.                    Subramanyam,     K. and Nayar, M.P.      1974. Vegetation
');

	split_column($pages[0]);
	
	exit();

	$citations = extract_citations($pages);

	print_r($citations);
}


?>
