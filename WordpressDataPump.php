<?php

namespace ClearPathDigital\WordpressDataPump;

/**
 * 
 * An XML-RPC client that implements the {@link http://codex.wordpress.org/XML-RPC_WordPress_API Wordpress API},
 * 					via the {@link https://github.com/letrunghieu/wordpress-xmlrpc-client XMLRPC Client by Hieu Le}.
 * 
 * @author  ClearPath Digital <info@clearpathdigital.com>
 * 
 * @version	0.0.1
 *
 * @license http://opensource.org/licenses/MIT MIT
 * 
 */
class WordpressDataPump
{

	private $debug = FALSE;
	private $post = FALSE;
	private $client;
	private $endpoint;
	private $username;
	private $password;
	private $log_delimiter = "\n";

	/**
	 * 
	 * Constructor.  Instantiates and connects client interface, registers client interface error callback.
	 * 
	 * @param		string Endpoint URI
	 * @param		string Username
	 * @param		string Password
	 * 
	 */
	public function __construct( $endpoint, $username, $password )
	{
		$this->endpoint = $endpoint;
		$this->username = $username;
		$this->client = new \HieuLe\WordpressXmlrpcClient\WordpressClient();
		$this->client->setCredentials( $endpoint, $username, $password );
		$this->client->onError( function( $error, $event ) {
			$this->error( "Error: [{$error}]: ".print_r( $event,1 ) );
		} );
		$this->post = new \StdClass();
	}

	/**
	 * 
	 * Create the new post and upload and attach media.
	 * 
	 * @return	integer Post ID
	 * 
	 */
	public function createPost()
	{
		$title = $this->post->title;
		$slug = $this->slugify( $title );
		$body = $this->post->content;

		$content = [
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => $title,
			'post_author' => '1',
			'post_excerpt' => '',
			'post_content' => $body,
			'post_date' => $this->post->date,
			'post_name' => $slug,
			'terms' => $this->post->terms,
			'terms_names' => $this->post->terms_names
		];
		$result = $this->client->newPost( $title, $body, $content );

		$this->log( "New Post ID: {$result}" );
		$this->log( "  Title: {$title}" );
		$this->log( "  Date: ".$this->post->date );
		$this->log( "  Slug: ".$slug );
		if( $this->debug ) $this->log( "  Body: ".$body );

		if( !empty( $this->post->media ) ) {
			foreach( $this->post->media as $m ) {
				$imgid = $this->uploadMedia( $result,$m );
			}

			// append the post content to include a simple gallery of images
			$gallery_ids = implode( ',', $this->post->media_ids );
			$this->log( "  Adding gallery ( {$gallery_ids} ) and featured image." );
			$gallery_shortcode = '[gallery ids="'.implode( ',', $this->post->media_ids ).'"]';
			$update = [
				'post_content' => $this->post->content . "\n\n{$gallery_shortcode}",
				'post_thumbnail' => $this->post->media_ids[0]
			];
			$this->updatePost( $result, $update );
		}
	}

	/**
	 * 
	 * Upload a media attachment to a post.
	 * 
	 * @param		integer Post ID
	 * @param		Object Media
	 * @return	Array Result
	 * 
	 */
	private function uploadMedia( $post,$media )
	{
		$this->log( "  Media:" );
		$this->log( "    Title: {$media->name}" );
		$this->log( "    Size: {$media->size}" );
		$this->log( "    MIME type: {$media->mime}" );
		$this->log( "    Uploading..." );
		$result = $this->client->uploadFile( $media->name, $media->mime, $media->bits, $overwrite = null, $postId = $post );
		$this->log( "    ID: ".$result['id'] );
		$this->log( "    Name: ".$result['name'] );
		$this->log( "    Type: ".$result['type'] );
		$this->log( "    URL: ".$result['url'] );

		$this->post->media_ids[] = $result['id'];

		$this->updatePost( $result['id'], [
			'post_title' => $media->title,
			'post_excerpt' => $media->caption
		] );
	}

	/**
	 * 
	 * Update a post with new metadata.
	 * 
	 * @param		integer Post ID
	 * @param		Array New Metadata
	 * 
	 */
	private function updatePost( $id, $content )
	{
		return $this->client->editPost( $id, $content );
	}

	/**
	 * 
	 * Register new media to upload with the post.
	 * 
	 * @param		string $path    Local path to media file.
	 * @param		string $name    Optional display name to add to file.
	 * @param		string $caption Optional caption to add to file.
	 * 
	 */
	public function addMedia( $path, $name=FALSE, $caption=FALSE )
	{
		if( !is_file( $path ) ) $this->error( "Not a valid file: {$path}" );
		if( empty( $name ) ) {
			$title = $this->slugify( explode( '.',basename( $path ) )[0] );
			$name = $this->slugify( basename( $path ),TRUE );
		} else {
			$title = $name;
			$name = $this->slugify( "{$name}." . array_pop( explode( '.', basename( $path ) ) ), TRUE );
		}
		if( !$file = fopen( $path, 'r' ) ) $this->error( "Cannot open file for reading: {$path}" );
		$mime = mime_content_type( $path );
		$size = filesize( $path );
		$bits = fread( $file, $size );
		fclose( $file );
		$this->post->media[] = ( object ) [
			'name' => $name,
			'title' => $title,
			'mime' => $mime,
			'size' => $size,
			'caption' => $caption,
			'bits' => $bits
		];	
		return TRUE;
	}

