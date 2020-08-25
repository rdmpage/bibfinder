<?php

// Elastic search

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/elastic.php');


//----------------------------------------------------------------------------------------
// Return a property value if it exists, otherwise an empty string
function get_property_value ($item, $key, $propertyName)
{
	$value = '';
	
	if (isset($item->{$key}))
	{
		$n = count($item->{$key});
		$i = 0;
		while ($value == '' && ($i < $n) )
		{
			if ($item->{$key}[$i]->name == $propertyName)
			{
				$value = $item->{$key}[$i]->value;
			}	
			
			$i++;	
		}
	}
	
	return $value;
}


//----------------------------------------------------------------------------------------
// Add a property value to an item. $key is the predicate that has the property,
// e.g. "identifier" 
function add_property_value (&$item, $key, $propertyName, $propertyValue)
{
	$found = false;
	
	$found = (get_property_value($item, $key, $propertyName) == $propertyValue);
	
	if (!$found)
	{
		// If we don't have this key then create it
		if (!isset($item->{$key}))
		{
			$item->{$key} = array();		
		}	
	
		$property = new stdclass;
		$property->{"@type"} = "PropertyValue";
		$property->name  = $propertyName;
		$property->value = $propertyValue;
		$item->{$key}[] = $property;
	}
}



//----------------------------------------------------------------------------------------

