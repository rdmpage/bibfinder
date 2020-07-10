<?php

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/elastic.php');
require_once(dirname(__FILE__) . '/thumbnails/thumbnails.php');

//----------------------------------------------------------------------------------------
function nice_strip_tags($str)
{
	$str = preg_replace('/</u', ' <', $str);
	$str = preg_replace('/>/u', '> ', $str);
	
	$str = strip_tags($str);
	
	$str = preg_replace('/\s\s+/u', ' ', $str);
	
	$str = preg_replace('/^\s+/u', '', $str);
	$str = preg_replace('/\s+$/u', '', $str);
	
	return $str;
	
}

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
		
	$doc->id = md5($guid);
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
				$doc->search_data->cluster_id = md5(strtolower($v));
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

$guids = array(
'10.7525/j.issn.1673-5102.2018.06.003',
'10.7525/j.issn.1673-5102.2006.01.016',
'10.3853/j.0067-1975.56.2004.1430',
'10.3853/j.0067-1975.63.2011.1552',
'biostor-257485',
'biostor-259861',
'10.3969/j.issn.2095-0845.2005.05.003',
'http://www.insect.org.cn/EN/abstract/abstract11729.shtml',
);

$guids = array(
'http://www.jstor.org/stable/20141855',
'10.1649/0010-065x(2002)056[0453:ansomt]2.0.co;2',
'10.3969/j.issn.1005-9628.2004.02.011',
);


