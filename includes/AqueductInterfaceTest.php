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


class AqueductInterfaceTest extends AqueductInterfaceBlackbook 
{
	//Like the Blackbook interface, but data is static instead of coming from Blackbook.
	protected function getMaterializeResults($uri)
	{
		if ($uri == 'uri:citydata:Chicago')
		{
			$ntriples = '
			<uri:citydata:Chicago> <uri:cityschema:HasNeighbor> <uri:citydata:Gary> .
			<uri:citydata:Chicago> <uri:cityschema:HasNeighbor> <uri:citydata:Skokie> .
			<uri:citydata:Chicago> <uri:cityschema:Population> "2853114" .
			<uri:citydata:Chicago> <uri:cityschema:State> "IL" .
			<uri:citydata:Chicago> <uri:cityschema:PovertyRate> "19.6" .
			';
		}
		else if ($uri == 'uri:citydata:Gary')
		{
			$ntriples = '
			<uri:citydata:Gary> <uri:cityschema:HasNeighbor> <uri:citydata:Chicago> .
			<uri:citydata:Gary> <uri:cityschema:Population> "99516" .
			<uri:citydata:Gary> <uri:cityschema:State> "IN" .
			<uri:citydata:Gary> <uri:cityschema:PovertyRate> "25.8" .
			';
		}
		else if ($uri == 'uri:citydata:Skokie')
		{
			$ntriples = '
			<uri:citydata:Skokie> <uri:cityschema:HasNeighbor> <uri:citydata:Chicago> .
			<uri:citydata:Skokie> <uri:cityschema:Population> "66559" .
			<uri:citydata:Skokie> <uri:cityschema:State> "IL" .
			<uri:citydata:Skokie> <uri:cityschema:PovertyRate> "5.4" .
			';
		}
		else
		{
			$ntriples = '';
		}
		
		aqProfile("arc");
		$parser = ARC2::getRDFParser();
		$parser->parse('',$ntriples);
		$triples = $parser->getSimpleIndex(0);
		aqProfile("aq");

		return $triples;
	}
	
	protected function getExecuteResults($algorithm, $datasource, $query, &$isModel)
	{
		throw new Exception('Advanced operations are not supported for the test datasource.');	
	}

	protected function runPersistAlgorithm($rdfxml, $datasource)
	{
		throw new Exception('Cannot persist to the test datasource.');	
	}
}