function do_search($q, $limit = 5)
{
	global $elastic;

	$json = '{
	"size":20,
		"query": {
		   "multi_match" : {
		  "query": "<QUERY>",
		  "fields":["search_data.fulltext", "search_data.fulltext_boosted^4"] 
		}
	},

	"highlight": {
		  "pre_tags": [
			 "<mark>"
		  ],
		  "post_tags": [
			 "<\/mark>"
		  ],
		  "fields": {
			 "search_data.fulltext": {},
			 "search_data.fulltext_boosted": {}
		  }
	   },

	"aggs": {


	"by_cluster_id": {
			"terms": {
				"field": "search_data.cluster_id.keyword",
				"order": {
					"max_score.value": "desc"
				}
			},
	
	
			"aggs": {
				"max_score": {
					"max": {
						"script": {
							"lang": "painless",
							"inline": "_score"
						}
					}
				}
			}
		}
	 }

	}';

	$json = str_replace('<QUERY>', $q, $json);
	//$json = str_replace('<SIZE>', $limit, $json);

	$response = $elastic->send('POST',  '_search?pretty', $json);					

	// echo $response;

	$obj = json_decode($response);

	// process and convert to RDF

	// schema.org DataFeed
	$output = new stdclass;

	$output->{'@context'} = (object)array
		(
			'@vocab'	 			=> 'http://schema.org/',
			'bibo' 					=> 'http://purl.org/ontology/bibo/',
			'doi'		 			=> 'bibo:doi',
			'handle' 				=> 'bibo:handle',
			'sici'					=> 'bibo:sici',
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


			// Get list of clusters				
			foreach ($obj->aggregations->by_cluster_id->buckets as $bucket)
			{
				$clusters[$bucket->key] = array();
			}
		
			// Cluster results
			foreach ($obj->hits->hits as $hit)
			{
				$cluster_id = $hit->_source->search_data->cluster_id;
			
				if (!isset($clusters[$cluster_id]))
				{
					$clusters[$cluster_id] = array();
				}
			
				$clusters[$cluster_id][$hit->_source->id] = $hit->_source->search_display;
			}
			
	
		}

	}

	// simplest approach, just use details from first element in cluster 
	foreach ($clusters as $cluster_id => $cluster)
	{
		if (count($cluster))
		{
	
			$item = new stdclass;
			$item->{'@id'} = $cluster_id;
			$item->{'@type'} = array("DataFeedItem", "ItemList");
			
			// Array is indexed by cluster_id, so we need to get first element (can't use "0")
			reset($cluster);
			$first_cluster_item_index = key($cluster);
	
			$item->name = $cluster[$first_cluster_item_index]->name;
			
			if (isset($cluster[$first_cluster_item_index]->creator))
			{
				$item->creator = $cluster[$first_cluster_item_index]->creator;
			}
		
			if (isset($cluster[$first_cluster_item_index]->csl->URL))
			{
				$item->url = $cluster[$first_cluster_item_index]->csl->URL;
			}
		
			// make a standard citation for us to check matches against
			$bibliographicCitation = array();
		
			$keys = array('author', 'issued', 'title', 'container-title', 'volume', 'issue', 'page');
			foreach ($keys as $k)
			{
				if (isset($cluster[$first_cluster_item_index]->csl->{$k}))
				{
					switch ($k)
					{
						case 'title':
							if (is_array($cluster[$first_cluster_item_index]->csl->{$k}))
							{
								$bibliographicCitation[] = ' ' . $cluster[$first_cluster_item_index]->csl->{$k}[0] . '.';
							}
							else
							{
								$bibliographicCitation[] = ' ' . $cluster[$first_cluster_item_index]->csl->{$k} . '.';						
							}
							break;

						case 'container-title':
							if (is_array($cluster[$first_cluster_item_index]->csl->{$k}))
							{
								$bibliographicCitation[] = ' ' . $cluster[$first_cluster_item_index]->csl->{$k}[0];
							}
							else
							{
								$bibliographicCitation[] = ' ' . $cluster[$first_cluster_item_index]->csl->{$k};						
							}
							break;

						case 'author':
							$authors = array();
							foreach ($cluster[$first_cluster_item_index]->csl->{$k} as $author)
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
							if (isset($cluster[$first_cluster_item_index]->csl->{$k}->{'date-parts'}))
							{
								$bibliographicCitation[] = ' (' . $cluster[$first_cluster_item_index]->csl->{$k}->{'date-parts'}[0][0] . ').';
							}					
							break;
						
						case 'page':
							$bibliographicCitation[] = ': ' . $cluster[$first_cluster_item_index]->csl->{$k};
							break;
						
						case 'issue':
							$bibliographicCitation[] = '(' . $cluster[$first_cluster_item_index]->csl->{$k} . ')';
							break;
				
						case 'volume':
							$bibliographicCitation[] = ', ' . $cluster[$first_cluster_item_index]->csl->{$k};
							break;

						default:
							$bibliographicCitation[] = $cluster[$first_cluster_item_index]->csl->{$k};
							break;
					}
			
				}
		
			}
				
			$item->bibliographicCitation = trim(join('', $bibliographicCitation));
			
			if (isset($cluster[$first_cluster_item_index]->csl->abstract))
			{
				$item->description = $cluster[$first_cluster_item_index]->csl->abstract;
			}
			else
			{
				$item->description = $item->bibliographicCitation;
			}
		
			// List of all members	
			$item->numberOfItems = count($cluster);
			$item->itemListElement = array();
	
			// build list of cluster members, and extract any useful information
			foreach ($cluster as $id => $cluster_data)
			{
			
				// Add this member to list of cluster members
				$item->itemListElement[] = $id;
			
				if (isset($cluster_data->csl->DOI))
				{
					$item->doi = $cluster_data->csl->DOI;
					
					if (!isset($item->url))
					{
						$item->url = 'https://doi.org/' . $item->doi;
					}
				}
				if (isset($cluster_data->csl->HANDLE))
				{
					$item->handle = $cluster_data->csl->HANDLE;
					
					if (!isset($item->url))
					{
						$item->url = 'https://hdl.handle.net.org/' . $item->handle;
					}
					
				}
				if (isset($cluster_data->csl->JSTOR))
				{
					add_property_value($item, "identifier", "jstor", $cluster_data->csl->JSTOR);
								
					//$item->jstor = $cluster_data->csl->JSTOR;
					
					if (!isset($item->url))
					{
						$item->url = 'https://www.jstor.org/' . $item->jstor;
					}
					
				}
				
				if (isset($cluster_data->csl->CNKI))
				{
					// $item->jstor = $cluster_data->csl->JSTOR;
					
					if (!isset($item->url))
					{
						$item->url = 'http://www.cnki.com.cn/Article/CJFDTOTAL-' . $cluster_data->csl->CNKI . '.htm';
					}
					
				}
								
				/*
				if (isset($cluster_data->csl->SICI))
				{
					$item->sici = $cluster_data->csl->SICI;
				}
				*/
				
				if (isset($cluster_data->csl->WIKIDATA))
				{
					add_property_value($item, "identifier", "wikidata", $cluster_data->csl->WIKIDATA);
					
					$item->sameAs[] = 'http://www.wikidata.org/entity/' . $cluster_data->csl->WIKIDATA;
				}				
			
				if (isset($cluster_data->csl->thumbnail))
				{
					//$item->thumbnailUrl = $cluster_data->csl->thumbnail;
				}
			
				if (isset($cluster_data->csl->link))
				{
					foreach ($cluster_data->csl->link as $link)
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
			
			
			
			}
	
			$output->{'@graph'}[0]->dataFeedElement[] = $item;
		}
	}

	//print_r($output);

	return $output;
}


// test
if (0)
{
	$q = 'Nicolas';
	
	$result = do_search($q);
	
	print_r($result);


}


?>
