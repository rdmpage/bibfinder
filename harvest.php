<?php

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/elastic.php');
require_once(dirname(__FILE__) . '/thumbnails/thumbnails.php');

require_once(dirname(__FILE__) . '/sha1.php');
require_once(dirname(__FILE__) . '/utils.php');


//----------------------------------------------------------------------------------------
function csl_to_elastic ($csl, $guid)
{
	$doc = new stdclass;
	
	// things we will display
	$doc->search_display = new stdclass;
	$doc->search_display->creator = array();
	
	// things we will search on
	$doc->search_data = new stdclass;
	$doc->search_data->container = array();
	$doc->search_data->author = array();
	$doc->search_data->fulltext_terms = array();
	$doc->search_data->fulltext_terms_boosted = array();
		
	$doc->id = md5(strtolower($guid));
	$doc->search_data->cluster_id = $doc->id;	
	
	foreach ($csl as $k => $v)
	{	
		switch ($k)
		{
			case 'type':
				switch ($v)
				{
					case 'article-journal':
					case 'journal-article':
						$doc->type = 'article';
						break;
						
					default:
						$doc->type = $v;
						break;
				}
				break;
		
			case 'title':			
				$done = false;
				
				if (isset($csl->multi))
				{
					if (isset($csl->multi->_key->title))
					{		
						$titles = array();			
						foreach ($csl->multi->_key->title as $language => $v)
						{
							$v = preg_replace('/\s+$/u', '', $v);
							$v = nice_strip_tags($v);
							
							// add
							$titles[] = $v;
							
							$doc->search_data->fulltext_terms[] = $v;
							$doc->search_data->fulltext_terms_boosted[] = $v;
						}				
						
						// concatenate for display name
						$doc->search_display->name = join(" / ", $titles);
							
						$done = true;
					}					
				}				
			
				if (!$done)
				{		
					$title = $v;
					if (is_array($v))
					{
						if (count($v) == 0)
						{
							$title = '';
						}
						else
						{
							$title = $v[0];
						}
					}									
					if ($title != '')
					{
						$title = nice_strip_tags($title);						
						$title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');						
						$title = str_replace("\n", "", $title);
						
						// add
						$doc->search_display->name = $title;
						$doc->search_data->fulltext_terms[] = $title;
						$doc->search_data->fulltext_terms_boosted[] = $title;						
					}								
				}
				break;
				
			case 'abstract':
				$done = false;
				
				if (isset($csl->multi))
				{
					if (isset($csl->multi->_key->abstract))
					{		
						$abstract = array();			
						foreach ($csl->multi->_key->abstract as $language => $v)
						{
							$v = preg_replace('/\s+$/u', '', $v);
							$v = nice_strip_tags($v);
							
							// add
							$abstract[] = $v;
							
							$doc->search_data->fulltext_terms[] = $v;
						}				
						$done = true;
					}					
				}
				
				if (!$done)
				{
					$abstract = $v;
					$abstract = nice_strip_tags($abstract);						
					$abstract = html_entity_decode($abstract, ENT_QUOTES | ENT_HTML5, 'UTF-8');
					$doc->search_data->fulltext_terms[] = $abstract;									
				}
				break;			
				
			case 'container-title':
				$done = false;
				
				if (isset($csl->multi))
				{
					if (isset($csl->multi->_key->{'container-title'}))
					{		
						foreach ($csl->multi->_key->{'container-title'} as $language => $v)
						{
							// facets
							$doc->search_data->container[] = $v;
							
							$doc->search_data->fulltext_terms[] = $v;
							$doc->search_data->fulltext_terms_boosted[] = $v;
						}
						
						$done = true;
					}
				}
				
				if (!$done)			
				{
					$container = $v;
					if (is_array($v))
					{
						$container = $v[0];
					}
					// facets
					$doc->search_data->container[] = $container;
					
					$doc->search_data->fulltext_terms[] = $container;
					$doc->search_data->fulltext_terms_boosted[] = $container;
				}
				break;	
				
			case 'author':
				$done = false;
				foreach ($csl->author as $author)
				{
					if (isset($author->multi))
					{
						if (isset($author->multi->_key->literal))
						{
							$names = array();
						
							foreach ($author->multi->_key->literal as $language => $v)
							{
								// facets
								$doc->search_data->author[] = $v;
							
								$doc->search_data->fulltext_terms[] = $v;
								$doc->search_data->fulltext_terms_boosted[] = $v;
								
								$names[] = $v;
							}
							
							$doc->search_display->creator[] = join(" / ", $names);
							
							$done = true;											
						}
					}
					
					if (!$done)
					{
						if (isset($author->literal))
						{
							// facets
							$doc->search_data->author[] = $author->literal;
							
							$doc->search_data->fulltext_terms[] = $author->literal;
							
							$doc->search_display->creator[] = $author->literal;
						}
						else
						{
							$parts = array();
							
							if (isset($author->given))
							{
								$parts[] =$author->given;
							}

							if (isset($author->family))
							{
								$parts[] =$author->family;
							}
							
							$text = join(" ", $parts);
							
							// facets
							$doc->search_data->author[] = $text;
							
							$doc->search_data->fulltext_terms[] = $text;
							
							$doc->search_display->creator[] = $text;
							
						
						}
						
					}
				}
				break;		
				
			case 'ISSN':
				if (is_array($v))
				{
					foreach ($v as $issn)
					{
						$doc->search_data->fulltext_terms[] = $issn;
					}
				}
				else
				{
					$doc->search_data->fulltext_terms[] = $v;
				}			
				break;	
				
			case 'volume':
			case 'issue':
			case 'page':
				$doc->search_data->fulltext_terms[] = $v;
				break;
				
			case 'issued':			
				$year = $v->{'date-parts'}[0][0];
				
				// facets
				$doc->search_data->year[] = $year;
				
				$doc->search_data->fulltext_terms[] = $year;
				$doc->search_data->fulltext_terms_boosted[] = $year;
				break;				
				
			case 'DOI':
				$doc->search_display->doi = strtolower($v);
			
				$doc->search_data->fulltext_terms[] = $v;
				
				break;
				
			case 'URL':
				$doc->search_display->url = $v;
				break;	
				
			case 'WIKIDATA':
				$doc->search_display->wikidata = $v;
				break;	
										
				
			default:
				break;
		}
	}
	
	// Cluster based on identifiers (need rules of precedence)
	// also need to have a canonical version of GUID that is used by all
	// uses of that GUID, both as id for record and cluster_id
	$cluster_id = '';
	
	if ($cluster_id == '')
	{
		if (isset($csl->DOI))
		{
			$cluster_id = md5(strtolower($csl->DOI));
		}
	}
	
	if ($cluster_id == '')
	{
		if (isset($csl->JSTOR))
		{
			$url = 'https://www.jstor.org/stable/' . $csl->JSTOR;
			$cluster_id = md5($url);
		}
	}	
	
	// Update cluster_id to GUID
	if ($cluster_id != '')
	{
		$doc->search_data->cluster_id = $cluster_id;
	}
	
	
	// Generate search terms
	$doc->search_data->fulltext = join(" ", $doc->search_data->fulltext_terms);
	unset($doc->search_data->fulltext_terms);

	$doc->search_data->fulltext_boosted = join(" ", $doc->search_data->fulltext_terms_boosted);
	unset($doc->search_data->fulltext_terms_boosted);
	
	
	
	// Add CSL for display
	$doc->search_display->csl = $csl;
	
	// empty for debugging	
	//$doc->search_display->csl = new stdclass;
	
	return $doc;
}