	/**
	 * Add a taxonomy term to the post
	 * 
	 * @param		string $tax  Taxonomy of new term.
	 * @param		string $term Term to add.
	 * @param		string $slug Optional slug.  Generated from the term by default.
	 * 
	 */
	public function addTerm( $tax, $term, $slug = FALSE )
	{
		if( !$slug ) $slug = $this->slugify( $term );
		$validtax = $this->getTaxonomies();
		if( !in_array( $tax,array_keys( $validtax ) ) ) $this->error( "Invalid taxonomy.  Valid taxonomies: ".print_r( $validtax,1 ) );
		$existing_terms = $this->getTerms( $tax );
		if( ( !in_array( $term,$existing_terms ) ) || ( empty( $existing_terms ) ) ) {
			$this->log( "Created new term ( {$tax} ) with ID {$result}: [{$slug}] => {$term}" );
			$this->post->terms[$tax][] = $result;
		} else {
			$this->log( "Using existing term ( {$tax} ): [{$slug}] => {$term}" );
			$this->post->terms_names[$tax][] = $term;
		}
	}

	/**
	 * 
	 * Get terms associated with a taxonomy.
	 * 
	 * @param		string $tax Taxonomy
	 * @return	Array       Terms
	 * 
	 */
	public function getTerms( $tax )
	{
		$array = [];
		$terms = $this->client->getTerms( $tax );
		foreach ( $terms as $tk => $t ) {
			$array[ $t['slug'] ] = $t['name'];
		}
		return $array;
	}

	/**
	 * 
	 * Get a list of terms for a taxonomy.
	 * 
	 * @return	Array Taxonomies
	 * 
	 */
	public function getTaxonomies()
	{
		$taxonomies = $this->client->getTaxonomies();
		foreach( $taxonomies as $tk => $t ) {
			$array[ $t['name'] ] = $t['label'];
		}
		return $array;
	}

	/**
	 * 
	 * Set the date of the post
	 * 
	 * @param		string $date Parseable date
	 * 
	 */
	public function setDate( $date )
	{
		if( empty( $date ) ) $this->error( "Date cannot be empty." );
		if ( ( $ts = strtotime( $date ) ) === FALSE ) $this->error( "Cannot parse date entry." );
		$dt = new \DateTime( "@$ts" ); 
		$this->post->date = $dt->format( 'Y-m-d H:i:s' );
		return TRUE;
	}

	/**
	 * 
	 * Set the title of the new post
	 * 
	 * @param		string $title Title
	 * 
	 */
	public function setTitle ( $title )
	{
		if( empty( $title ) ) $this->error( "Title cannot be empty." );
		$this->post->title = $title;
	}

	/**
	 * 
	 * Set the main content of the post.
	 * 
	 * @param		string $content Content.
	 * 
	 */
	public function setContent ( $content )
	{
		if( empty( $content ) ) $this->error( "Content cannot be empty." );
		$content = str_replace( chr( 146 ), chr( 39 ), $content );
		$this->post->content = $content;
	}

	/**
	 * 
	 * Add a tag to the post.
	 * 
	 * @param		string $tag Tag to add.
	 * 
	 */
	public function addTag ( $tag )
	{
		if( empty( $tag ) ) $this->error( "Content add empty tag." );
		$this->post->tags[] = $tag;
	}

	/**
	 * 
	 * Add a category to the post.
	 * 
	 * @param		string $category Category to add.
	 * 
	 */
	public function addCategory( $category )
	{
		if( empty( $category ) ) $this->error( "Content add empty category." );
		$this->post->categories[] = $category;
	}

	/**
	 * 
	 * Convert a string to a Wordpress-suitable slug.
	 * 
	 * @param		string  $string      String to Convert
	 * @param		boolean $is_filename Handle dots in a way that's suitable for filenames.
	 * @return	string               Slug.
	 * 
	 */
	private function slugify( $string, $is_filename = FALSE )
	{
		if( empty( $string ) ) $this->error( "Cannot slugify and empty string." );
		$allowed_chars = 'qwertyuiopasdfghjklzxcvbnm1234567890';
		if( $is_filename ) $allowed_chars .= '.';
		$array = str_split( strtolower( $string ) );
		foreach( $array as $ak => $a ) {
			if( strpos( $allowed_chars, $a ) === FALSE ) {
				$newstring .= '-';
			} else {
				$newstring .= $a;
			}
		}
		$newstring = trim( preg_replace( '!-+!', '-', $newstring ),'-' );
		$string = preg_replace( '/-?\.-?/','.',$string );
		$this->log( "Slugify: {$string} -> {$newstring}" );
		return $newstring;
	}

	/**
	 * 
	 * Enable debugging ( verbose ) output.
	 * 
	 */
	public function debugEnable()
	{
		$this->debug = TRUE;
		$this->client->onSending( function( $event ) {
			if( isset( $event['params'][3]['bits']->scalar ) ) $event['params'][3]['bits']->scalar = '[[ Binary Data Masked ]]';
			if( isset( $event['request'] ) ) $event['request'] = '[[ XML Request Masked ]]';
			$this->log( "===== RPC Client Sending =====" );
			$this->log( print_r( $event,1 ) );
			$this->log( "===============================" );
		} );
	}


	/**
	 * 
	 * Send a message to the log.
	 * 
	 * @param		string $message The message to log.
	 * 
	 */
	private function log( $message )
	{
		echo "$message{$this->log_delimiter}";
	}

	/**
	 * 
	 * Send a message to error output and exit.
	 * 
	 * @param		string $message The message to send.
	 * 
	 */
	private function error( $message )
	{
		echo $message;
		exit;
	}


}


?>