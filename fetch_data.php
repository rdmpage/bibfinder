<?php

// Fetch CSL and store locally

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/utils.php');
require_once(dirname(__FILE__) . '/thumbnails/thumbnails.php');


//----------------------------------------------------------------------------------------
$source = 'unknown';


// Load from file
$filename   = 'test.txt';
$source 	= 'local';

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

if (1)
{
	// test
	$filename 	= 'sources/test.txt';
	$source 	= 'local';
}

if (0)
{
	// test
	$filename 	= 'sources/copeia.txt';
	$source 	= 'local';
}

if (0)
{
	// test
	$filename 	= 'sources/cnki.txt';
	$source 	= 'local';
}

if (1)
{
	// test
	$filename 	= 'sources/2095-0357.txt';
	$source 	= 'local';
}


$counter = 1;

$force = true;
//$force = false;

$data_dir = dirname(__FILE__) . '/data';

$data_filename = $filename;
$data_filename = str_replace('sources/', '', $data_filename);
$data_filename = str_replace('.txt', '', $data_filename);
$data_filename .= '.jsonl';

$data_filename = $data_dir . '/' . $data_filename;

echo $data_filename . "\n";

// If force then wipe data file and start again
if ($force)
{
	file_put_contents($data_filename, '');
}

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
		$obj = null;
		
		switch ($source)
		{
			case 'bionames':
				$sici = $guid;
				$sici = preg_replace('/https?:\/\/bionames.org\/references\//', '', $guid);
				$url = 'http://bionames.org/api/api_citeproc.php?id=' . $sici . '&style=csljson';
				$json = get($url);
			
				$obj = json_decode($json);				

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

				$obj = json_decode($json);				

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
				
					$obj = json_decode($json);				

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
				echo $guid . "\n";
			
				$url = '';
		
				// DOI
				if (preg_match('/^10./', $guid))
				{
					$url = 'http://localhost/~rpage/microcitation/www/citeproc-api.php?guid=' . urlencode($guid);
				}
				
				// Handle
				if (preg_match('/^\d+\//', $guid))
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
			//print_r($obj);
		
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
		
			//echo "GUID $guid\n";
			$guid = clean_guid($guid);
			
			$obj->guid = $guid;
			
			//echo "Cleaned GUID $guid\n";
			
			echo json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
			
			file_put_contents($data_filename, json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

		}
	
	}
}

?>