//----------------------------------------------------------------------------------------
$source = 'unknown';


$guids = array(
'10.7525/j.issn.1673-5102.2018.06.003',
'10.7525/j.issn.1673-5102.2006.01.016',
'10.3853/j.0067-1975.56.2004.1430',
'10.3853/j.0067-1975.63.2011.1552',
'biostor-257485',
'biostor-259861',
'10.3969/j.issn.2095-0845.2005.05.003',
'http://www.insect.org.cn/EN/abstract/abstract11729.shtml',
'http://www.jstor.org/stable/20141855',
'10.1649/0010-065x(2002)056[0453:ansomt]2.0.co;2',
'10.3969/j.issn.1005-9628.2004.02.011',
);



// PDF no URL
$guids=array('https://lkcnhm.nus.edu.sg/app/uploads/2017/06/61rbz097-102.pdf');

if (0)
{
	// datacite or medra DOIs
	$source = 'datacite';

	$guids = array(
		'10.13128/Acta_Herpetol-13269',
		'10.21248/contrib.entomol.15.1-2.167-174',
		'10.12905/0380.sydowia66(1)2014-0099',
		'10.5281/zenodo.35388',
		);
}

// Load from file
$source 	= 'local';

$filename 	= 'source.txt';
$filename 	= 'sources/taiwania.txt';
$filename 	= 'sources/0001-6799.txt';
$filename 	= 'sources/1681-5556.txt';
$filename 	= 'sources/0289-3568.txt';
$filename 	= 'sources/1005-9628.txt';
$filename 	= 'sources/0374-5481.txt';
$filename 	= 'sources/0524-4994.txt';
$filename 	= 'sources/0001-5202.txt';
$filename 	= 'sources/0161-8202.txt';
$filename 	= 'sources/0028-0119.txt';
$filename 	= 'sources/0387-9089.txt';
$filename 	= 'sources/1341-1160.txt';
$filename 	= 'sources/test.txt';

