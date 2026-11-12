#!/usr/bin/env node
/**
 * Interactive release script: bumps the version everywhere it's recorded,
 * rebuilds the plugin, and produces a versioned zip in dist/ (one file per
 * release, never overwritten -- see README).
 *
 * Usage: npm run release
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const readline = require( 'readline' );
const { execSync } = require( 'child_process' );

const ROOT_DIR = path.join( __dirname, '..' );
const PLUGIN_SLUG = 'bb-a11y-audit';
const PACKAGE_JSON = path.join( ROOT_DIR, 'package.json' );
const PLUGIN_FILE = path.join( ROOT_DIR, `${ PLUGIN_SLUG }.php` );
const README_TXT = path.join( ROOT_DIR, 'readme.txt' );

function readJson( file ) {
	return JSON.parse( fs.readFileSync( file, 'utf8' ) );
}

function bumpVersion( current, choice ) {
	const [ major, minor, patch ] = current.split( '.' ).map( Number );

	switch ( choice ) {
		case '1':
			return `${ major + 1 }.0.0`;
		case '2':
			return `${ major }.${ minor + 1 }.0`;
		case '3':
			return `${ major }.${ minor }.${ patch + 1 }`;
		default:
			return current;
	}
}

function replaceOrThrow( content, pattern, replacement, file ) {
	if ( ! pattern.test( content ) ) {
		throw new Error( `Could not find pattern ${ pattern } in ${ file }` );
	}
	return content.replace( pattern, replacement );
}

async function prompt( question ) {
	const rl = readline.createInterface( { input: process.stdin, output: process.stdout } );
	const answer = await new Promise( ( resolve ) => rl.question( question, resolve ) );
	rl.close();
	return answer.trim();
}

async function main() {
	const pkg = readJson( PACKAGE_JSON );
	const currentVersion = pkg.version;

	console.log( `Current version: ${ currentVersion }` );
	const choice = await prompt( 'Bump: [1] major  [2] minor  [3] patch  (Enter to keep current): ' );
	const newVersion = bumpVersion( currentVersion, choice );

	console.log( `Releasing version ${ newVersion }...` );

	// package.json
	pkg.version = newVersion;
	fs.writeFileSync( PACKAGE_JSON, JSON.stringify( pkg, null, '\t' ) + '\n' );

	// Plugin header comment + version constant.
	let pluginContent = fs.readFileSync( PLUGIN_FILE, 'utf8' );
	pluginContent = replaceOrThrow(
		pluginContent,
		/Version:\s*[\d.]+/,
		`Version: ${ newVersion }`,
		PLUGIN_FILE
	);
	pluginContent = replaceOrThrow(
		pluginContent,
		/define\(\s*'BB_A11Y_AUDIT_VERSION',\s*'[\d.]+'\s*\);/,
		`define( 'BB_A11Y_AUDIT_VERSION', '${ newVersion }' );`,
		PLUGIN_FILE
	);
	fs.writeFileSync( PLUGIN_FILE, pluginContent );

	// readme.txt "Stable tag".
	let readmeContent = fs.readFileSync( README_TXT, 'utf8' );
	readmeContent = replaceOrThrow(
		readmeContent,
		/Stable tag:\s*[\d.]+/,
		`Stable tag: ${ newVersion }`,
		README_TXT
	);
	fs.writeFileSync( README_TXT, readmeContent );

	// Rebuild (unzipped -- this script produces its own versioned zip below).
	execSync( 'bash scripts/build.sh', { cwd: ROOT_DIR, stdio: 'inherit' } );

	const distDir = path.join( ROOT_DIR, 'dist' );
	fs.mkdirSync( distDir, { recursive: true } );

	const zipName = `${ PLUGIN_SLUG }-${ newVersion }.zip`;
	execSync( `zip -r -q "${ path.join( distDir, zipName ) }" "${ PLUGIN_SLUG }"`, {
		cwd: path.join( ROOT_DIR, 'build' ),
		stdio: 'inherit',
	} );

	console.log( `\nRelease ${ newVersion } ready: dist/${ zipName }` );
}

main().catch( ( err ) => {
	console.error( err.message );
	process.exit( 1 );
} );
