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

//Implementation of Blackbook interface that uses the Blackbook 2.8 web service interface
class AqueductInterfaceBlackbook28 extends AqueductInterfaceBlackbook 
{
	protected function getExecuteResults($algorithm, $datasource, $query, &$isModel)
	{
		//Determine the algorithm class name and execution method
		switch ($algorithm)
		{
			case 'LuceneKeyword':
			$method = 'executeAlgorithmKeyword2URIProxy';
			$algorithmclass = 'blackbook.ejb.server.datamanager.LuceneKeyword';
			$isModel = FALSE;
			break;
			case 'SparqlConstructQuery':
			$method = 'executeAlgorithmKeyword2ModelProxy';
			$algorithmclass = 'blackbook.ejb.server.datamanager.SparqlConstructQueryAlgorithm';
			$isModel = TRUE;
			break;
			case 'SparqlDescribeQuery':
			$method = 'executeAlgorithmKeyword2ModelProxy';
			$algorithmclass = 'blackbook.ejb.server.datamanager.SparqlDescribeQueryAlgorithm';
			$isModel = TRUE;
			break;
			case 'SparqlSelectQuery':
			$method = 'executeAlgorithmKeyword2ModelProxy';
			$algorithmclass = 'blackbook.ejb.server.datamanager.SparqlSelectQueryAlgorithm';
			$isModel = TRUE;
			break;
			default:
			throw new Exception("Unknown algorithm $algorithm. New algorithms can be defined in the AqueductInterfaceBlackbook class.");
		}
		return $this->doExecute($method, $algorithmclass, $datasource, $query);
	}

	
	private function doExecute($method, $algorithm, $datasource, $query)
	{
		//Implementation of execute that uses the PHP SOAP client
		if ($method == 'executeAlgorithmKeyword2ModelProxy')
		{
			$callparams = array('arg1' => $algorithm, 'arg2' => $datasource,'arg3' => $query);
			$rdf = $this->bbWebServiceCall('blackbookws/DataManager',$method,$callparams,true)->return;
			return $rdf;
		}
		else if ($method == 'executeAlgorithmKeyword2URIProxy')
		{
			$callparams = array('arg1' => $algorithm, 'arg2' => $datasource,'arg3' => $query);
			$uriarray = $this->bbWebServiceCall('blackbookws/DataManager',$method,$callparams,true)->return;
			if (!is_array($uriarray))
			{
				$uriarray = array($uriarray);
				if ($uriarray[0] == NULL)
				{
					$uriarray = array();
				}
			}
			return $uriarray;
		}
		else
		{
			throw new Exception('Unknown algorithm execution method');
		}
	}
	
	protected function getMaterializeResults($uri)
	{
		//Implementation of materialize that uses the PHP SOAP client
		$uriarray = array($uri);
		$materialize_parameters = array("arg1" => $uriarray, "arg2" => "No Assertions");
		$materialize_results = $this->bbWebServiceCall("blackbookws/DataManager", "materializeProxy", $materialize_parameters, true);
		$rdf=$materialize_results->return;
		aqProfile("arc");
		$parser = ARC2::getRDFXMLParser();
		$parser->parse('',$rdf);
		$triples = $parser->getSimpleIndex(0);
		aqProfile("aq");
		return $triples;
	}

	// Call "persistProxy" or something of that sort using bbWebServiceCall with the given rdf into the given datasource.
	protected function runPersistAlgorithm($rdfxml, $datasource)
	{
		$callparams = array('arg1' => $datasource, 'arg2' => $rdfxml); 
		$this->bbWebServiceCall('blackbookws/DataManager', 'persistProxy', $callparams, true);
	}    

	protected function getBrowserCertificate()
	{
		$cert = $_SERVER["SSL_CLIENT_CERT"];
		if (!$cert)
		{
			throw new Exception('You must call the API through https and send a valid client certificate. Either you did not pass a valid client certificate, or Apache is not set up to do proper certificate validation.');
		}
		else
		{
			return $cert;
		}
	}
	
	private function bbWebServiceCall($managerName, $functionName, $params, $useProxy=false)
	{
		global $wgAqWsCache;
		if (!isset($wgAqWsCache))
		{
			$wgAqWsCache = array();
		}
		$blackbookURL = $this->mRow['aq_source_location'];
		$blackbookServerCertPath = $this->mRow['aq_source_cert_path'];
		$blackbookServerCertPassphrase = $this->mRow['aq_source_cert_pass'];
		$callparams = $params;
		if ($useProxy)
		{
			$callparams["arg0"] = $this->getBrowserCertificate();
		}
		$wsdlpath = $blackbookURL . $managerName . "?wsdl";
		if (!isset($wgAqWsCache[$wsdlpath]))
		{
			aqProfile("wsclient");
			$wgAqWsCache[$wsdlpath] = new SOAPClient($wsdlpath,
				array("local_cert" => $blackbookServerCertPath, "passphrase" => $blackbookServerCertPassphrase, "exceptions" => true));
		}
		
		aqProfile("wscall");
		$results = call_user_func(array($wgAqWsCache[$wsdlpath], $functionName), $callparams);
		aqProfile("aq");
		return $results;
	}
}
