<?php
/**
 * Runs after Strauss (`composer prefix`).
 *
 * 1. Strauss copies only PHP files, so copy the library's popup/notice assets next to it.
 * 2. Normalise permissions: Strauss creates 0700 directories, which the web server cannot
 *    read, so the copied assets would 403 wherever the build is deployed with modes intact.
 * 3. Pin the library's gettext calls to the literal 'profitly' text domain, so
 *    `wp i18n make-pot` can extract them into languages/profitly.pot.
 *
 * @package Profitly
 */

$root   = dirname( __DIR__ );
$source = $root . '/vendor/standalonetech/wp-telemetry/assets';
$target = $root . '/vendor-prefixed/standalonetech/wp-telemetry';

if ( ! is_dir( $source ) || ! is_dir( $target ) ) {
	fwrite( STDERR, "post-prefix: run `composer install` and Strauss first.\n" );
	exit( 1 );
}

$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ) );
foreach ( $files as $file ) {
	$to = $target . '/assets' . substr( $file->getPathname(), strlen( $source ) );
	if ( ! is_dir( dirname( $to ) ) ) {
		mkdir( dirname( $to ), 0755, true );
	}
	copy( $file->getPathname(), $to );
}

$tree = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/vendor-prefixed', FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
foreach ( $tree as $item ) {
	chmod( $item->getPathname(), $item->isDir() ? 0755 : 0644 );
}
chmod( $root . '/vendor-prefixed', 0755 );

$pattern = '/,\s*(?:\$domain|\$this->client->config\( \'text_domain\' \))\s*\)/';
foreach ( glob( $target . '/src/*.php' ) as $php ) {
	file_put_contents( $php, preg_replace( $pattern, ", 'profitly' )", file_get_contents( $php ) ) );
}
echo "post-prefix: assets copied, permissions normalised, text domain pinned.\n";
