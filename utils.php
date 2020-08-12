<?php

// utils

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
function clean_guid($guid)
{
	// DOIs are lower case
	if (preg_match('/^10./', $guid))
	{
		$guid = strtolower($guid);		
	}
	
	// JSTOR is HTTPS
	if (preg_match('/jstor.org/', $guid))
	{
		if (preg_match('/jstor.org\/stable\/(?<id>.*)/', $guid, $m))
		{
			$guid = 'https://www.jstor.org/stable/' . strtolower($m['id']);		
		}
	}
	

	return $guid;
}

?>
