<?php
/**
 * File containing the eZFSFileHandlerTest class
 *
 * @copyright Copyright (C) 1999-2011 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 * @package tests
 */

class eZFSFileHandlerTest extends eZClusterFileHandlerAbstractTest
{
    /**
     * @var eZINI
     */
    protected $fileINI;

    protected $backupGlobals = false;

    protected $previousFileHandler;

    protected $clusterClass = 'eZFSFileHandler';

    /**
     * Test setup
     *
     * Load an instance of file.ini
     */
    public function setUp()
    {
        parent::setUp();

        // We need to clear the existing handler if it was loaded before the INI
        // settings changes
        if ( !eZClusterFileHandler::$globalHandler instanceof eZFSFileHandler )
            eZClusterFileHandler::$globalHandler = null;

        // Load database parameters for cluster
        // The same DSN than the relational database is used
        $fileINI = eZINI::instance( 'file.ini' );
        $this->previousFileHandler = $fileINI->variable( 'ClusteringSettings', 'FileHandler' );
        $fileINI->setVariable( 'ClusteringSettings', 'FileHandler', 'eZFSFileHandler' );
    }

    public function tearDown()
    {
        // restore the previous file handler
        if ( $this->previousFileHandler !== null )
        {
            $fileINI = eZINI::instance( 'file.ini' );
            $fileINI->setVariable( 'ClusteringSettings', 'FileHandler', $this->previousFileHandler );
            $this->previousFileHandler = null;
            eZClusterFileHandler::$globalHandler = null;
        }

        parent::tearDown();
    }


    /**
     * Test for the fetchUnique() method
     *
     * Doesn't do much with eZFS. Nothing, actually.
     */
    public function testFetchUnique()
    {
        $testFile = 'var/tests/' . __FUNCTION__ . '/file.txt';
        $this->createFile( $testFile, "contents" );

        $clusterHandler = eZClusterFileHandler::instance( $testFile );
        $fetchedFile = $clusterHandler->fetchUnique();

        self::assertSame( $testFile, $fetchedFile, "A unique name should have been returned" );

        self::deleteLocalFiles( $testFile, $fetchedFile );
    }

    public function testStartCacheGeneration()
    {
        self::assertTrue( eZClusterFileHandler::instance()->startCacheGeneration() );
    }

    public function testEndCacheGeneration()
    {
        self::assertTrue( eZClusterFileHandler::instance()->endCacheGeneration() );
    }

    public function testAbortCacheGeneration()
    {
        self::assertTrue( eZClusterFileHandler::instance()->abortCacheGeneration() );
    }

    public function testCheckCacheGenerationTimeout()
    {
        self::assertTrue( eZClusterFileHandler::instance()->abortCacheGeneration() );
    }

    public function testPrefork()
    {
        self::markTestSkipped( "preFork does nothing on non DB based handlers" );
    }
}
?>
