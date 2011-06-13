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

abstract class AqueductInterfaceBlackbook extends AqueductInterface 
{
	//VERSION DEPENDENT FUNCTIONS
	protected abstract function getExecuteResults($algorithm, $datasource, $query, &$isModel);
	protected abstract function getMaterializeResults($uri);
	protected abstract function runPersistAlgorithm($rdfxml, $datasource);
	
	//FUNCTIONS CALLED BY THE API
	public function hardSet($title, $predicateURI, $object, $objecttype)
	{
		// Materialize the RDF.
		$uri = $this->titleToURI($title);
		$reifiedindex = $this->getMaterializeResults($uri);
		$unreifiedindex = AqueductUtility::unReifyRDF($reifiedindex);
		
		// Remove all data under this predicate and insert the new triple.
		$unreifiedindex[$uri][$predicateURI] = array(array('value' => $object, 'type' => $objecttype));

		// Re-serialize the triples.
		aqProfile("arc");
		$parser = ARC2::getRDFXMLParser();
		$rdfxml = $parser->toRDFXML($unreifiedindex);
		aqProfile("aq");

		// Collect the datasource name.
		$datasource = $this->mRow['aq_source_name'];

		//print '<br>Datasource: '.$datasource.'<br>RDFXML: <br><br>'.$rdfxml;

		// Persist the changes.
		// XXX: WARNING: This will only persist to ASSERTION datasources right now.
		//	Fairly be ye warned.
		$this->runPersistAlgorithm($rdfxml, $datasource);
	}

	public function materialize($title)
	{
		$result = $this->askWSCache($title);
		if ($result !== FALSE)
			return $result;
		
		$uri = $this->titleToURI($title);
		$result = $this->materializeAndFormatUris(array($uri), $title);
		$this->setWSCache($title, $result);
		return $result;
	}
	
