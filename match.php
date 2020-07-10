<?php

require_once(dirname(__FILE__) . '/search.php');

require_once(dirname(__FILE__) . '/lcs.php');

//----------------------------------------------------------------------------------------
// https://stackoverflow.com/a/2759179
function Unaccent($string)
{
    return preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);~i', '$1', htmlentities($string, ENT_QUOTES, 'UTF-8'));
}

//----------------------------------------------------------------------------------------
function clean ($text)
{
	
	$text = preg_replace('/\./u', '', $text);
	$text = preg_replace('/-/u', ' ', $text);
	$text = preg_replace('/,/u', ' ', $text);
	$text = preg_replace('/\(/u', ' ', $text);
	$text = preg_replace('/\)/u', ' ', $text);
	$text = preg_replace('/[\?|:|\.]/u', ' ', $text);
	
	$text = preg_replace('/\s\s+/u', ' ', $text);
	
	//echo $text . "\n";

	$text = mb_convert_case($text, MB_CASE_LOWER);
	
	//echo $text . "\n";


	$text = Unaccent($text);

	//echo $text . "|\n";

	return $text;
}

//----------------------------------------------------------------------------------------
function compare($name1, $name2, $debug = false)
{
	
	$result = new stdclass;
	
	$result->str1 = $name1;
	$result->str2 = $name2;
	
	$result->str1 = clean($result->str1);
	$result->str2 = clean($result->str2);

	$lcs = new LongestCommonSequence($result->str1, $result->str2);
	
	$result->d = $lcs->score();
	
	$result->p = $result->d / min(strlen($result->str1), strlen($result->str2));
	
	$lcs->get_alignment();
	
		
	if ($debug)
	{
		$result->alignment = '';
		$result->alignment .= "\n";
		$result->alignment .= $lcs->left . "\n";
		$result->alignment .= $lcs->bars . "\n";
		$result->alignment .= $lcs->right . "\n";
		$result->alignment .= $result->d . " characters match\n";
		$result->alignment .= $result->p . " of shortest string matches\n";
	}	
	

	
	return $result;
}


//----------------------------------------------------------------------------------------



$queries = array(
'A new species of Mexican Tinotus from the refuse piles of Atta ants, including an annotated world catalog of Tinotus (Coleoptera: Staphylinidae: Aleocharinae: Aleocharini',
);



foreach ($queries as $q)
{
	echo $q . "\n";

	$search_result = do_search($q);
	
	// print_r($result);

	$hits = array();

	$threshold 	= 0.9;
	$max_d 		= 0.0;

	foreach ($search_result->{'@graph'}[0]->dataFeedElement as $item)
	{
		$result = compare($q, $item->bibliographicCitation, true);
	
		//print_r($result);	
	
		//echo $item->bibliographicCitation . "\n";
	
		if ($result->p > $threshold && $result->p >= $max_d)
		{
		
	
			$hit = new stdclass;
		
			$hit->id = $item->{'@id'};
			$hit->query = $q;
			$hit->bibliographicCitation = $item->bibliographicCitation;
			$hit->d = $result->p;
		
			if (isset($item->doi))
			{
				$hit->doi = $item->doi;
			}		
		
			if ($result->p > $max_d)
			{
				$max_d = $result->p;
				$hits = array();
		
			}
		
			$hits[] =  $hit;
		
		
		}

	}

	print_r($hits);
}

?>

