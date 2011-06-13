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
//Implementation of Blackbook interface that uses the Blackbook 3.0 web service interface
class AqueductInterfaceBlackbook30 extends AqueductInterfaceBlackbook 
{
	protected function getExecuteResults($algorithm, $datasource, $query, &$isModel)
	{
		//Determine the algorithm class name and execution method
		switch ($algorithm)
		{
			case 'LuceneKeyword':
			$method = 'executeKeyword';
			$algorithmclass = 'blackbook.ejb.server.algorithm';
			$isModel = FALSE;
			break;
			case 'SparqlConstructQuery':
			$method = 'executeSPARQL';
			$algorithmclass = 'blackbook.ejb.server.sparql';
			$isModel = TRUE;
			break;
			case 'SparqlDescribeQuery':
			$method = 'executeSPARQL';
			$algorithmclass = 'blackbook.ejb.server.sparql';
			$isModel = TRUE;
			break;
			case 'SparqlSelectQuery':
			$method = 'executeSPARQL';
			$algorithmclass = 'blackbook.ejb.server.sparql';
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
		if ($method == 'executeKeyword')
		{
			$callparams = array('queryRequest' => array('sourceDataSource' => $datasource,'query' => $query));
			$keywordresults = $this->bbWebServiceCall('blackbookws/AlgorithmManagerProxy',$method,$callparams, true)->return;
			$uris = $keywordresults->uris;
			if (!is_array($uris))
			{
				$uris = array($uris);
			}
			//error_log($uris);
			return $uris;
		}
		else if($method == 'executeSPARQL')
		{
			// TODO: annotations are missing in Blackbook3.0 RC 9 that must be fixed for this to work
			$callparams = array('query' => $query);
			$rdf = $this->bbWebServiceCall('blackbookws/SparqlQueryManagerProxy', 'query', $callparams, true)->return;
			return $rdf;
		}
		else
		{
			throw new Exception('Unknown algorithm execution method');
		}
	}
	
	protected function getMaterializeResults($uri)
	{
		$rdf = $this->getMaterializeResultsRDF($uri);
		aqProfile("arc");
		$parser = ARC2::getRDFXMLParser();
		$parser->parse('',$rdf);
		$triples = $parser->getSimpleIndex(0);
		aqProfile("aq");
		//Get rid of the "materialized=true" that Blackbook returns when materializing
		$forunset = NULL;
		foreach ($triples as $subj=>$subjcontents)
		{
			if (isset($subjcontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate']) &&
				$subjcontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate'][0]['value'] == 'urn:blackbook:materialized')
			$forunset = $subj;
		}
		if ($forunset !== NULL)
		{
			unset($triples[$forunset]);
		}
		return $triples;
	}
	
	private function getMaterializeResultsRDF($uri)
	{
		//Implementation of materialize that uses the PHP SOAP client
		$uriarray = array($uri);
		$materialize_parameters = array("uriSetRequest" => array("uris" => $uriarray));
		$materialize_results = $this->bbWebServiceCall("blackbookws/AlgorithmManagerProxy", "executeMaterialize", $materialize_parameters, true);
		$temp_datasource = $materialize_results->return->dataSource;
		$rdf=$this->retrieveResults($temp_datasource);
		//error_log($rdf);
		return $rdf;
	}
	
	protected function retrieveResults($tempDS)
	{
		$data = array("dataSource" => $tempDS);
		$algorithm_parameters = new SoapVar($data, SOAP_ENC_OBJECT, 'rdfRetrievalRequest');
		$jena_parameters = array("algorithm" => "Jena Retrieve", "algorithmRequest" => $algorithm_parameters);
		$retrieve_results = $this->bbWebServiceCall("blackbookws/AlgorithmManagerProxy", "execute", $jena_parameters, true);
		//Now we can delete the temp datasource because it will no longer be needed
		$this->bbWebServiceCall('blackbookws/MetadataManagerProxy', 'removeTemporaryDS', array('name'=>$tempDS), true);
		//BUG: Must delete it twice for it to actually delete
		$this->bbWebServiceCall('blackbookws/MetadataManagerProxy', 'removeTemporaryDS', array('name'=>$tempDS), true);
		return $retrieve_results;
	}

	// Call "persistProxy" or something of that sort using bbWebServiceCall with the given rdf into the given datasource.
	protected function runPersistAlgorithm($rdfxml, $datasource)
	{
		$persist_params = array('rdfRequest' => array("destinationDataSource" => $datasource, "rdf" => $rdfxml)); 
		$this->bbWebServiceCall('blackbookws/AlgorithmManagerProxy', 'executePersist', $persist_params, true);
	}
	
	//Function to be called from maintenance scripts
	public function deleteAllTemporaryDatasources()
	{
		$this->bbWebServiceCall('blackbookws/MetadataManagerProxy', 'removeAllTemporaryDS', array(), true);
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
		$blackbookURL = $this->mRow['aq_source_location'];
		$blackbookServerCertPath = $this->mRow['aq_source_cert_path'];
		$blackbookServerCertPassphrase = $this->mRow['aq_source_cert_pass'];
		$callparams = $params;
		if ($useProxy)
		{
			$callparams["publicKey"] = $this->getBrowserCertificate();
		}

		aqProfile("wsclient");
		$blackbookWSClient = new SOAPClient($blackbookURL . $managerName . "?wsdl",
			array('local_cert' => $blackbookServerCertPath, 'passphrase' => $blackbookServerCertPassphrase,'trace' => ($functionName == 'execute')));
		aqProfile("wscall");
		$results = call_user_func(array($blackbookWSClient, $functionName) ,$callparams);		
		aqProfile("aq");
		
		//error_log(print_r($results,true));
		if($functionName == 'execute')
		{
			$response = $blackbookWSClient->__getLastResponse();
			$xmldoc = DOMDocument::loadXML($response);
			$rdfnodes = $xmldoc->getElementsByTagName('rdf');
			if ($rdfnodes->length>0)
			{
				$results = $rdfnodes->item(0)->textContent;
			}
			else
			{
				throw new Exception('RDF not found in Blackbook results retrieval');
			}
		}
		return $results;
	}
}
