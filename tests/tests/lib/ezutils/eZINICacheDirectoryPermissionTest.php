<?php
/**
 * The mode of the INI cache directory (var/cache/ini) eZINI creates. No database is needed.
 *
 *  PD-01 - Without EZP_INI_DIR_PERMISSION the directory is created 0777, as before
 *  PD-02 - With EZP_INI_DIR_PERMISSION the directory and the missing ones above it get that mode
 *  PD-03 - The cache file in it keeps EZP_INI_FILE_PERMISSION's mode (0644 by default)
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package tests
 */
class eZINICacheDirectoryPermissionTest extends PHPUnit\Framework\TestCase
{
    /** @var string Absolute path of this test's directory under var/tmp */
    private $dir;
    /** @var string|null The INI cache directory before the test */
    private $cacheDirBefore;

    protected function setUp(): void
    {
        chdir( dirname( __DIR__, 4 ) );
        $this->dir = getcwd() . '/var/tmp/ezini_cache_dir_perm_' . getmypid() . '_' . bin2hex( random_bytes( 4 ) );
        mkdir( $this->dir, 0755, true );
        $this->cacheDirBefore = isset( $GLOBALS['eZINI_CONFIG_CACHE_DIR'] ) ? $GLOBALS['eZINI_CONFIG_CACHE_DIR'] : null;
    }

    protected function tearDown(): void
    {
        if ( $this->cacheDirBefore === null )
        {
            unset( $GLOBALS['eZINI_CONFIG_CACHE_DIR'] );
        }
        else
        {
            $GLOBALS['eZINI_CONFIG_CACHE_DIR'] = $this->cacheDirBefore;
        }
        eZDir::recursiveDelete( $this->dir );
    }

    /**
     * Loads site.ini with the cache in <dir>/site/ini/, which does not exist yet, so eZINI creates it.
     *
     * @return string The cache directory
     */
    private function loadWithANewCacheDirectory()
    {
        $cacheDir = $this->dir . '/site/ini/';
        $GLOBALS['eZINI_CONFIG_CACHE_DIR'] = $cacheDir;
        $wasEnabled = eZINI::isCacheEnabled();
        eZINI::setIsCacheEnabled( true );
        try
        {
            $ini = new eZINI( 'site.ini', 'settings', null, true );
            $this->assertNotSame( '', (string)$ini->variable( 'DatabaseSettings', 'DatabaseImplementation' ), 'site.ini was read' );
        }
        finally
        {
            eZINI::setIsCacheEnabled( $wasEnabled );
        }
        clearstatcache();
        $this->assertDirectoryExists( $cacheDir, 'the cache directory was created' );
        return rtrim( $cacheDir, '/' );
    }

    private function mode( $path )
    {
        clearstatcache();
        return fileperms( $path ) & 0777;
    }

    /** PD-01 */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState( false )]
    public function testWithoutTheConstantTheDirectoryIs0777()
    {
        $this->assertSame( 0777, eZINI::cacheDirectoryPermission() );
        $cacheDir = $this->loadWithANewCacheDirectory();
        $this->assertSame( 0777, $this->mode( $cacheDir ) );
    }

    /** PD-02, PD-03 */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState( false )]
    public function testTheConstantSetsTheModeOfTheDirectories()
    {
        define( 'EZP_INI_DIR_PERMISSION', 0750 );
        $this->assertSame( 0750, eZINI::cacheDirectoryPermission() );
        $cacheDir = $this->loadWithANewCacheDirectory();
        $this->assertSame( 0750, $this->mode( $cacheDir ) );
        $this->assertSame( 0750, $this->mode( dirname( $cacheDir ) ), 'the missing directory above it too' );

        $files = glob( $cacheDir . '/*.php' );
        $this->assertNotEmpty( $files, 'a cache file was written' );
        $this->assertSame( 0644, $this->mode( $files[0] ), 'the cache file keeps its own mode' );
    }
}
