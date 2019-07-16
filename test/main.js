/*
	Require and initialise PhantomCSS module
	Paths are relative to CasperJs directory
*/

var fs = require( 'fs' );
var path = fs.absolute( fs.workingDirectory + '/phantomcss.js' );
var phantomcss = require( path );
var helpers = require('spider-helper.js')

// URL variables
var visitedUrls = [], pendingUrls = [], notVisitedUrls = [];

// to screenshot or not
var scrshot = getAttrib('shot', 1, 'Screenshot');

// to crawl or not
var crawl = getAttrib('crawl', 1, 'Crawling for hrefs');

// Regexp for urls to skip
var regexp = getAttrib('regexp', '#|ftp|javascript|.pdf', 'Regexp for urls to skip');

// delay before screenshoting
var delay = getAttrib('delay', 4000, 'Delay before screenshots in millisec');

// domain
var startUrl = getAttrib('url','https://digitalconcept.se', 'Start url');casper.cli.get("url");

// screenshot sizes
if (scrshot) {
	viewportSizes = [
		[320,480],
		[600,1024],
		[1024,768],
		[1280,800],
		[1440,900]
	];
	var viewport = casper.cli.get("viewport");
	if (typeof(viewport) == "undefined"){
		casper.echo(casper.colorizer.format('ScreenSize : [320,480], [600,1024], [1024,768], [1280,800], [1440,900]', { fg: 'magenta' }));
	} else {
		if(viewport == '320') viewportSizes = [viewportSizes[0]];
		if(viewport == '600') viewportSizes = [viewportSizes[1]];
		if(viewport == '1024') viewportSizes = [viewportSizes[2]];
		if(viewport == '1280') viewportSizes = [viewportSizes[3]];
		if(viewport == '1440') viewportSizes = [viewportSizes[4]];
		casper.echo(casper.colorizer.format('ScreenSize : ' + viewportSizes, { fg: 'magenta' }));
	}
}

// Use of url list
var extUrls = casper.cli.get("urllist");
if (typeof(extUrls) != "undefined"){
	require(extUrls);
	var startUrl = pendingUrls.shift();
	casper.echo(casper.colorizer.format('Loaded urls to test from : ' + extUrls, { fg: 'magenta' }));
}
casper.echo(pendingUrls);


var saveDir = startUrl.replace(/[^a-zA-Z0-9]/gi, '-').replace(/^https?-+/, '');

