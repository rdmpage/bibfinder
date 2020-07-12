<?php

require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/reconciliation_api.php');
require_once (dirname(__FILE__) . '/lcs.php');
require_once (dirname(__FILE__) . '/search.php');
require_once (dirname(__FILE__) . '/api_utils.php');

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
class BibFinderService extends ReconciliationService
{
	//----------------------------------------------------------------------------------------------
	function __construct()
	{
		global $config;
		
		$this->name 			= 'BibFinder';
		
		$this->identifierSpace 	= $config['web_server'];
		$this->schemaSpace 		= 'http://rdf.freebase.com/ns/type.object.id';
		$this->Types();
		
		$view_url = $config['web_server'] . '/api.php?id={{id}}';

		$preview_url = '';	
		$width = 430;
		$height = 300;
		
		if ($view_url != '')
		{
			$this->View($view_url);
		}
		if ($preview_url != '')
		{
			$this->Preview($preview_url, $width, $height);
		}
	}
	
	//----------------------------------------------------------------------------------------------
	function Types()
	{
		$type = new stdclass;
		$type->id = 'https://schema.org/CreativeWork';
		$type->name = 'CreativeWork';
		$this->defaultTypes[] = $type;
	} 	
		
	// Elastic 
	//----------------------------------------------------------------------------------------------
	// Handle an individual query
	function OneQuery($query_key, $text, $limit = 1, $properties = null)
	{
		global $config;
		
		
		$search_result = do_search($text);
	
		//print_r($search_result);

		$hits = array();

		$threshold 	= 0.9;
		$max_d 		= 0.0;

		foreach ($search_result->{'@graph'}[0]->dataFeedElement as $item)
		{
			$result = compare($text, $item->bibliographicCitation, true);
	
			//print_r($result);	
	
			//echo $item->bibliographicCitation . "\n";
	
			// get hits
			if ($result->p > $threshold && $result->p >= $max_d)
			{
				$hit = new stdclass;
		
				$hit->id = $item->{'@id'};
				$hit->query = $text;
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
		
		// output in reconciliation format	
		
		$n = count($hits);
		$n = min($n, 3);
		
		for ($i = 0; $i < $n; $i++)
		{
			$recon_hit = new stdclass;
			$recon_hit->id 		= $hits[$i]->id;	
			$recon_hit->name 	= $hits[$i]->bibliographicCitation;	
			$recon_hit->score = $hits[$i]->d;
			$recon_hit->match = ($recon_hit->score > 0.8);
			$this->StoreHit($query_key, $recon_hit);							
		}		
		
	}	
	
}

$service = new BibFinderService();


if (0)
{
	file_put_contents('/tmp/q.txt', $_REQUEST['queries'], FILE_APPEND);
}


$service->Call($_REQUEST);

?>