	public function advancedOperation($title,$advancedrow)
	{
		//See if the is the operation supported by Blackbook (algorithm execution)
		if ($advancedrow['aq_query_type'] == 'ExecuteAlgorithm')
		{
			$datasource = $advancedrow['aq_datasource'];
			$query = $advancedrow['aq_query'];
			$algorithm = $advancedrow['aq_algorithm'];
			if ($title === NULL)
			{
				//No title because we are using an inline query.
			}
			else
			{
				if ($query == '')
				{
					//Blank query, so use the page title as the query
					$query = $title->getText();
				}
				else
				{
					if ($advancedrow['aq_query_uri_param'])
					{
						//Insert the page's URI in the query where a double hash mark is seen.
						$replace = $this->titleToURI($title);
					}
					else
					{
						//Insert the  page name as a literal in the query where a double hash mark is seen
						//This is hard because the page name may contain characters that must be escaped
						//in a SPARQL literal.
						$replace = AqueductUtility::escapeSPARQLParameter($title->getText());
					}
					$query = str_replace('##',$replace,$query);
				}
			}

			// Check the cache
			$result = $this->askWSCache($datasource . $algorithm . $query);
			if ($result !== FALSE)
				return $result;

			//Otherwise, Execute the algorithm normally
			$result = $this->getExecuteResults($algorithm,$datasource,$query,$isModel);
			
			//Get or process the RDF from the algorithm execution
			if ($isModel)
			{
				aqProfile("arcBBRDFparse");
				aqProfile("aq");
				if ($algorithm == 'SparqlSelectQuery')
				{
					$outputindexarray = array();
					//Special handler for SPARQL select query
					//Check to see if we have RDF-style results or W3C-style results
					$xmldoc = DOMDocument::loadXML($result);
					$currentresultid = 1;
					if (strpos($xmldoc->documentElement->tagName,'sparql') !== FALSE)
					{
						//W3C XML SPARQL results
						$nodelist = $xmldoc->getElementsByTagName('result');
						foreach ($nodelist as $node)
						{
							$resultnode = array();
							$childlist = $node->childNodes;
							foreach ($childlist as $child)
							{
								if ($child->tagName=='binding')
								{
									$bindingname = $child->getAttribute('name');
									$bindingnodes = $child->childNodes;
									foreach ($bindingnodes as $bindingnode)
									{
										if (strpos($bindingnode->tagName,'uri') !== FALSE)
										{
											$resultnode[$bindingname] []= array('value'=>$bindingnode->textContent,'type'=>'uri');
										}
										else if (strpos($bindingnode->tagName,'literal') !== FALSE)
										{
											$resultnode[$bindingname] []= array('value'=>$bindingnode->textContent,'type'=>'literal');
										}
									}
								}
							}
							$outputindexarray['_SPARQL_'.$currentresultid] = array('urn:sparqlresult'.$currentresultid=>$resultnode);
							
							$currentresultid++;
						}						
					}					
					else if (strpos($xmldoc->documentElement->tagName,'rdf') !== FALSE)
					{
						//RDF-formatted SPARQL results
						//Keep track of the mapping between the Blackbook result identifiers and our generated IDs
						$resultids = array();
						//Look for reified RDF statements about the results
						$parser = ARC2::getRDFParser();
						$parser->parse('', $result);
						$index = $parser->getSimpleIndex(0);
						foreach ($index as $nodeid=>$nodecontents)
						{
							if (isset($nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#subject']))
							{
								//This is a reified RDF statement
								$s = $nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#subject'][0]['value'];
								if (!$nodeids[$s])
								{
									$nodeids[$s] = $currentresultid;
									$currentresultid++;
									$outputindexarray['_SPARQL_' . $nodeids[$s]] = array();
								}
									$outputindexarray['_SPARQL_' . $nodeids[$s]][$nodeid] = $nodecontents;
							}
						}
					}
					else
					{
						throw new Exception('SPARQL query result document type could not be detected.'.$xmldoc->documentElement->tagName);
					}
					
				}
				else if ($algorithm == 'SparqlDescribeQuery')
				{
					$parser = ARC2::getRDFParser();
					$parser->parse('', $result);
					$index = $parser->getSimpleIndex(0);
					//Special handler for SPARQL describe query
					//This handler should also be used in any algorithm that returns a collection of clearly defined entities
					$outputindexarray = AqueductUtility::seperateReifiedRdfEntities($index);
				}
				else
				{
					$parser = ARC2::getRDFParser();
					$parser->parse('', $result);
					$index = $parser->getSimpleIndex(0);
					//Handler for SPARQL construct query, or any other query that cannot clearly be considered as
					//a collection of statements regarding clearly defined entities that reside in the dataset
					$outputindexarray[$title->getPrefixedDBkey()] = $index;
				}
				$this->setWSCache($datasource . $algorithm . $query, $outputindexarray);
				return $outputindexarray;
			}
			else
			{
				//We don't have RDF yet, just an array of URIs.
				//We must materialize.
				$materializeResult = $this->materializeAndFormatUris($result, NULL);

				$this->setWSCache($datasource . $algorithm . $query, $materializeResult);
				return $materializeResult;
			}
		}
		else
		{
			throw new Exception('Unsupported advanced operation type');
		}	
	}
	
	protected function materializeAndFormatUris($uriarray, $defaulttitle)
	{
		//Materialize each URI in the array and return the whole collection as an array pointing to RDF-JSON structures keyed by wiki title.
		//This implementation will call materialize once for every URI, so the entities don't get mixed up. In the future, we may want to change this for speed.
		$result = array();
		foreach ($uriarray as $uri)
		{
			// Check the cache
			$triples = $this->askWSCache($uri);
			if ($triples === FALSE)
			{
				$triples = $this->getMaterializeResults($uri);
				$this->setWSCache($uri, $triples);
			}
			
			if ($defaulttitle)
			{
				//There should be only one URI to materialize, and we know its wiki title already.
				$result[$defaulttitle->getPrefixedDBkey()] = $triples;
			}
			else
			{
				$result[AqueductInterface::uriToTitle($uri)] = $triples;
			}
		}		
		return $result;
	}

	protected function askWSCache($key)
	{
       		global $aqMemcacheHost, $aqMemcachePort, $aqMemcacheExpirationTime;

		// Check for existance of memcache and configuration
		if (!isset($aqMemcacheHost) || !function_exists(memcache_connect))
			return FALSE;
		
		aqProfile("memcache");
       		$result = FALSE;
	
       		// Connect to the server
       		$connection = memcache_connect($aqMemcacheHost, $aqMemcachePort);
	
       		// Fail out to a query call if not connected.
       		if ($connection === FALSE)
       	        	return FALSE;
	
       		// Check to see if a value was hit.
       		$result = memcache_get($connection, $key);
		aqProfile("aq");
		return $result;
	}
	

	protected function setWSCache($key, $result)
	{
       		global $aqMemcacheHost, $aqMemcachePort, $aqMemcacheExpirationTime;
	
		// Check for existance of memcache and configuration
		if (!isset($aqMemcacheHost) || !function_exists(memcache_connect))
			return FALSE;
		
		aqProfile("memcache");
       		// Connect to the server
       		$connection = memcache_connect($aqMemcacheHost, $aqMemcachePort);

       		// Fail out if not connected.
       		if ($connection === FALSE)
       	        	return FALSE;
	
		// Set the actual cache
       	        memcache_set($connection, $key, $result, MEMCACHE_COMPRESSED, $aqMemcacheExpirationTime);
		aqProfile("aq");
		return TRUE;
	}
}
