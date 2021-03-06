<?php
//
// Copyright (c) Autodesk, Inc. All rights reserved
//
// Large Model Viewer Extractor
// by Cyrille Fauvel - Autodesk Developer Network (ADN)
// January 2015
//
// Permission to use, copy, modify, and distribute this software in
// object code form for any purpose and without fee is hereby granted,
// provided that the above copyright notice appears in all copies and
// that both that copyright notice and the limited warranty and
// restricted rights notice below appear in all supporting
// documentation.
//
// AUTODESK PROVIDES THIS PROGRAM "AS IS" AND WITH ALL FAULTS.
// AUTODESK SPECIFICALLY DISCLAIMS ANY IMPLIED WARRANTY OF
// MERCHANTABILITY OR FITNESS FOR A PARTICULAR USE.  AUTODESK, INC.
// DOES NOT WARRANT THAT THE OPERATION OF THE PROGRAM WILL BE
// UNINTERRUPTED OR ERROR FREE.
//
namespace ADN\Extract ;

use Symfony\Component\Console\Command\Command ;
use Symfony\Component\Console\Input\InputArgument ;
use Symfony\Component\Console\Input\InputInterface ;
//use Symfony\Component\Console\Input\InputOption ;
use Symfony\Component\Console\Output\OutputInterface ;
use Symfony\Component\Console\Application ;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem ;
//use Unirest ;

ini_set ('display_errors', E_ALL) ;
require_once __DIR__ . '/../vendor/autoload.php' ;
date_default_timezone_set ('UTC') ;
mb_internal_encoding ('UTF-8') ;
mb_http_output ('UTF-8') ;

class DlCommand extends Command {
	
	protected function configure () {
		$this
			->setName ('lmv:dl')
			->setDescription ('LMV dl command')
			->addArgument (
				'identifier',
				InputArgument::REQUIRED,
				'Project identitier'
			)
		;
	}

	protected function execute (InputInterface $input, OutputInterface $output) {
		$identifier =$input->getArgument ('identifier') ;
		if ( !$identifier )
			$identifier ='1799-Auobj' ;
		
		$path =utils::normalize (__DIR__ . "/../data/$identifier.json") ;
		$content =file_get_contents ($path) ;
		$data =json_decode ($content) ;
		
		$localFile =utils::normalize (__DIR__ . "/../tmp/{$data->name}") ;
		$file =fopen ($localFile, 'wb') ;
		$http =new \ADN\Extract\HttpRequest ($data->uri, [], null, null) ;
		$response =$http->get (function ($code, $chunk) use ($file, $data, $path) {
			if ( $code != 'data' )
				return (false) ;
			fwrite ($file, $chunk) ;
			$data->bytesRead +=strlen ($chunk) ;
			file_put_contents ($path, json_encode ($data)) ;
			return (true) ;
		}) ;
		fclose ($file) ;
		
		if ( !$response || $response->code != Response::HTTP_OK ) {
			$output->writeln ('oops') ;
			$fs =new Filesystem () ;
			$fs->remove ($path) ;
			$fs->remove ($localFile) ;
			return ;
		}
		
		$data->size =utils::findKey ($response->headers, 'Content-Length') ;
		$data->bytesRead =$data->size ;
		file_put_contents ($path, json_encode ($data)) ;

		utils::log ('ok') ;
	}
	
}

$application =new \Symfony\Component\Console\Application () ;
$application->add (new DlCommand ()) ;
$application->run () ;
