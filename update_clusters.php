<?php

// Clustering using disjoint sets to construct components

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/elastic.php');
require_once(dirname(__FILE__) . '/utils.php');


//----------------------------------------------------------------------------------------
// Disjoint-set data structure

$parents = array();

function makeset($x) {
	global $parents;
	
	$parents[$x] = $x;
}

function find($x) {
	global $parents;
	
	if ($x == $parents[$x]) {
		return $x;
	} else {
		return find($parents[$x]);
	}
}

function union($x, $y) {
	global $parents;
	
	$x_root = find($x);
	$y_root = find($y);
	$parents[$x_root] = $y_root;
	
}

//----------------------------------------------------------------------------------------
// Merge records
// Returns array of clusters that can then be merged
function merge_records ($records, $check = true)
{
	global $parents;
	
	$threshold = 0.95;
	
	$clusters = array();
	
	// If we have more than one reference with the same hash, compare and cluster
	$n = count($records);

	if ($n > 1)
	{
		// Initialise
		for ($i = 0; $i < $n; $i++)
		{
			makeset($i);
		}	
	
		// Compare
		for ($i = 0;$i < $n; $i++)
		{
			echo "$i: " . csl_to_citation_string($records[$i]->search_display->csl) . "\n";
			
			for ($j = 0; $j < $i; $j++)
			{
				// use string matching to check match
				if ($check)
				{		
				
					$result = compare(
						csl_to_citation_string($records[$i]->search_display->csl),
						csl_to_citation_string($records[$j]->search_display->csl)				
					);
					
					echo $result->p . "\n";
				
					if ($result->p >= $threshold)
					{
						union($i, $j);
					}					
				}
				else
				{
					// Just merge (e.g., if set of records is based on sharing an identifier)
					union($i, $j);
				}
			}			
		}
		
		// Get list of components of graph, which are the sets rooted on each parent node
		$blocks = array();
	
		for ($i = 0; $i < $n; $i++)
		{
			$p = $parents[$i];
		
			if (!isset($blocks[$p]))
			{
				$blocks[$p] = array();
			}
			$blocks[$p][] = $i;
		}
		
		if (0)
		{
			echo "Blocks\n";
			print_r($blocks);
		}	
		
		// merge things 
		foreach ($blocks as $block)
		{
			// if component has more than one member then merge them
			if (count($block) > 1)
			{
				$cluster_id = $records[$block[0]]->id;
				
				$clusters[$cluster_id] = array();
								
				foreach ($block as $k => $v)
				{
					$member = $records[$v]->id;
					$clusters[$cluster_id][] = $member;
				}
			}
		}			
		
		
	}


	return $clusters;	
}

//----------------------------------------------------------------------------------------
// Find hashes for records added after a given time 
function get_buckets_to_update_hash_timestamp($from_timestamp)
{	
	global $elastic;
	
	$buckets = array();
	
	// Get all hashes for records with timestamps more recent than "$from_timestamp"
	$query = new stdclass;

	$query->size = 0;
	$query->query = new stdclass;
	$query->query->range = new stdclass;
	$query->query->range->{'search_data.timestamp'} = new stdclass;
	$query->query->range->{'search_data.timestamp'}->gte = $from_timestamp;

	$query->aggs = new stdclass;
	$query->aggs->{'v_y_fp'} = new stdclass;
	$query->aggs->{'v_y_fp'}->terms = new stdclass;
	$query->aggs->{'v_y_fp'}->terms->field = "search_data.hash_v_y_fp.keyword";
	$query->aggs->{'v_y_fp'}->terms->size	= 1000;

	//echo json_encode($query);
	//exit();

	$response = $elastic->send('POST',  '_search?pretty', json_encode($query));					

	// echo $response;

	$obj = json_decode($response);
	
	foreach ($obj->aggregations->v_y_fp->buckets as $bucket)
	{
		$buckets[] = $bucket->key;
	}
	
	return $buckets;
}

	
//----------------------------------------------------------------------------------------
// Get records for a hash
function get_records_for_hash($hash)
{	
	global $elastic;
	
	// get all records with this hash	
	$query = new stdclass;
	$query->size = 10;
	$query->query = new stdclass;
	$query->query->term = new stdclass;
	$query->query->term->{"search_data.hash_v_y_fp.keyword"} = new stdclass;
	$query->query->term->{"search_data.hash_v_y_fp.keyword"}->value = $hash;

	//echo json_encode($query);
	//exit();

	$response = $elastic->send('POST',  '_search?pretty', json_encode($query));					

	$response_obj = json_decode($response);

	//print_r($response_obj);

	$records = array();
	$record_index = array();

	$count = 0;

	if (isset($response_obj->hits))
	{
		foreach ($response_obj->hits->hits as $hit)
		{
			if (isset($hit->_source->search_display->csl->thumbnail))
			{
				unset($hit->_source->search_display->csl->thumbnail);
			}
			$records[] = $hit->_source;
			$record_index[$hit->_source->id] = $count++;
	
		}

	}
	
	return $records;
}

		
//----------------------------------------------------------------------------------------
// Given a set of records find clusters and update Elastic
function update_clusters_for_records($records)
{	
	global $elastic;	
	
	// If more than one record has the same hash then see if they should be clustered
	if (count($records) > 1)
	{
		// Get an integer index to the records (which are indexed by their id)
		$record_index = array();
		$count = 0;
		foreach ($records as $record)
		{
			$record_index[$record->id] = $count++;
		}
	
		//echo "Records:\n";
		//print_r($records);	
		//print_r($record_index);		
	
		// Find clusters for these records
		$clusters = merge_records($records, true);
	
		echo "Clusters:\n";
		print_r($clusters);
	
		// If we have any clusters update Elastic
		if (count($clusters) > 0)
		{
			foreach ($clusters as $cluster_id => $members)
			{
				foreach ($members as $member)
				{
					echo $cluster_id . '->' . $member . "\n";
				
					// get original record
					$doc = $records[$record_index[$member]];
				
					// update cluster_id
					$doc->search_data->cluster_id = $cluster_id;
				
					// send to Elastic
					$elastic_doc = new stdclass;
					$elastic_doc->doc = $doc;
					$elastic_doc->doc_as_upsert = true;
					$elastic->send('POST',  '_doc/' . urlencode($elastic_doc->doc->id). '/_update', json_encode($elastic_doc));					
				
				}
			}
		}					
	

	}

}

// test
if (0)
{

	$from_timestamp = time() - (60 * 60 * 8); // last 8 hours
	$from_timestamp = time() - (60 * 60 * 24); // last 24 hours
	$from_timestamp = time() - (60 * 60 * 1); // last  hour
	
	// get hashes
	$hashes = get_buckets_to_update_hash_timestamp($from_timestamp);
	
	print_r($hashes);
	
	foreach ($hashes as $hash)
	{
		// what records have this hash?
		$records = get_records_for_hash($hash);
		
		print_r($records);
		
		// cluster records
		update_clusters_for_records($records);
	}

}

// test
if (1)
{

	$hash = "191-1982-241";
	
	// what records have this hash?
	$records = get_records_for_hash($hash);
	
	//print_r($records);
	
	// cluster records
	update_clusters_for_records($records);

}
	
	


?>