casper.test.begin( 'Visual tests of: ' + startUrl, function ( test ) {

	phantomcss.init( {
		rebase: casper.cli.get( "rebase" ),
		// SlimerJS needs explicit knowledge of this Casper, and lots of absolute paths
		casper: casper,
		libraryRoot: fs.absolute( fs.workingDirectory ),
		screenshotRoot: fs.absolute( fs.workingDirectory + '/screenshots' ),
		failedComparisonsRoot: fs.absolute( fs.workingDirectory + '/failures' ),
		addLabelToFailedImage: false,
		fileNameGetter: function(root,filename){ 
			// globally override output filename
			// files must exist under root
			// and use the .diff convention
			var name = root + '/ ' + filename;
			if(fs.isFile(name+'.png')){
				return name+'.diff.png';
			} else {
				return name+'.png';
			}
		},
		/*
		casper: specific_instance_of_casper,
		libraryRoot: '/phantomcss',
		fileNameGetter: function overide_file_naming(){},
		onPass: function passCallback(){},
		onFail: function failCallback(){},
		onTimeout: function timeoutCallback(){},
		onComplete: function completeCallback(){},
		hideElements: '#thing.selector',
		addLabelToFailedImage: true,
		outputSettings: {
			errorColor: {
				red: 255,
				green: 255,
				blue: 0
			},
			errorType: 'movement',
			transparency: 0.3
		}*/
	} );

	casper.on( 'remote.message', function ( msg ) {
		this.echo( msg );
	} )

	casper.on( 'error', function ( err ) {
		this.die( "PhantomJS has errored: " + err );
	} );

	casper.on( 'resource.error', function ( err ) {
		casper.log( 'Resource load error: ' + err, 'warning' );
	} );

	// Spider from the given URL
	function spider(url) {
		casper.thenOpen(url).then(function() {
			// Set the status style based on server status code
			var status = this.status().currentHTTPStatus;
			switch(status) {
				case 200: var statusStyle = { fg: 'green', bold: true }; break;
				case 404: var statusStyle = { fg: 'red', bold: true }; break;
				 default: var statusStyle = { fg: 'magenta', bold: true }; break;
			}

			// Display the spidered URL and status
			this.echo(this.colorizer.format(status, statusStyle) + ' ' + url);
			// Add the URL to the visited stack
			visitedUrls.push(url);

			// not checking/screenshoting 404 pages
			if(404 != status) {

				if(crawl) {
					// Find links present on this page
					var links = this.evaluate(function() {
						var links = [];
						Array.prototype.forEach.call(__utils__.findAll('a'), function(e) {
							links.push(e.getAttribute('href'));
						});
						return links;
					});

					// Add newly found URLs to the stack
					var baseUrl = this.getGlobal('location').origin;
					Array.prototype.forEach.call(links, function(link) {
						var newUrl = helpers.absoluteUri(baseUrl, link);
						if (
							pendingUrls.indexOf(newUrl) == -1 && 
							visitedUrls.indexOf(newUrl) == -1 && 
							notVisitedUrls.indexOf(newUrl) == -1
							) {
							casper.echo(
								casper.colorizer.format(
									'\t ' + newUrl + '<- Added to test list', 
									{ fg: 'magenta' }
								)
							);
							pendingUrls.push(newUrl);
						}
					});
				}

				if (scrshot) {
					casper.each(viewportSizes, function(self, viewportSize, i) {
				
						// set two vars for the viewport height and width as we loop through each item in the viewport array
						var width = viewportSize[0],
								height = viewportSize[1];
					
						//give some time for the page to load
						casper.wait(delay, function() {
					
							//set the viewport to the desired height and width
							this.viewport(width, height);
							casper.thenOpen(url, function() {
								phantomcss.screenshot( 'body', saveDir + '/' + url.replace(startUrl,'') + '/' + width );  
							});
						});
					});
				}
			}
		// If there are URLs to be processed, check if it's of the same domain, startUrl
		if (pendingUrls.length > 0) {
			var nextUrl = pendingUrls.shift();
			while (
				nextUrl && 
				pendingUrls.length >= 0 && 
				( 
					nextUrl.indexOf(startUrl) === -1 || 
					RegExp(regexp).test(nextUrl) 
				) 
				) {
				notVisitedUrls.push(nextUrl);
				this.echo(
					this.colorizer.format(
						'\t ' + nextUrl + '<- Removed from testing list', 
						{ fg: 'blue' }
					)
				);
				if (pendingUrls.length) {
					nextUrl = pendingUrls.shift();
				} else {
					nextUrl = false;
				}
			}
			this.echo(this.colorizer.format('\t Pending: ' + pendingUrls.length, { fg: 'yellow' }));
			if (nextUrl) spider(nextUrl);
		}

		});
	}

	casper.start( startUrl );
	spider(startUrl);
	if (scrshot) phantomcss.compareAll();

	/*
	Casper runs tests
	*/
	casper.run( function () {
		console.log( '\nTHE END.' );

	/*		casper.then( function () {
				casper.click( '#coffee-machine-button' );

				// wait for modal to fade-in 
				casper.waitForSelector( '#myModal:not([style*="display: none"])',
					function success() {
						phantomcss.screenshot( '#myModal', 'coffee machine dialog' );
					},
					function timeout() {
						casper.test.fail( 'Should see coffee machine' );
					}
				);
			} );

			casper.then( function () {
				casper.click( '#cappuccino-button' );
				phantomcss.screenshot( '#myModal', 'cappuccino success' );
			} );

			casper.then( function () {
				casper.click( '#close' );

				// wait for modal to fade-out
				casper.waitForSelector( '#myModal[style*="display: none"]',
					function success() {
						phantomcss.screenshot( {
							'Coffee machine close success': {
								selector: '#coffee-machine-wrapper',
								ignore: '.selector'
							},
							'Coffee machine button success': '#coffee-machine-button'
						} );
					},
					function timeout() {
						casper.test.fail( 'Should be able to walk away from the coffee machine' );
					}
				);
			} );
	*/

		// phantomcss.getExitStatus() // pass or fail?
		casper.test.done();
		this.echo(this.colorizer.format('Visited: ' + visitedUrls.length, { fg: 'green' }));
		this.echo(this.colorizer.format('Not visited: ' + notVisitedUrls.length, { fg: 'blue' }));
	} );
} );


function getAttrib(attr, defaultValue , text){
	var attr = casper.cli.get(attr);
	if (typeof(attr) == "undefined") var attr = defaultValue;
	var output = attr;
	if (!isNaN(attr)) output =  Boolean(attr);
	casper.echo(casper.colorizer.format(text + ' : ' + output, { fg: 'magenta' }));
	return attr;
}