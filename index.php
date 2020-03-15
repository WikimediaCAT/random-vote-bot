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

if ( array_key_exists( "pretxt", $confjson ) ) {
	$props["pretxt"] = $confjson["pretxt"];
}

if ( array_key_exists( "weight", $confjson ) ) {
	$props["weight"] = $confjson["weight"];
}


$wpapi = Mwapi\MediawikiApi::newFromApiEndpoint( $wikiconfig["url"] );

// Login
if ( array_key_exists( "user", $wikiconfig ) && array_key_exists( "password", $wikiconfig ) ) {
	
	$wpapi->login( new ApiUser( $wikiconfig["user"], $wikiconfig["password"] ) );

}

if ( array_key_exists( "source", $props ) ) {
	
	$params = array( "prop" => "revisions", "rvlimit" => 1, "rvdir" => "older", "rvprop" => "content" );
	$params["titles"] = $props["source"];
	
	$query = new Mwapi\SimpleRequest( 'query', $params  );
	$outcome = $wpapi->postRequest( $query );
	
	#var_dump( $outcome );
	$text = "";
	$randomList = array();
	
	if ( array_key_exists( "query", $outcome ) ) {
		
		if ( array_key_exists( "pages", $outcome["query"] ) ) {
						
			if ( count( $outcome["query"]["pages"] ) > 0 ) {
				
				foreach ( $outcome["query"]["pages"] as $pageid => $page ) {
				
					if ( array_key_exists( "revisions", $page ) ) {
						
						if ( count( $page["revisions"] ) > 0 ) {
	
							$revision = $page["revisions"][0];

							if ( array_key_exists( "*", $revision ) ) {

								$randomList = processContentRandom( $revision["*"] );
								//var_dump( $randomList );
							}
						
						}
					}
				
				}
				
			}
			
		}
		
	}
			
	if ( count( array_keys( $randomList ) ) > 0 ) {
		
		$string = getRandomFromList( $randomList, $props["weight"] )." — votat per ~~~~ (PHP ".phpversion().")\n";
		
		// var_dump( $string ); exit();
		if ( array_key_exists( "target", $props ) ) {
					
			if ( $props["target"] === $props["source"] ) {
				
				if ( array_key_exists( "pretxt", $props ) ) {
					$string = $text."\n\n".$props["pretxt"].": ".$string;
				} else {
					$string = $text."\n\n".$string;
				}
				
			}
			

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
		} else {
			
			echo $string;
		}
	
	}
	
}


function processContentRandom( $content ) {
	
	$pages = array();
	
	$lines = explode( "\n", $content );
	
	foreach ( $lines as $line ) {
		if ( strpos( $line, "{{" ) === false ) {
			
			if ( strpos( $line, "*" ) !== false ) {
			
				$line = str_replace( "*", "", $line );
				$line = trim( $line );
	
				if ( $line !== "" ) {
					
					$pweight = 100;
					
					$parts = preg_split("/\s+/", $line );

					if ( count( $parts ) > 1 ) {
						
						$pweight = $parts[1];
					}
					
					$pages[$parts[0]] = $pweight;
				}
			}
		}
	}
	
	return $pages;
	
}


function getRandomFromList( $array, $weighting=false ) {
	
	if ( $weighting ) {
		
		return getRandomWeightedElement( $array );
		
	} else {
	
		$list = array_keys( $array );
		sort( $list );
		return $list[array_rand($list)];

	}
	
	

}

// https://stackoverflow.com/questions/445235/generating-random-results-by-weight-in-php
function getRandomWeightedElement(array $weightedValues) {
   $rand = mt_rand(1, (int) array_sum($weightedValues));

   foreach ($weightedValues as $key => $value) {
	 $rand -= $value;
	 if ($rand <= 0) {
	   return $key;
	 }
   }
 }

