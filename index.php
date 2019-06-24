<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Mediawiki\Api\ApiUser;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;
use \Wikibase\DataModel as WbDM;

ini_set('memory_limit', '-1'); # Comment if not needed

// Detect commandline args
$conffile = 'config.json';

if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

$confjson = json_decode( file_get_contents( $conffile ), 1 );

$wikiconfig = null;

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

$props = null;

if ( array_key_exists( "source", $confjson ) ) {
	$props["source"] = $confjson["source"];
}

if ( array_key_exists( "target", $confjson ) ) {
	$props["target"] = $confjson["target"];
}

$wpapi = Mwapi\MediawikiApi::newFromApiEndpoint( $wikiconfig["url"] );

// Login
if ( array_key_exists( "user", $wikiconfig ) && array_key_exists( "password", $wikiconfig ) ) {
	
	$wpapi->login( new ApiUser( $wikiconfig["user"], $wikiconfig["password"] ) );

}

if ( array_key_exists( "source", $props ) ) {
	
	$params = array( "prop" => "revisions", "rvlimit" => 1, "rvdir" => "newer", "rvprop" => "content" );
	$params["titles"] = $props["source"];
	
	$query = new Mwapi\SimpleRequest( 'query', $params  );
	$outcome = $wpapi->postRequest( $query );
	
	#var_dump( $outcome );
	$randomList = array();
	
	if ( array_key_exists( "query", $outcome ) ) {
		
		if ( array_key_exists( "pages", $outcome["query"] ) ) {
			
			if ( count( $outcome["query"]["pages"] > 0 ) ) {
				
				foreach ( $outcome["query"]["pages"] as $pageid => $page ) {
				
					if ( array_key_exists( "revisions", $page ) ) {
						
						if ( count( $page["revisions"] > 0 ) ) {
	
							$revision = $page["revisions"][0];

							if ( array_key_exists( "*", $revision ) ) {

								$randomList = processContentRandom( $revision["*"] );
							}
						
						}
					}
				
				}
				
			}
			
		}
		
	}
	
	if ( array_key_exists( "target", $props ) ) {
		
		if ( count( $randomList ) > 0 ) {
			
			$string = getRandomFromList( $randomList )." ~~~~ (PHP ".phpversion().")\n";
			
			#var_dump( $string );
			if ( array_key_exists( "target", $props ) ) {
		
				$params = array( "meta" => "tokens" );
				$getToken = new Mwapi\SimpleRequest( 'query', $params  );
				$outcome = $wpapi->postRequest( $getToken );
			
				if ( array_key_exists( "query", $outcome ) ) {
					if ( array_key_exists( "tokens", $outcome["query"] ) ) {
						if ( array_key_exists( "csrftoken", $outcome["query"]["tokens"] ) ) {
							
							$token = $outcome["query"]["tokens"]["csrftoken"];
							$params = array( "title" => $props["target"], "summary" => "Vot aleatori del bot", "text" => $string, "token" => $token );
							$sendText = new Mwapi\SimpleRequest( 'edit', $params  );
							$outcome = $wpapi->postRequest( $sendText );			
						
						}				
					}
				}
			}
		
		}
	}
	
}


function processContentRandom( $content ) {
	
	$pages = array();
	
	$lines = explode( "\n", $content );
	
	foreach ( $lines as $line ) {
		if ( strpos( $line, "{{" ) === false ) {
			
			$line = str_replace( "[[", "", $line );
			$line = str_replace( "]]", "", $line );
			$line = str_replace( "*", "", $line );
			$line = trim( $line );

			if ( $line !== "" ) {
				array_push( $pages, $line );
			}
		}
	}
	
	return $pages;
	
}


function getRandomFromList( $array ) {
	
	return $array[array_rand($array)];
	
}

