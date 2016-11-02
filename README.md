WordpressdataPump
=================

A PHP data pump for importing blog posts from another system into Wordpress via the the XML-RPC WordPress API.

This class requires the Wordpress [XMLRPC Client by Hieu Le](https://github.com/letrunghieu/wordpress-xmlrpc-client) as an interface.  It's easy to include it if you are using [Composer](https://getcomposer.org/).

Progress to here is merely a working class that abstracts away some of the nitty-gritty of preparing and importing data from another system into Wordrpress.  We have used this data pump to import hundreds of blogs posts in varying formats into Wordpress.

## Dependencies
* "hieu-le/wordpress-xmlrpc-client":"~2.0"

## TODO
* Better logging and error reporting options.
* More input checking and error prevention.
* Build out more input options
* Add a config file for allowing customization of how things are handled, such as:
	* Switch to add gallery shortcodes to associated posts or not.
	* Switch to add featured image or not.
	* Gallery shortcodes additional options.
	* Caption and excerpt handling.
	* To name a few.

## Setup

You will need to use Composer to add the above-mentioned dependenices to your project.  We plan to implement this project in the same way at some point.

## Usage Example
	$endpoint = 'https://www.yourwpsite.com/xmlrpc.php';
	$username = 'username';
	$password = 'password';

	require('path/to/WordpressDataPump.php');
	$WPDP = new \ClearPathDigital\WordpressDataPump\WordpressDataPump($endpoint, $username, $password);

	$WPDP->setDate('Today');
	$WPDP->setTitle('WordPressDataPump Test Post');
	$WPDP->setContent('This is a test post created by WordPressDataPump.');
	$WPDP->addTerm('category', 'Testing');
	$WPDP->addTerm('post_tag', 'TestTag1');
	$WPDP->addTerm('post_tag', 'TestTag2');
	$WPDP->addMedia('path/to/picture.jpeg', 'WordpressDataPump Test Image', 'This is a caption for a test image uploaded by WordpressDataPump.');
	$WPDP->createPost();






?>
