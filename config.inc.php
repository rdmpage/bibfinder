<?php

error_reporting(E_ALL);

global $config;

// Date timezone--------------------------------------------------------------------------
date_default_timezone_set('UTC');

// Multibyte strings----------------------------------------------------------------------
mb_internal_encoding("UTF-8");

// Hosting--------------------------------------------------------------------------------

$site = 'local';
$site = 'heroku';

switch ($site)
{
	case 'heroku':
		// Server-------------------------------------------------------------------------
		$config['web_server']	= 'https://bibfinder.herokuapp.com'; 
		$config['site_name']	= 'BibFinder';

		// Files--------------------------------------------------------------------------
		$config['web_dir']		= dirname(__FILE__);
		$config['web_root']		= '/';		
		break;

	case 'local':
	default:
		// Server-------------------------------------------------------------------------
		$config['web_server']	= 'http://localhost'; 
		$config['site_name']	= 'BibFinder';

		// Files--------------------------------------------------------------------------
		$config['web_dir']		= dirname(__FILE__);
		$config['web_root']		= '/~rpage/bibfinder-o/';
		break;
}

// Environment----------------------------------------------------------------------------
// In development this is a PHP file that is in .gitignore, when deployed these parameters
// will be set on the server
if (file_exists(dirname(__FILE__) . '/env.php'))
{
	include 'env.php';
}

$config['cache']					= dirname(__FILE__) . '/cache';


$config['platform'] = 'local';
$config['platform'] = 'cloud';

if ($config['platform'] == 'local')
{

	// Local Docker Elasticsearch version 7.6.2 http://localhost:32772
	$config['elastic_options'] = array(
			'protocol' => 'http',
			'index' => 'bibfinder',
			'protocol' => 'http',
			'host' => 'localhost',
			'port' => 32769
			);

}

if ($config['platform'] == 'cloud')
{

	// Bitnami
	$config['elastic_options'] = array(
			'index' 	=> 'elasticsearch/bibfinder',
			'protocol' 	=> 'http',
			'host' 		=> '35.204.73.93',
			'port' 		=> 80,
			'user' 		=> getenv('ELASTIC_USERNAME'),
			'password' 	=> getenv('ELASTIC_PASSWORD'),
			);
}

?>
