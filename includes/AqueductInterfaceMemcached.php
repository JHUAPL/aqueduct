<?php
/*
Aqueduct: A linked data semantic web extension for MediaWiki
Copyright (C) 2010 The Johns Hopkins University/Applied Physics Laboratory

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/
if ( !defined( 'MEDIAWIKI' ) )
{
	die();
}


class AqueductInterfaceMemcached extends AqueductInterfaceBlackbook 
{
	//Like the Blackbook interface, but data is coming from memcached (loaded externally) instead of coming from Blackbook.
	protected function getMaterializeResults($uri)
	{
       		global $aqMemcacheHost, $aqMemcachePort;

		// Check for existance of memcache and configuration
		if (!function_exists(memcache_connect) || !isset($aqMemcacheHost))
			throw new Exception('memcached PHP functions not found, or aqMemcacheHost not set.');
		
		aqProfile("memcache");
	
       		// Connect to the server
       		$connection = memcache_connect($aqMemcacheHost, $aqMemcachePort);
       		if ($connection === FALSE)
       	        	throw new Exception('The connection to the memcached server failed.');
	
       		// Check to see if a value was hit.
       		$result = memcache_get($connection, $uri);

		aqProfile("arc");
		$parser = ARC2::getRDFParser();
		$parser->parse('',$result);
		$triples = $parser->getSimpleIndex(0);
		aqProfile("aq");

		return $triples;
	}
	
	protected function getExecuteResults($algorithm, $datasource, $query, &$isModel)
	{
		throw new Exception('Advanced operations are not supported for the memcached datasource.');	
	}

	protected function runPersistAlgorithm($rdfxml, $datasource)
	{
		throw new Exception('Cannot persist to the memcached datasource.');	
	}
}