$filename 	= 'sources/0001-5202.txt';
$filename 	= 'sources/0022-8567.txt';
$filename 	= 'sources/0022-1511.txt';
$filename 	= 'sources/1578-665X.txt';
$filename 	= 'sources/1808-9798.txt';

$filename 	= 'sources/rsz-bioone.txt'; // BioOne

$filename 	= 'sources/bengal.txt'; 
$filename 	= 'sources/nl.txt'; 
$filename 	= 'sources/0375-099X.txt'; 
$filename 	= 'sources/tropical-zoology.txt'; 
$filename 	= 'sources/0028-7199.txt'; 
$filename 	= 'sources/0033-2615.txt';

//$filename 	= 'sources/1000-7482.txt'; // Entomotaxonomia test

$filename 	= 'sources/zootaxa.txt';

if (1)
{
	// bionames
	$filename 	= 'sources/bionames.txt';
	$source 	= 'bionames';
}

if (0)
{
	// datacite
	$filename 	= 'sources/datacite.txt';
	$source 	= 'datacite';
}

if (0)
{
	// biostor
	$filename 	= 'sources/biostor.txt';
	$source 	= 'biostor';
}

if (0)
{
	// test
	$filename 	= 'sources/test.txt';
	$source 	= 'local';
}


/*
// RSZ, note that because I use DOI as guid this overwrites local version (harvested from BioOne)
$source 	= 'datacite';
$filename 	= 'sources/rsz-zenodo.txt'; // Zenodo
*/

$counter = 1;

$force = true;

