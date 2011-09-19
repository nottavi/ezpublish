<?php
/**
 * File containing the eZWebDAVBackendContentRegressionTest class.
 *
 * @copyright Copyright (C) 1999-2011 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 * @package tests
 */

require_once( 'wrappers.php' );

/**
 * Main test class for WebDAV tests.
 *
 * Read doc/features/specifications/trunk/webdav/testing.txt for information
 * about the WebDAV tests and what is needed to make them work on another
 * machine.
 *
 * This class requires the extension eZSiteAccessHelper
 * {@link http://svn.ez.no/svn/commercial/projects/qa/trunk/ezsiteaccesshelper/}.
 *
 * In the constructor:
 * - ./siteaccess/site.ini.append.php.replace is copied over site.ini.append.php,
 *   with certain values replaced dynamically (@ezc_siteaccess@ and
 *   @ezc_webdav_database@)
 * - the extension ezsiteaccesshelper is called to create the siteaccess with
 *   the same name as this class (in lowercase), and it will copy the site.ini.append.php
 *   file to the ezp/siteaccess/@ezc_siteaccess@ folder
 * - the extension ezsiteaccesshelper is called to enable the created siteaccess
 *   in ezp/siteaccess/override/site.ini.append.php
 */
class eZWebDAVBackendContentRegressionTest extends ezpDatabaseRegressionTest
{
    /**
     * Setting needed to keep the global variables working between the tests.
     *
     * @var bool
     */
    protected $backupGlobals = FALSE;

    /**
     * Setup webdav test siteaccess & fills $this->files with all the
     * .request files found in the regression directory, recursively.
     */
    public function __construct()
    {
        $siteaccess = strtolower( __CLASS__ );

        // List of values that should be placed in temporary site.ini file for webdav testing
        $replace = array();
        $dsn     = ezpTestRunner::dsn();
        $replace['@ezc_siteaccess@']  = $siteaccess;
        $replace['@ezc_db_phptype@']  = $dsn->phptype;
        $replace['@ezc_db_server@']   = $dsn->server;
        $replace['@ezc_db_port@']     = $dsn->port ? $dsn->port : '';
        $replace['@ezc_db_user@']     = $dsn->user;
        $replace['@ezc_db_password@'] = $dsn->password;
        $replace['@ezc_db_database@'] = $dsn->database;
        $replace['@ezc_db_socket@']   = $dsn->socket ? 'enabled' : 'disabled';

        // replace $replace values in site.ini.append.php.replace
        $contents = file_get_contents( dirname( __FILE__ ) . '/siteaccess/site.ini.append.php.replace' );
        foreach ( $replace as $key => $replacement )
        {
            $contents = str_replace( $key, $replacement, $contents );
        }

        // create siteaccess folder
        if ( !file_exists( 'settings/siteaccess/' . $siteaccess ) )
        {
            mkdir( 'settings/siteaccess/' . $siteaccess );
        }

        // store site.ini.append.php file
        file_put_contents( 'settings/siteaccess/' . $siteaccess . '/site.ini.append.php', $contents );

        // setup ini system to read siteaccess
        $ini = eZINI::instance();
        $ini->prependOverrideDir( 'siteaccess/' . $siteaccess, false, 'siteaccess', 'siteaccess' );
        $ini->loadCache();

        // load tests
        $this->readDirRecursively( dirname( __FILE__ ) . '/regression', $this->files, 'request' );

        parent::__construct();
    }

    /**
     * Called by PHPUnit before each test.
     */
    public function setUp()
    {
        // Call the setUp() in ezpDatabaseTestCase
        parent::setUp();

        // Set these to your own values
        // @todo get these values automatically from somewhere (how to get the password?)
        $GLOBALS['ezc_webdav_username'] = 'admin';
        $GLOBALS['ezc_webdav_password'] = 'publish';
        $GLOBALS['ezc_webdav_host'] = 'webdav.ezp';

        // A compound value from the above
        $GLOBALS['ezc_webdav_url'] = 'http://' . $GLOBALS['ezc_webdav_host'] . '/';

        // Set some server variables (not all of them are needed)
        $_SERVER['HTTP_USER_AGENT'] = 'cadaver/0.22.5 neon/0.26.3';
        $_SERVER['SERVER_NAME'] = $GLOBALS['ezc_webdav_host'];
        $_SERVER['SERVER_PORT'] = '80';

        // Set to null various variables used in the tests
        $GLOBALS['ezc_response_body'] = null;
        $GLOBALS['ezc_post_body'] = null;
        $GLOBALS['ezc_webdav_testfolder'] = null;
        $GLOBALS['ezc_webdav_testfolderobject'] = null;
        $GLOBALS['ezc_webdav_testfolderid'] = null;

        // Not sure if these 2 values are needed
        $_SERVER['SCRIPT_FILENAME'] = eZSys::rootDir() . DIRECTORY_SEPARATOR . 'index.php';
        $_SERVER['DOCUMENT_ROOT'] = eZSys::rootDir();

        $GLOBALS['ezc_siteaccess'] = strtolower( __CLASS__ );
    }

