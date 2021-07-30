<?php

/***********
 *	
 *	ExpiringMediaCache
 *	https://freshvine.co/
 *	
 *	© Paul Prins
 *	https://paulprins.net https://paul.build/
 *	
 *	Licensed under MIT - For full license, view the LICENSE distributed with this source.
 *		
 *	This is a simple and very focused media scraper for Instagram. One of the main goals of this project is to be able to grab media from Instagram. Yet Instgram puts expiring timestamp codes with its media URIs and so we need to fetch the complete paths everytime we run our tests. This is how we'll ensure we have valid URIs (including GETs).
 *
 * Instagram is using React so we can simply grab the page JSON payload, then map through it. It is pretty easy, and should never not work.
 *
 ***********/


class InstagramScraper{
	const startString = '<script type="text/javascript">window._sharedData = ';
	const endString = '</script>';
	const userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36';
	
	
	public function getMediaPaths( $URL ){
		/*
		 * Prepare the Context for Fetching
		 */
		$host = parse_url( $URL, PHP_URL_HOST );
		$StreamContextOptions = array(
			'http'=>array(
				'method'=> "GET",
				'header'=> "host: " . $host . "\r\n",
				'user_agent' => self::userAgent
			)
		);
		$StreamContext = stream_context_create($StreamContextOptions);


		/*
		 * Open the file using the HTTP headers set above
		 */
		$HTML = file_get_contents($URL, false, $StreamContext);


		/*
		 * Cut the HTML down to only the react json payload
		 */
		$StartPosition = stripos( $HTML, self::startString) + strlen( self::startString );
		$EndPosition = stripos( $HTML, self::endString, $StartPosition );

		$JsonMarkup = rtrim( substr( $HTML, $StartPosition, $EndPosition - $StartPosition ), ';' );
		$AssocArray = json_decode( $JsonMarkup, true );

		if( !is_array( $AssocArray ) ){
			throw new \Exception('ExpiringMediaCache-InstagramScraper: Returned invalid JSON payload from instagram page markup.');
		}


		/*
		 * Prepare the response by getting the relivant media paths from the payload
		 */
		$Response = array();
		$MediaGraphQL = $AssocArray['entry_data']['PostPage'][0]['graphql'];

		if( $MediaGraphQL['shortcode_media']['__typename'] == 'GraphImage'){
			foreach( $MediaGraphQL['shortcode_media']['display_resources'] as $vars ){
				$Response[ $vars['config_width'] . 'w'] = $vars['src'];
			}
		}else if($MediaGraphQL['shortcode_media']['__typename'] == 'GraphVideo'){
			$Response['cover'] = $MediaGraphQL['shortcode_media']['display_url'];
			$Response['video'] = $MediaGraphQL['shortcode_media']['video_url'];
		}else
			throw new \Exception('ExpiringMediaCache-InstagramScraper: Unclear what media is being fetched.');

		return $Response;
	}
}
?>