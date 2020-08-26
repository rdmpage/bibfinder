<?php

// Get all versions in a cluster

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/elastic.php');

//----------------------------------------------------------------------------------------

function do_versions($cluster, $limit = 100)
{
	global $elastic;

	$url = '_search?pretty&q=' . urlencode("search_data.cluster_id:" . $cluster);

	$response = $elastic->send('GET', $url);					

	//echo $response;

	$obj = json_decode($response);

	// process and convert to RDF

	// schema.org DataFeed
	$output = new stdclass;

	$output->{'@context'} = (object)array
		(
			'@vocab'				=> 'http://schema.org/',
			'bibo' 					=> 'http://purl.org/ontology/bibo/',
			'doi' 					=> 'bibo:doi',
			'handle' 				=> 'bibo:handle',
			'dcterms' 				=> 'http://purl.org/dc/terms/',
			'bibliographicCitation' => 'dcterms:bibliographicCitation'
		);

	$output->{'@graph'} = array();
	$output->{'@graph'}[0] = new stdclass;
	$output->{'@graph'}[0]->{'@id'} = "http://example.rss";
	$output->{'@graph'}[0]->{'@type'} = "DataFeed";
	$output->{'@graph'}[0]->dataFeedElement = array();
	

	$clusters = array();

	if (isset($obj->hits))
	{
		$num_hits = 0;
		
		// Elastic 7.6.2
		if (isset($obj->hits->total->value))
		{
			$num_hits = $obj->hits->total->value;
		}
		else
		{
			$num_hits = $obj->hits->total;			
		}
		
		$time = '';
		if ($obj->took > 1000)
		{
			$time = '(' . floor($obj->took/ 1000) . ' seconds)';
		}
		else
		{
			$time = '(' . round($obj->took/ 1000, 2) . ' seconds)';
		}
		
		if ($num_hits == 0)
		{
			// Describe search
			$output->{'@graph'}[0]->description = "No results " . $time;
		}
		else
		{
			// Describe search
			if ($obj->hits->total == 1)
			{
				$output->{'@graph'}[0]->description = "One hit ";
			}
			else
			{
				$output->{'@graph'}[0]->description = $obj->hits->total . " hits ";
			}
			
			$output->{'@graph'}[0]->description .=  $time;

			// Just putput results (don't cluster)
			// Get list of clusters				
			foreach ($obj->hits->hits as $hit)
			{
				//print_r($hit);
				//
	
				$item = new stdclass;
				$item->{'@id'} = $hit->_source->id;
				$item->{'@type'} = array("DataFeedItem", "ItemList");
			
				$item->name = $hit->_source->search_display->name;
				
				//print_r($item);
				//exit();
			
				if (isset($hit->_source->search_display->creator))
				{
					$item->creator = $hit->_source->search_display->creator;
				}
		
				if (isset($hit->_source->search_display->csl->URL))
				{
					$item->url = $hit->_source->search_display->csl->URL;
				}
				
						
				// make a standard citation for us to check matches against
				$bibliographicCitation = array();
		
				$keys = array('author', 'issued', 'title', 'container-title', 'volume', 'issue', 'page');
				foreach ($keys as $k)
				{
					if (isset($hit->_source->search_display->csl->{$k}))
					{
						switch ($k)
						{
							case 'title':
								if (is_array($hit->_source->search_display->csl->{$k}))
								{
									$bibliographicCitation[] = ' ' . $hit->_source->search_display->csl->{$k}[0] . '.';
								}
								else
								{
									$bibliographicCitation[] = ' ' . $hit->_source->search_display->csl->{$k} . '.';						
								}
								break;

							case 'container-title':
								if (is_array($hit->_source->search_display->csl->{$k}))
								{
									$bibliographicCitation[] = ' ' . $hit->_source->search_display->csl->{$k}[0];
								}
								else
								{
									$bibliographicCitation[] = ' ' . $hit->_source->search_display->csl->{$k};						
								}
								break;

							case 'author':
								$authors = array();
								foreach ($hit->_source->search_display->csl->{$k} as $author)
								{
									$author_parts = [];
									if (isset($author->literal))
									{
										$author_parts[] = $author->literal;
									}
									else
									{
										if (isset($author->given))
										{
											$author_parts[] = $author->given;
										}
										if (isset($author->family))
										{
											$author_parts[] = $author->family;
										}
									}
									$authors[] = join(' ', $author_parts);						
								}
								$bibliographicCitation[] = join('; ', $authors);
								break;
						
							case 'issued':
								if (isset($hit->_source->search_display->csl->{$k}->{'date-parts'}))
								{
									$bibliographicCitation[] = ' (' . $hit->_source->search_display->csl->{$k}->{'date-parts'}[0][0] . ').';
								}					
								break;
						
							case 'page':
								$bibliographicCitation[] = ': ' . $hit->_source->search_display->csl->{$k};
								break;
						
							case 'issue':
								$bibliographicCitation[] = '(' . $hit->_source->search_display->csl->{$k} . ')';
								break;
				
							case 'volume':
								$bibliographicCitation[] = ', ' . $hit->_source->search_display->csl->{$k};
								break;

							default:
								$bibliographicCitation[] = $hit->_source->search_display->csl->{$k};
								break;
						}
			
					}
		
				}
				
				$item->bibliographicCitation = trim(join('', $bibliographicCitation));
			
				if (isset($hit->_source->search_display->csl->abstract))
				{
					$item->description = $hit->_source->search_display->csl->abstract;
				}
				else
				{
					$item->description = $item->bibliographicCitation;
				}
	
		
				if (isset($hit->_source->search_display->csl->DOI))
				{
					$item->doi = $hit->_source->search_display->csl->DOI;
					
					add_property_value($item, "identifier", "doi", $hit->_source->search_display->csl->DOI);
				
					if (!isset($item->url))
					{
						$item->url = 'https://doi.org/' . $item->doi;
					}
				}
				if (isset($hit->_source->search_display->csl->HANDLE))
				{
					$item->handle = $hit->_source->search_display->csl->HANDLE;
					
					add_property_value($item, "identifier", "handle", $hit->_source->search_display->csl->HANDLE);
				
					if (!isset($item->url))
					{
						$item->url = 'https://hdl.handle.net.org/' . $item->handle;
					}
				
				}
				if (isset($hit->_source->search_display->csl->JSTOR))
				{
					add_property_value($item, "identifier", "jstor", $hit->_source->search_display->csl->JSTOR);
									
					if (!isset($item->url))
					{
						$item->url = 'https://www.jstor.org/' . $hit->_source->search_display->csl->jstor;
					}
				
				}
				
				
				// BioStor
				if (isset($hit->_source->search_display->csl->BIOSTOR))
				{
					add_property_value($item, "identifier", "biostor", $hit->_source->search_display->csl->BIOSTOR);
								
					if (!isset($item->url))
					{
						$item->url = 'https://biostor.org/reference/' . $hit->_source->search_display->BIOSTOR;
					}
					
				}				
			
				if (isset($hit->_source->search_display->csl->CNKI))
				{
					add_property_value($item, "identifier", "cnki", $hit->_source->search_display->csl->CNKI);	
								
					if (!isset($item->url))
					{
						$item->url = 'http://www.cnki.com.cn/Article/CJFDTOTAL-' . $hit->_source->search_display->csl->CNKI . '.htm';
					}
				
				}

			
				if (isset($hit->_source->search_display->csl->WIKIDATA))
				{
					add_property_value($item, "identifier", "wikidata", $hit->_source->search_display->csl->WIKIDATA);
					
					$item->sameAs[] = 'http://www.wikidata.org/entity/' . $hit->_source->search_display->csl->WIKIDATA;
				}
				
								
		
				if (isset($hit->_source->search_display->csl->thumbnail))
				{
					$item->thumbnailUrl = $hit->_source->search_display->csl->thumbnail;
				}
		
				if (isset($hit->_source->search_display->csl->link))
				{
					foreach ($hit->_source->search_display->csl->link as $link)
					{
						if ($link->{'content-type'} == 'application/pdf')
						{
							if (!isset($item->encoding))
							{
								$item->encoding = array();
							}
					
							$encoding = new stdclass;
							$encoding->url = $link->URL;
							$encoding->encodingFormat = $link->{'content-type'};
							$item->encoding[] = $encoding;
						
							if (!isset($item->url))
							{
								$item->url = $encoding->url;
							}
					
				
						}
					}
				}
				
				$output->{'@graph'}[0]->dataFeedElement[] = $item;
			}
	
			
		}
	}

	//print_r($output);

	return $output;
}


// test

if (0)
{
	$cluster = '0be95a74f435a7de79fdd52f66f2422e';
	
	$result = do_versions($cluster );
	
	print_r($result);


}


?>