    /**
     * Called by PHPUnit after each test.
     */
    public function tearDown()
    {
        // Remove the created folder if it exists
        if ( $GLOBALS['ezc_webdav_testfolderobject'] !== null )
        {
            $GLOBALS['ezc_webdav_testfolderobject']->remove();
            $GLOBALS['ezc_webdav_testfolderobject'] = null;
        }

        /*$siteaccess = strtolower( __CLASS__ );

        //remove siteaccess site.ini file & directory
        if ( file_exists( 'settings/siteaccess/' . $siteaccess . '/site.ini.append.php' ) )
            unlink( 'settings/siteaccess/' . $siteaccess . '/site.ini.append.php' );

        if ( file_exists( 'settings/siteaccess/' . $siteaccess ) )
            rmdir( 'settings/siteaccess/' . $siteaccess );

        // refresh ini instance
        $ini = eZINI::instance();
        $ini->removeOverrideDir( 'siteaccess', 'siteaccess' );
        $ini->loadCache();*/

        parent::tearDown();
    }

    /**
     * Called by PHPUnit to create this test suite.
     */
    public static function suite()
    {
        $ini = eZINI::instance( 'i18n.ini' );

        list( $i18nSettings['internal-charset'], $i18nSettings['http-charset'], $i18nSettings['mbstring-extension'] ) =
            $ini->variableMulti( 'CharacterSettings', array( 'Charset', 'HTTPCharset', 'MBStringExtension' ), array( false, false, 'enabled' ) );

        eZTextCodec::updateSettings( $i18nSettings );

        return new ezpTestRegressionSuite( __CLASS__ );
    }

    /**
     * Clean the $text of dynamic stuff (creation date, etags, etc).
     *
     * Optionally append $body to $text, with "\n\n" between them.
     *
     * @param string $text
     * @param string $body
     * @return string
     */
    protected function cleanForCompare( $text, $body = null )
    {
        $result = $text;
        $result = preg_replace( array( '/ETag: (\w+)/' ), array( 'ETag: XXX' ), $result );
        $result = preg_replace( array( '/Server: ([\w\/\.]+)/' ), array( 'Server: XXX' ), $result );
        $result = preg_replace( '@<D:creationdate>.*?</D:creationdate>@', '<D:creationdate>XXX</D:creationdate>', $result );
        $result = preg_replace( '@<D:getlastmodified>.*?</D:getlastmodified>@', '<D:getlastmodified>XXX</D:getlastmodified>', $result );
        $result = preg_replace( '@<D:getetag>.*?</D:getetag>@', '<D:getetag>XXX</D:getetag>', $result );
        $result = preg_replace( '/@ezc_webdav_host@/', $GLOBALS['ezc_webdav_host'], $result );
        $result = preg_replace( '/@ezc_siteaccess@/', $GLOBALS['ezc_siteaccess'], $result );
        $result = preg_replace( '/@ezc_webdav_testfolder@/', $GLOBALS['ezc_webdav_testfolder'], $result );
        if ( $body !== null )
        {
            $result = "{$result}\n\n{$body}";
        }
        return $result;
    }

    /**
     * Skips the test $file if the directory name is uncommented in $skipTests
     * inside the function.
     *
     * @param string $file
     */
    protected function skip( $file )
    {
        // uncomment the tests that you want to skip
        $skipTests = array(
                // 'COPY',
                // 'DELETE',
                // 'GET',
                // 'HEAD',
                // 'MKCOL',
                // 'MOVE',
                // 'OPTIONS',
                // 'PROPFIND',
                // 'PROPPATCH',
                // 'PUT',
            );

        foreach ( $skipTests as $test )
        {
            if ( strpos( $file, $test ) !== false )
            {
                $this->markTestSkipped( "Test environment is configured to skip {$test} tests." );
                break;
            }
        }
    }