$guids=array(
'10.3969/j.issn.1005-9628.2005.01.011',
'10.3969/j.issn.1005-9628.2005.01.001',
'10.3969/j.issn.1005-9628.2005.01.002',
'10.3969/j.issn.1005-9628.2005.01.003',
'10.3969/j.issn.1005-9628.2005.01.004',
'10.3969/j.issn.1005-9628.2005.01.005',
'10.3969/j.issn.1005-9628.2005.01.006',
'10.3969/j.issn.1005-9628.2005.01.007',
'10.3969/j.issn.1005-9628.2005.01.008',
'10.3969/j.issn.1005-9628.2005.01.009',
'10.3969/j.issn.1005-9628.2005.01.010',
'10.3969/j.issn.1005-9628.2005.01.012',
'10.3969/j.issn.1005-9628.2005.01.013',
'10.3969/j.issn.1005-9628.2005.02.001',
'10.3969/j.issn.1005-9628.2005.02.002',
'10.3969/j.issn.1005-9628.2005.02.003',
'10.3969/j.issn.1005-9628.2005.02.004',
'10.3969/j.issn.1005-9628.2005.02.005',
'10.3969/j.issn.1005-9628.2005.02.006',
'10.3969/j.issn.1005-9628.2005.02.007',
'10.3969/j.issn.1005-9628.2005.02.008',
'10.3969/j.issn.1005-9628.2005.02.009',
'10.3969/j.issn.1005-9628.2005.02.010',
'10.3969/j.issn.1005-9628.2005.02.011',
'10.3969/j.issn.1005-9628.2005.02.012',
'10.3969/j.issn.1005-9628.2005.02.013',
'10.3969/j.issn.1005-9628.2005.02.014',
'10.3969/j.issn.1005-9628.2014.01.001',
'10.3969/j.issn.1005-9628.2014.01.002',
'10.3969/j.issn.1005-9628.2014.01.003',
'10.3969/j.issn.1005-9628.2014.01.004',
'10.3969/j.issn.1005-9628.2014.01.005',
'10.3969/j.issn.1005-9628.2014.01.006',
'10.3969/j.issn.1005-9628.2014.01.007',
'10.3969/j.issn.1005-9628.2014.01.008',
'10.3969/j.issn.1005-9628.2014.01.009',
'10.3969/j.issn.1005-9628.2014.01.010',
'10.3969/j.issn.1005-9628.2014.01.011',
'10.3969/j.issn.1005-9628.2014.01.012',
'10.3969/j.issn.1005-9628.2014.01.013',
'10.3969/j.issn.1005-9628.2014.02.001',
'10.3969/j.issn.1005-9628.2014.02.002',
'10.3969/j.issn.1005-9628.2014.02.003',
'10.3969/j.issn.1005-9628.2014.02.004',
'10.3969/j.issn.1005-9628.2014.02.005',
'10.3969/j.issn.1005-9628.2014.02.006',
'10.3969/j.issn.1005-9628.2014.02.007',
'10.3969/j.issn.1005-9628.2014.02.008',
'10.3969/j.issn.1005-9628.2014.02.009',
'10.3969/j.issn.1005-9628.2014.02.010',
'10.3969/j.issn.1005-9628.2014.02.011',
'10.3969/j.issn.1005-9628.2014.02.012',
'10.3969/j.issn.1005-9628.2014.02.013',
'10.3969/j.issn.1005-9628.2001.01.001',
'10.3969/j.issn.1005-9628.2001.01.002',
'10.3969/j.issn.1005-9628.2001.01.003',
'10.3969/j.issn.1005-9628.2001.01.004',
'10.3969/j.issn.1005-9628.2001.01.005',
'10.3969/j.issn.1005-9628.2001.01.006',
'10.3969/j.issn.1005-9628.2001.01.007',
'10.3969/j.issn.1005-9628.2001.01.008',
'10.3969/j.issn.1005-9628.2001.01.009',
'10.3969/j.issn.1005-9628.2001.01.010',
'10.3969/j.issn.1005-9628.2001.01.011',
'10.3969/j.issn.1005-9628.2001.01.012',
'10.3969/j.issn.1005-9628.2001.01.013',
'10.3969/j.issn.1005-9628.2001.01.014',
'10.3969/j.issn.1005-9628.2001.01.015',
'10.3969/j.issn.1005-9628.2001.01.016',
'10.3969/j.issn.1005-9628.2001.01.017',
'10.3969/j.issn.1005-9628.2001.02.001',
'10.3969/j.issn.1005-9628.2001.02.002',
'10.3969/j.issn.1005-9628.2001.02.003',
'10.3969/j.issn.1005-9628.2001.02.004',
'10.3969/j.issn.1005-9628.2001.02.005',
'10.3969/j.issn.1005-9628.2001.02.006',
'10.3969/j.issn.1005-9628.2001.02.007',
'10.3969/j.issn.1005-9628.2001.02.008',
'10.3969/j.issn.1005-9628.2001.02.009',
'10.3969/j.issn.1005-9628.2001.02.010',
'10.3969/j.issn.1005-9628.2001.02.011',
'10.3969/j.issn.1005-9628.2001.02.012',
'10.3969/j.issn.1005-9628.2001.02.013',
'10.3969/j.issn.1005-9628.2001.02.014',
'10.3969/j.issn.1005-9628.2001.02.015',
'10.3969/j.issn.1005-9628.2001.02.016',
'10.3969/j.issn.1005-9628.2002.01.001',
'10.3969/j.issn.1005-9628.2002.01.002',
'10.3969/j.issn.1005-9628.2002.01.003',
'10.3969/j.issn.1005-9628.2002.01.004',
'10.3969/j.issn.1005-9628.2002.01.005',
'10.3969/j.issn.1005-9628.2002.01.006',
'10.3969/j.issn.1005-9628.2002.01.007',
'10.3969/j.issn.1005-9628.2002.01.008',
'10.3969/j.issn.1005-9628.2002.01.009',
'10.3969/j.issn.1005-9628.2002.01.010',
'10.3969/j.issn.1005-9628.2002.01.011',
'10.3969/j.issn.1005-9628.2002.01.012',
'10.3969/j.issn.1005-9628.2002.01.013',
'10.3969/j.issn.1005-9628.2002.01.014',
'10.3969/j.issn.1005-9628.2002.01.015',
'10.3969/j.issn.1005-9628.2002.01.016',
'10.3969/j.issn.1005-9628.2002.02.001',
'10.3969/j.issn.1005-9628.2002.02.002',
'10.3969/j.issn.1005-9628.2002.02.003',
'10.3969/j.issn.1005-9628.2002.02.004',
'10.3969/j.issn.1005-9628.2002.02.005',
'10.3969/j.issn.1005-9628.2002.02.006',
'10.3969/j.issn.1005-9628.2002.02.007',
'10.3969/j.issn.1005-9628.2002.02.008',
'10.3969/j.issn.1005-9628.2002.02.009',
'10.3969/j.issn.1005-9628.2003.01.001',
'10.3969/j.issn.1005-9628.2003.01.002',
'10.3969/j.issn.1005-9628.2003.01.003',
'10.3969/j.issn.1005-9628.2003.01.004',
'10.3969/j.issn.1005-9628.2003.01.005',
'10.3969/j.issn.1005-9628.2003.01.006',
'10.3969/j.issn.1005-9628.2003.01.007',
'10.3969/j.issn.1005-9628.2003.01.008',
'10.3969/j.issn.1005-9628.2003.01.009',
'10.3969/j.issn.1005-9628.2003.01.010',
'10.3969/j.issn.1005-9628.2003.01.011',
'http://e.wanfangdata.com.hk/zh-TW/d/Periodical_zxxb200302001.aspx',
'10.3969/j.issn.1005-9628.2003.02.002',
'10.3969/j.issn.1005-9628.2003.02.003',
'10.3969/j.issn.1005-9628.2003.02.004',
'10.3969/j.issn.1005-9628.2003.02.005',
'10.3969/j.issn.1005-9628.2003.02.006',
'10.3969/j.issn.1005-9628.2003.02.007',
'10.3969/j.issn.1005-9628.2003.02.008',
'10.3969/j.issn.1005-9628.2003.02.009',
'10.3969/j.issn.1005-9628.2003.02.010',
'10.3969/j.issn.1005-9628.2003.02.011',
'10.3969/j.issn.1005-9628.2003.02.012',
'10.3969/j.issn.1005-9628.2003.02.013',
'10.3969/j.issn.1005-9628.2003.02.014',
'10.3969/j.issn.1005-9628.2003.02.015',
'10.3969/j.issn.1005-9628.2004.01.001',
'10.3969/j.issn.1005-9628.2004.01.002',
'10.3969/j.issn.1005-9628.2004.01.003',
'http://e.wanfangdata.com.hk/zh-TW/d/Periodical_zxxb200401004.aspx',
'10.3969/j.issn.1005-9628.2004.01.005',
'10.3969/j.issn.1005-9628.2004.01.006',
'10.3969/j.issn.1005-9628.2004.01.007',
'10.3969/j.issn.1005-9628.2004.01.008',
'10.3969/j.issn.1005-9628.2004.01.009',
'10.3969/j.issn.1005-9628.2004.01.010',
'10.3969/j.issn.1005-9628.2004.01.011',
'10.3969/j.issn.1005-9628.2004.01.012',
'10.3969/j.issn.1005-9628.2004.01.013',
'10.3969/j.issn.1005-9628.2004.01.014',
'10.3969/j.issn.1005-9628.2004.01.015',
'10.3969/j.issn.1005-9628.2004.02.001',
'10.3969/j.issn.1005-9628.2004.02.002',
'10.3969/j.issn.1005-9628.2004.02.003',
'10.3969/j.issn.1005-9628.2004.02.004',
'10.3969/j.issn.1005-9628.2004.02.005',
'10.3969/j.issn.1005-9628.2004.02.006',
'10.3969/j.issn.1005-9628.2004.02.007',
'10.3969/j.issn.1005-9628.2004.02.008',
'10.3969/j.issn.1005-9628.2004.02.009',
'10.3969/j.issn.1005-9628.2004.02.010',
'10.3969/j.issn.1005-9628.2004.02.011',
'10.3969/j.issn.1005-9628.2004.02.012',
'10.3969/j.issn.1005-9628.2004.02.013',
'10.3969/j.issn.1005-9628.2006.01.001',
'10.3969/j.issn.1005-9628.2006.01.002',
'10.3969/j.issn.1005-9628.2006.01.003',
'10.3969/j.issn.1005-9628.2006.01.004',
'10.3969/j.issn.1005-9628.2006.01.005',
'10.3969/j.issn.1005-9628.2006.01.006',
'10.3969/j.issn.1005-9628.2006.01.007',
'10.3969/j.issn.1005-9628.2006.01.008',
'10.3969/j.issn.1005-9628.2006.01.009',
'10.3969/j.issn.1005-9628.2006.01.010',
'10.3969/j.issn.1005-9628.2006.01.011',
'10.3969/j.issn.1005-9628.2006.02.001',
'10.3969/j.issn.1005-9628.2006.02.002',
'10.3969/j.issn.1005-9628.2006.02.003',
'10.3969/j.issn.1005-9628.2006.02.004',
'10.3969/j.issn.1005-9628.2006.02.005',
'10.3969/j.issn.1005-9628.2006.02.006',
'10.3969/j.issn.1005-9628.2006.02.007',
'10.3969/j.issn.1005-9628.2006.02.008',
'10.3969/j.issn.1005-9628.2006.02.009',
'10.3969/j.issn.1005-9628.2006.02.010',
'10.3969/j.issn.1005-9628.2006.02.011',
'10.3969/j.issn.1005-9628.2006.02.012',
'10.3969/j.issn.1005-9628.2006.02.013',
'10.3969/j.issn.1005-9628.2007.01.001',
'10.3969/j.issn.1005-9628.2007.01.002',
'10.3969/j.issn.1005-9628.2007.01.003',
'10.3969/j.issn.1005-9628.2007.01.004',
'10.3969/j.issn.1005-9628.2007.01.005',
'10.3969/j.issn.1005-9628.2007.01.006',
'10.3969/j.issn.1005-9628.2007.01.007',
'10.3969/j.issn.1005-9628.2007.01.008',
'10.3969/j.issn.1005-9628.2007.01.009',
'http://e.wanfangdata.com.hk/zh-TW/d/Periodical_zxxb200701010.aspx',
'10.3969/j.issn.1005-9628.2007.02.001',
'10.3969/j.issn.1005-9628.2007.02.002',
'10.3969/j.issn.1005-9628.2007.02.003',
'10.3969/j.issn.1005-9628.2007.02.004',
'10.3969/j.issn.1005-9628.2007.02.005',
'10.3969/j.issn.1005-9628.2007.02.006',
'10.3969/j.issn.1005-9628.2007.02.007',
'10.3969/j.issn.1005-9628.2007.02.008',
'10.3969/j.issn.1005-9628.2007.02.009',
'10.3969/j.issn.1005-9628.2007.02.010',
'10.3969/j.issn.1005-9628.2007.02.011',
'10.3969/j.issn.1005-9628.2008.01.002',
'10.3969/j.issn.1005-9628.2008.01.003',
'10.3969/j.issn.1005-9628.2008.01.004',
'10.3969/j.issn.1005-9628.2008.01.005',
'10.3969/j.issn.1005-9628.2008.01.006',
'10.3969/j.issn.1005-9628.2008.01.007',
'10.3969/j.issn.1005-9628.2008.01.008',
'10.3969/j.issn.1005-9628.2008.01.009',
'10.3969/j.issn.1005-9628.2008.01.010',
'10.3969/j.issn.1005-9628.2008.01.011',
'10.3969/j.issn.1005-9628.2008.01.012',
'10.3969/j.issn.1005-9628.2008.01.013',
'10.3969/j.issn.1005-9628.2008.01.014',
'10.3969/j.issn.1005-9628.2008.01.015',
'10.3969/j.issn.1005-9628.2008.01.016',
'10.3969/j.issn.1005-9628.2008.02.001',
'10.3969/j.issn.1005-9628.2008.02.002',
'10.3969/j.issn.1005-9628.2008.02.003',
'10.3969/j.issn.1005-9628.2008.02.004',
'10.3969/j.issn.1005-9628.2008.02.005',
'10.3969/j.issn.1005-9628.2008.02.006',
'10.3969/j.issn.1005-9628.2008.02.007',
'10.3969/j.issn.1005-9628.2008.02.008',
'10.3969/j.issn.1005-9628.2008.02.009',
'10.3969/j.issn.1005-9628.2008.02.010',
'10.3969/j.issn.1005-9628.2008.02.011',
'10.3969/j.issn.1005-9628.2008.02.012',
'10.3969/j.issn.1005-9628.2008.02.013',
'10.3969/j.issn.1005-9628.2009.01.001',
'10.3969/j.issn.1005-9628.2009.01.002',
'10.3969/j.issn.1005-9628.2009.01.003',
'10.3969/j.issn.1005-9628.2009.01.004',
'10.3969/j.issn.1005-9628.2009.01.005',
'10.3969/j.issn.1005-9628.2009.01.006',
'10.3969/j.issn.1005-9628.2009.01.007',
'10.3969/j.issn.1005-9628.2009.01.008',
'10.3969/j.issn.1005-9628.2009.01.009',
'10.3969/j.issn.1005-9628.2009.01.010',
'10.3969/j.issn.1005-9628.2009.02.001',
'10.3969/j.issn.1005-9628.2009.02.002',
'10.3969/j.issn.1005-9628.2009.02.003',
'10.3969/j.issn.1005-9628.2009.02.004',
'10.3969/j.issn.1005-9628.2009.02.005',
'10.3969/j.issn.1005-9628.2009.02.006',
'10.3969/j.issn.1005-9628.2009.02.007',
'10.3969/j.issn.1005-9628.2009.02.008',
'10.3969/j.issn.1005-9628.2009.02.009',
'10.3969/j.issn.1005-9628.2009.02.010',
'10.3969/j.issn.1005-9628.2010.01.001',
'10.3969/j.issn.1005-9628.2010.01.002',
'10.3969/j.issn.1005-9628.2010.01.003',
'10.3969/j.issn.1005-9628.2010.01.004',
'10.3969/j.issn.1005-9628.2010.01.005',
'10.3969/j.issn.1005-9628.2010.01.006',
'10.3969/j.issn.1005-9628.2010.01.007',
'10.3969/j.issn.1005-9628.2010.01.008',
'10.3969/j.issn.1005-9628.2010.01.009',
'10.3969/j.issn.1005-9628.2010.01.010',
'10.3969/j.issn.1005-9628.2010.01.011',
'10.3969/j.issn.1005-9628.2010.01.012',
'10.3969/j.issn.1005-9628.2010.01.013',
'10.3969/j.issn.1005-9628.2010.02.001',
'10.3969/j.issn.1005-9628.2010.02.002',
'10.3969/j.issn.1005-9628.2010.02.003',
'10.3969/j.issn.1005-9628.2010.02.004',
'10.3969/j.issn.1005-9628.2010.02.005',
'10.3969/j.issn.1005-9628.2010.02.006',
'10.3969/j.issn.1005-9628.2010.02.007',
'10.3969/j.issn.1005-9628.2010.02.008',
'10.3969/j.issn.1005-9628.2010.02.009',
'10.3969/j.issn.1005-9628.2010.02.010',
'10.3969/j.issn.1005-9628.2010.02.0011',
'10.3969/j.issn.1005-9628.2010.02.012',
'10.3969/j.issn.1005-9628.2010.02.013',
'10.3969/j.issn.1005-9628.2011.01.001',
'10.3969/j.issn.1005-9628.2011.01.002',
'10.3969/j.issn.1005-9628.2011.01.003',
'10.3969/j.issn.1005-9628.2011.01.004',
'10.3969/j.issn.1005-9628.2011.01.005',
'10.3969/j.issn.1005-9628.2011.01.006',
'10.3969/j.issn.1005-9628.2011.01.007',
'10.3969/j.issn.1005-9628.2011.01.008',
'10.3969/j.issn.1005-9628.2011.01.009',
'10.3969/j.issn.1005-9628.2011.01.0010',
'10.3969/j.issn.1005-9628.2011.01.0011',
'10.3969/j.issn.1005-9628.2011.01.0012',
'10.3969/j.issn.1005-9628.2011.01.0013',
'10.3969/j.issn.1005-9628.2011.02.001',
'10.3969/j.issn.1005-9628.2011.02.002',
'10.3969/j.issn.1005-9628.2011.02.003',
'10.3969/j.issn.1005-9628.2011.02.004',
'10.3969/j.issn.1005-9628.2011.02.005',
'10.3969/j.issn.1005-9628.2011.02.006',
'10.3969/j.issn.1005-9628.2011.02.007',
'10.3969/j.issn.1005-9628.2011.02.008',
'10.3969/j.issn.1005-9628.2011.02.009',
'10.3969/j.issn.1005-9628.2011.02.010',
'10.3969/j.issn.1005-9628.2011.02.011',
'10.3969/j.issn.1005-9628.2011.02.012',
'10.3969/j.issn.1005-9628.2011.02.013',
'10.3969/j.issn.1005-9628.2012.01.001',
'10.3969/j.issn.1005-9628.2012.01.002',
'10.3969/j.issn.1005-9628.2012.01.003',
'10.3969/j.issn.1005-9628.2012.01.004',
'10.3969/j.issn.1005-9628.2012.01.005',
'10.3969/j.issn.1005-9628.2012.01.006',
'10.3969/j.issn.1005-9628.2012.01.007',
'10.3969/j.issn.1005-9628.2012.01.008',
'10.3969/j.issn.1005-9628.2012.01.009',
'10.3969/j.issn.1005-9628.2012.01.010',
'10.3969/j.issn.1005-9628.2012.01.011',
'10.3969/j.issn.1005-9628.2012.02.001',
'10.3969/j.issn.1005-9628.2012.02.002',
'10.3969/j.issn.1005-9628.2012.02.003',
'10.3969/j.issn.1005-9628.2012.02.004',
'10.3969/j.issn.1005-9628.2012.02.005',
'10.3969/j.issn.1005-9628.2012.02.006',
'10.3969/j.issn.1005-9628.2012.02.007',
'10.3969/j.issn.1005-9628.2012.02.008',
'10.3969/j.issn.1005-9628.2012.02.009',
'10.3969/j.issn.1005-9628.2012.02.010',
'10.3969/j.issn.1005-9628.2012.02.011',
'10.3969/j.issn.1005-9628.2012.02.012',
'10.3969/j.issn.1005-9628.2013.01.001',
'10.3969/j.issn.1005-9628.2013.01.002',
'10.3969/j.issn.1005-9628.2013.01.003',
'10.3969/j.issn.1005-9628.2013.01.004',
'10.3969/j.issn.1005-9628.2013.01.005',
'10.3969/j.issn.1005-9628.2013.01.006',
'10.3969/j.issn.1005-9628.2013.01.007',
'10.3969/j.issn.1005-9628.2013.01.008',
'10.3969/j.issn.1005-9628.2013.01.009',
'10.3969/j.issn.1005-9628.2013.01.010',
'10.3969/j.issn.1005-9628.2013.01.011',
'10.3969/j.issn.1005-9628.2013.01.012',
'10.3969/j.issn.1005-9628.2013.01.013',
'10.3969/j.issn.1005-9628.2013.02.001',
'10.3969/j.issn.1005-9628.2013.02.002',
'10.3969/j.issn.1005-9628.2013.02.003',
'10.3969/j.issn.1005-9628.2013.02.004',
'10.3969/j.issn.1005-9628.2013.02.005',
'10.3969/j.issn.1005-9628.2013.02.006',
'10.3969/j.issn.1005-9628.2013.02.007',
'10.3969/j.issn.1005-9628.2013.02.008',
'10.3969/j.issn.1005-9628.2013.02.009',
'10.3969/j.issn.1005-9628.2013.02.010',
'10.3969/j.issn.1005-9628.2013.02.011',
'10.3969/j.issn.1005-9628.2013.02.012',
'10.3969/j.issn.1005-9628.2015.01.001',
'10.3969/j.issn.1005-9628.2015.01.002',
'10.3969/j.issn.1005-9628.2015.01.003',
'10.3969/j.issn.1005-9628.2015.01.004',
'10.3969/j.issn.1005-9628.2015.01.005',
'10.3969/j.issn.1005-9628.2015.01.006',
'10.3969/j.issn.1005-9628.2015.01.007',
'10.3969/j.issn.1005-9628.2015.01.008',
'10.3969/j.issn.1005-9628.2015.01.009',
'10.3969/j.issn.1005-9628.2015.01.010',
'10.3969/j.issn.1005-9628.2015.01.011',
'10.3969/j.issn.1005-9628.2015.01.012',
'10.3969/j.issn.1005-9628.2015.01.013',
'10.3969/j.issn.1005-9628.2016.01.001',
'10.3969/j.issn.1005-9628.2016.01.002',
'10.3969/j.issn.1005-9628.2016.01.003',
'10.3969/j.issn.1005-9628.2016.01.004',
'10.3969/j.issn.1005-9628.2016.01.005',
'10.3969/j.issn.1005-9628.2016.01.006',
'10.3969/j.issn.1005-9628.2016.01.007',
'10.3969/j.issn.1005-9628.2016.01.008',
'10.3969/j.issn.1005-9628.2016.01.009',
'10.3969/j.issn.1005-9628.2016.01.010',
'10.3969/j.issn.1005-9628.2016.01.011',
'10.3969/j.issn.1005-9628.2016.01.012',
'10.3969/j.issn.1005-9628.2016.01.013',
'10.3969/j.issn.1005-9628.2016.01.014',
'10.3969/j.issn.1005-9628.2016.02.001',
'10.3969/j.issn.1005-9628.2016.02.002',
'10.3969/j.issn.1005-9628.2016.02.003',
'10.3969/j.issn.1005-9628.2016.02.004',
'10.3969/j.issn.1005-9628.2016.02.005',
'10.3969/j.issn.1005-9628.2016.02.006',
'10.3969/j.issn.1005-9628.2016.02.007',
'10.3969/j.issn.1005-9628.2016.02.008',
'10.3969/j.issn.1005-9628.2016.02.009',
'10.3969/j.issn.1005-9628.2016.02.010',
'10.3969/j.issn.1005-9628.2016.02.011',
'10.3969/j.issn.1005-9628.2016.02.012',
'10.3969/j.issn.1005-9628.2016.02.013',
'10.3969/j.issn.1005-9628.2016.02.014',
'10.3969/j.issn.1005-9628.2016.02.015',
'10.3969/j.issn.1005-9628.2015.02.002',
'10.3969/j.issn.1005-9628.2015.02.003',
'10.3969/j.issn.1005-9628.2015.02.004',
'10.3969/j.issn.1005-9628.2015.02.005',
'10.3969/j.issn.1005-9628.2015.02.006',
'10.3969/j.issn.1005-9628.2015.02.007',
'10.3969/j.issn.1005-9628.2015.02.008',
'10.3969/j.issn.1005-9628.2015.02.009',
'10.3969/j.issn.1005-9628.2015.02.010',
'10.3969/j.issn.1005-9628.2015.02.011',
'10.3969/j.issn.1005-9628.2015.02.012',
'10.3969/j.issn.1005-9628.2015.02.013',
'10.3969/j.issn.1005-9628.2015.02.014',
'10.3969/j.issn.1005-9628.2017.01.001',
'10.3969/j.issn.1005-9628.2017.01.002',
'10.3969/j.issn.1005-9628.2017.01.003',
'10.3969/j.issn.1005-9628.2017.01.004',
'10.3969/j.issn.1005-9628.2017.01.005',
'10.3969/j.issn.1005-9628.2017.01.006',
'10.3969/j.issn.1005-9628.2017.01.007',
'10.3969/j.issn.1005-9628.2017.01.008',
'10.3969/j.issn.1005-9628.2017.01.009',
'10.3969/j.issn.1005-9628.2017.01.010',
'10.3969/j.issn.1005-9628.2017.01.011',
'10.3969/j.issn.1005-9628.2017.01.012',
'10.3969/j.issn.1005-9628.2017.01.013',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201701014',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201701015',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201701016',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201701017',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201701018',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201701019',
'10.3969/j.issn.1005-9628.2017.02.001',
'10.3969/j.issn.1005-9628.2017.02.02',
'10.3969/j.issn.1005-9628.2017.02.03',
'10.3969/j.issn.1005-9628.2017.02.04',
'10.3969/j.issn.1005-9628.2017.02.05',
'10.3969/j.issn.1005-9628.2017.02.06',
'10.3969/j.issn.1005-9628.2017.02.07',
'10.3969/j.issn.1005-9628.2017.02.08',
'10.3969/j.issn.1005-9628.2017.02.09',
'10.3969/j.issn.1005-9628.2017.02.10',
'10.3969/j.issn.1005-9628.2017.02.11',
'10.3969/j.issn.1005-9628.2017.02.12',
'10.3969/j.issn.1005-9628.2017.02.13',
'10.3969/j.issn.1005-9628.2017.02.14',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201702015',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201702016',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201702017',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201702018',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201702019',
'10.3969/j.issn.1005-9628.2018.01.01',
'10.3969/j.issn.1005-9628.2018.01.02',
'10.3969/j.issn.1005-9628.2018.01.03',
'10.3969/j.issn.1005-9628.2018.01.04',
'10.3969/j.issn.1005-9628.2018.01.05',
'10.3969/j.issn.1005-9628.2018.01.06',
'10.3969/j.issn.1005-9628.2018.01.07',
'10.3969/j.issn.1005-9628.2018.01.08',
'10.3969/j.issn.1005-9628.2018.01.09',
'10.3969/j.issn.1005-9628.2018.01.10',
'10.3969/j.issn.1005-9628.2018.01.11',
'10.3969/j.issn.1005-9628.2018.01.12',
'10.3969/j.issn.1005-9628.2018.01.13',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201801014',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201801015',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201801016',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201801017',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201801018',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201801019',
'10.3969/j.issn.1005-9628.2018.02.001',
'10.3969/j.issn.1005-9628.2018.02.002',
'10.3969/j.issn.1005-9628.2018.02.003',
'10.3969/j.issn.1005-9628.2018.02.004',
'10.3969/j.issn.1005-9628.2018.02.005',
'10.3969/j.issn.1005-9628.2018.02.006',
'10.3969/j.issn.1005-9628.2018.02.007',
'10.3969/j.issn.1005-9628.2018.02.008',
'10.3969/j.issn.1005-9628.2018.02.009',
'10.3969/j.issn.1005-9628.2018.02.010',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201802011',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201802012',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201802013',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201802014',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201802015',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201802016',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201802017',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201802018',
'http://med.wanfangdata.com.cn/Paper/Detail/PeriodicalPaper_zxxb201802019',
);
$guids = array('10.3969/j.issn.2095-0845.2005.05.003'); // multilingual journal

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