$file_handle = fopen($filename, "r");
while (!feof($file_handle)) 
{
	$guid = trim(fgets($file_handle));
	
	if (preg_match('/^#/', $guid))
	{
		// skip
	}
	else
	{
		$hash = md5($guid);
	
		$cache_dir = dirname(__FILE__) . '/cache';
		$hash_path = create_path_from_sha1($hash, $cache_dir);
	
		$cached_file = $hash_path . '/' . $hash . '.json';
	
		$obj = null;
	 
		if (!file_exists($cached_file) || $force)
		{
			echo "Fetching...\n";
		
			switch ($source)
			{
				case 'bionames':
					$sici = $guid;
					$sici = preg_replace('/https?:\/\/bionames.org\/references\//', '', $guid);
					$url = 'http://bionames.org/api/api_citeproc.php?id=' . $sici . '&style=csljson';
					
					//echo $url . "\n";
					$json = get($url);
				
					// save CSL in cache nicely formatted
					$obj = json_decode($json);				
					file_put_contents($cached_file, json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
			
					// get server a break
					if (($counter++ % 10) == 0)
					{
						$rand = rand(1000000, 3000000);
						echo "\n ...sleeping for " . round(($rand / 1000000),2) . ' seconds' . "\n\n";
						usleep($rand);
					}
					break;		
	
				case 'datacite':
				case 'medra':
					$url = 'https://doi.org/' . $guid;	
					$json = get($url, 'application/vnd.citationstyles.csl+json');

					// save CSL in cache nicely formatted
					$obj = json_decode($json);				
					file_put_contents($cached_file, json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

					// get server a break
					if (($counter++ % 10) == 0)
					{
						$rand = rand(1000000, 3000000);
						echo "\n ...sleeping for " . round(($rand / 1000000),2) . ' seconds' . "\n\n";
						usleep($rand);
					}

					break;
				
				case 'biostor':
					if (preg_match('/https?:\/\/biostor.org\/reference\/(?<id>\d+)/', $guid, $m))
					{
						$url = 'https://biostor.org/api.php?id=biostor-' . $m['id'] . '&format=citeproc';
						$json = get($url);
					
						// save CSL in cache nicely formatted
						$obj = json_decode($json);				
						file_put_contents($cached_file, json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
					}
					// get server a break
					if (($counter++ % 10) == 0)
					{
						$rand = rand(1000000, 3000000);
						echo "\n ...sleeping for " . round(($rand / 1000000),2) . ' seconds' . "\n\n";
						usleep($rand);
					}
				
					break;
			
				case 'local':
				case 'unknown':
				default:
					$url = '';
			
					// DOI
					if (preg_match('/^10./', $guid))
					{
						$url = 'http://localhost/~rpage/microcitation/www/citeproc-api.php?guid=' . urlencode($guid);
					}
	
					// URL
					if (preg_match('/^http/', $guid))
					{
						$url = 'http://localhost/~rpage/microcitation/www/citeproc-api.php?guid=' . urlencode($guid);
					}
	
					// Biostor
					if (preg_match('/^biostor/', $guid))
					{
						$url = 'https://biostor.org/api.php?id=' . $guid . '&format=citeproc';
					}

					if ($url != '')
					{
						$json = get($url);
						
						//echo $json;
					
						// save CSL in cache nicely formatted
						$obj = json_decode($json);				
						file_put_contents($cached_file, json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
					
					}
					break;
			}


		}
		else
		{
			echo "Cached...\n";
		}
	
		$json = file_get_contents($cached_file);
		$obj = json_decode($json);
	
	
		if ($obj)
		{
			// CSL
			print_r($obj);
		
			// Thumbnail?
		
			if (isset($obj->JSTOR))
			{
				unset($obj->thumbnail);
			
				$thumbnail = get_jstor_thumbnail($obj->JSTOR);
				if ($thumbnail != '')
				{
					$obj->thumbnail = $thumbnail;
				}
			}
		
			if (isset($obj->thumbnailUrl))
			{
				$obj->thumbnail = $obj->thumbnailUrl;
				unset($obj->thumbnailUrl);
			}
		
			echo "GUID $guid\n";
			$guid = clean_guid($guid);
			
			echo "Cleaned GUID $guid\n";
			
			$doc = csl_to_elastic($obj, $guid);

			//print_r($doc);
			
			//exit();

			$elastic_doc = new stdclass;
			$elastic_doc->doc = $doc;
			$elastic_doc->doc_as_upsert = true;
			$elastic->send('POST',  '_doc/' . urlencode($elastic_doc->doc->id). '/_update', json_encode($elastic_doc));					
		}
	
	}
}

?>