    /**
     * Runs the $file (.request file from $this->files) as a PHPUnit test.
     *
     * Steps performed:
     *  - setUp() is called automatically before this function
     *  - skip the test $file if declared. See {@link skip()}
     *  - identify test files attached to the .request file $file (.expected, .body)
     *  - initialize various global variables (together with $GLOBALS from setUp())
     *  - create an eZ Publish folder with the name $file
     *  - include $file (the .request file) as PHP code. $GLOBALS and $_SERVER values set in there
     *    will be used further on
     *  - initialize the WebDAV system like in webdav.php (it does NOT go through webdav.php)
     *  - create an eZWebDAVContentBackend object and use ezcWebdavServer to handle the
     *    WebDAV request specified in the .request file $file (through $_SERVER variables
     *    'REQUEST_METHOD' and 'REQUEST_URI' and others.
     *  - the output from the WebDAV system is collected in $GLOBALS['ezc_response_body']
     *    (through hacks in wrappers.php).
     *  - append the .body file contents to the $GLOBALS['ezc_response_body'] if it exists
     *  - clean the response and the .expected file with cleanForCompare() (eg. creation date, etags etc)
     *  - compare the response and the .expected file with assertEquals(). Same contents means the test passed.
     *  - tearDown() is called automatically after this function
     *
     * See doc/specifications/trunk/webdav/testing.txt for detailed information
     * about each $GLOBALS and $_SERVER variables.
     *
     * @param string $file
     */
    public function testRunRegression( $file )
    {
        // 'ezc' = use eZWebDAVContentBackend (new eZ Publish WebDAV based on ezcWebdav)
        // 'ezp' = use eZWebDAVContentServer  (old eZ Publish WebDAV)
        // Only 'ezc' is supported for now
        $system = 'ezc';

        // uncomment the tests that you want to skip in the skip() function
        $this->skip( $file );

        $error = '';
        $response = null;

        $outFile = $this->outFileName( $file, '.request', '.expected' );
        $bodyFile = $this->outFileName( $file, '.request', '.body' );

        $parts = pathinfo( $file );
        $GLOBALS['ezc_webdav_testfolder'] = $parts['filename'];

        // Create an eZ Publish folder for each test with the name of the test
        // $GLOBALS['ezc_webdav_testfolderid'] can be used in the .request file
        // to create file, image, folder etc under the test folder.
        $folder = new ezpObject( "folder", 2 );
        $folder->name = $GLOBALS['ezc_webdav_testfolder'];
        $folder->publish();
        $GLOBALS['ezc_webdav_testfolderobject'] = $folder;
        $GLOBALS['ezc_webdav_testfolderid'] = $folder->mainNode->node_id;

        // var_dump( $GLOBALS['ezc_webdav_testfolder'] . ' (' . $GLOBALS['ezc_webdav_testfolderid'] . ')' );

        //eZExtension::activateExtensions( 'default' );

        //eZModule::setGlobalPathList( array( "kernel" ) );

        eZUser::logoutCurrent();

        eZSys::init( 'webdav.php' );

        // include the .request file. $GLOBALS and $_SERVER defined in the file
        // will be used in the test
        include $file;

        // var_dump( '--- After include' );
        // These values can be overwritten in the included $file which contains the WebDAV request
        $username = $GLOBALS['ezc_webdav_username'];
        $password = $GLOBALS['ezc_webdav_password'];

        // Set the HTTP_AUTHORIZATION header
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( "{$username}:{$password}" );
        // var_dump( 'Default REQUEST_URI: ' . $_SERVER['REQUEST_URI'] );

        if ( $system === 'ezc' )
        {
            // Use eZ Components
            // clean the REQUEST_URI and HTTP_DESTINATION
            $_SERVER['REQUEST_URI'] = urldecode( $_SERVER['REQUEST_URI'] );
            if ( isset( $_SERVER['HTTP_DESTINATION'] ) )
            {
                $_SERVER['HTTP_DESTINATION'] = urldecode( $_SERVER['HTTP_DESTINATION'] );
            }

            $server = ezcWebdavServer::getInstance();

            $backend = new eZWebDAVContentBackend();
            $server->configurations = new ezcWebdavServerConfigurationManagerWrapper();

            $server->init( new ezcWebdavBasicPathFactory( $GLOBALS['ezc_webdav_url'] ),
                           new ezcWebdavXmlTool(),
                           new ezcWebdavPropertyHandler(),
                           new ezcWebdavHeaderHandler(),
                           new ezcWebdavTransportWrapper() );

            $server->auth = new eZWebDAVContentBackendAuth();
        }
        else
        {
            // Use the previous WebDAV system in eZ Publish
            $backend = new eZWebDAVContentServerWrapper();
        }

        $currentSite = $backend->currentSiteFromPath( $_SERVER['REQUEST_URI'] );
        if ( $currentSite )
        {
            $backend->setCurrentSite( $currentSite );
        }

        if ( $system === 'ezc' )
        {
            $server->handle( $backend );
        }
        else
        {
            $backend->processClientRequest();
        }

        // This value comes from the included $file which contains the WebDAV request
        $response = trim( $GLOBALS['ezc_response_body'] );

        $expected = trim( file_get_contents( $outFile ) );

        $body = null;
        if ( file_exists( $bodyFile ) )
        {
            $body = trim( file_get_contents( $bodyFile ) );
        }

        // replace dynamic text (eg. ETag) with static text
        // $body = optional body content (eg. for binary files) to be appended to $expected
        $expected = $this->cleanForCompare( $expected, $body );
        $response = $this->cleanForCompare( $response );

        $this->assertEquals( $expected, $response, "The expected response " . basename( $outFile ) . " (" . strlen( $expected ) . ") is not the same as the response got from request " . basename( $file ) . " (" . strlen( $response ) . "). {$error}" );
    }
}
?>