//$guids=array('http://peckhamia.com/peckhamia/PECKHAMIA_141.1.pdf');

//$guids=array('https://lkcnhm.nus.edu.sg/app/uploads/2017/06/61rbz097-102.pdf');


//$guids =array('http://www.insect.org.cn/EN/abstract/abstract11729.shtml');

// datacite or medra DOIs

/*
$guids = array(
	'10.13128/Acta_Herpetol-13269',
	'10.21248/contrib.entomol.15.1-2.167-174',
	'10.12905/0380.sydowia66(1)2014-0099',
	'10.5281/zenodo.35388',
	);
*/

$source = 'unknown';
//$source = 'datacite';
//$source = 'medra';

foreach ($guids as $guid)
{
	$obj = null;
	
	switch ($source)
	{
		case 'datacite':
		case 'medra':
			$url = 'https://doi.org/' . $guid;	
			$json = get($url, 'application/vnd.citationstyles.csl+json');
			$obj = json_decode($json);
			break;
			
		case 'unknown':
		default:
			$url = '';
			
			// DOI
			if (preg_match('/^10./', $guid))
			{
				$url = 'http://localhost/~rpage/microcitation/www/citeproc-api.php?guid=' . urlencode($guid);
			}
	
			// URL
			if (preg_match('/^http./', $guid))
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
				$obj = json_decode($json);
			}
			break;
	}
	
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
		
		$doc = csl_to_elastic($obj, $guid);

		print_r($doc);

		$elastic_doc = new stdclass;
		$elastic_doc->doc = $doc;
		$elastic_doc->doc_as_upsert = true;
		$elastic->send('POST',  '_doc/' . urlencode($elastic_doc->doc->id). '/_update', json_encode($elastic_doc));					
	}
	

}

?>


