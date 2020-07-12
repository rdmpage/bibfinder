<?php


$q = 'Wang, M., Wu, P. L. & Zhang, F. (2015). Descriptions of two new species of the Clubiona corticalis-group (Araneae: Clubionidae) from China. Acta Arachnologica 64(2): 83-89.';

$q = 'Michener, C.D. and T.L. Griswold. 1994. The classification of Old World Anthidiini (Hymenoptera, Megachilidae). The University of Kansas Science Bulletin 55: 299-327';

if (isset($_GET['q']))
{
	$q = $_GET['q'];
}


function main($q)
{
	$obj = new stdclass;
	
	$obj->query = $q;

	$obj->found = false;

	// reconciliation API(s)
	
	$query = new stdclass;
	$key = 'q0';
	$query->{$key} = new stdclass;
	$query->{$key}->query = $obj->query;
	$query->{$key}->limit = 3;
	
	$endpoints = array(
		'http://localhost/~rpage/bibfinder/api_reconciliation.php?queries=',
		'https://biostor.org/reconcile?queries=',
	);
	
	$i = 0;
	$n = count($endpoints);
	
	while (!$obj->found && $i < $n)
	{
		$url = $endpoints[$i] . urlencode(json_encode($query));
		
		//echo $url . "\n";
	
		$opts = array(
		  CURLOPT_URL =>$url,
		  CURLOPT_FOLLOWLOCATION => TRUE,
		  CURLOPT_RETURNTRANSFER => TRUE
		);
	
		$ch = curl_init();
		curl_setopt_array($ch, $opts);
		$data = curl_exec($ch);
		$info = curl_getinfo($ch); 
		curl_close($ch);
		
		//echo $data;
		
	
		if ($data != '')
		{
			$response = json_decode($data);
		
			print_r($response);
			
			if (isset($response->{$key}->result))
			{
				if (isset($response->{$key}->result[0]))
				{
					if ($response->{$key}->result[0]->match)
					{
						$obj->found = true;
						$obj->score = $response->{$key}->result[0]->score;
						
						$obj->id = $response->{$key}->result[0]->id;
												
						if (preg_match('/^10\./', $obj->id))
						{
							$obj->doi = $obj->id;
						}
						
						if (preg_match('/jstor\./', $obj->id))
						{
							$obj->jstor = preg_replace('/https?:\/\/(www\.)?jstor.org\/stable\//', '', $obj->id);
						}
						
					}
				}
			
			}
		
			/*
			if (count($obj) == 1)
			{
				if ($obj[0]->match)
				{
					$doi = $obj[0]->id;
				}
			}
			*/
		
		}
		
		$i++;		
	
	}

	echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

}


main($q);

?>
