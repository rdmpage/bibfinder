<?php

// Upload CSL  (in JSONL format) to Elasticsearch

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/populate.php');


$filename = "data/copeia.jsonl";
$filename = "data/test.jsonl";
//$filename = "data/cnki.jsonl";
//$filename = "data/bionames.jsonl";
$filename 	= 'data/herpmonographs.jsonl';
$filename 	= 'data/2095-0357.jsonl';

$count = 1;

$file_handle = fopen($filename, "r");
while (!feof($file_handle)) 
{
	$json = trim(fgets($file_handle));
	
	if (preg_match('/^\{/', $json))
	{
		$csl = json_decode($json);
		
		print_r($csl);
		upload($csl);
		
		// Give server a break every 10 items
		if (($count++ % 30) == 0)
		{
			$rand = rand(1000000, 3000000);
			echo "\n ...sleeping for " . round(($rand / 1000000),2) . ' seconds' . "\n\n";
			usleep($rand);
		}
	}
}




?>
