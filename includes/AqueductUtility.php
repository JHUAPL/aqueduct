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

abstract class AqueductUtility
{
	//STATIC UTILITY FUNCTIONS
	
	//Take a wiki title as input, and see if it is associated with Aqueduct namespaces (as defined on the special page.)
	//If it is associated with a basic namespace, return the row in the basicrow parameter
	//If it is associated with an advanced namespace, return the rows in the basicrow and advancedrow parameters
	public static function getRowsForAqueductTitle($title, &$basicrow, &$advancedrow)
	{
		$transtable = aqDbGetTransTable();
		$basicrow = NULL;
		$advancedrow = NULL;
		$ns = $title->getNamespace();
		//See if this is a basic query
		foreach ($transtable as $row)
		{
			if ($row['aq_wiki_namespace_id'] == $ns)
			{
				$basicrow = $row;
				return;
			}
		}
		//See if this was an advanced query
		$querytable = aqDbTableGetAll('query');
		foreach ($querytable as $advrow)
		{
			if ($advrow['aq_wiki_namespace_id'] == $ns)
			{
				$advancedrow = $advrow;
				$basicrow = AqueductUtility::getBasicRowFromAdvancedRow($advrow);
			}
		}
		return;
	}
	
	//Look up the basic row that is "chained" to the given advanced row
	public static function getBasicRowFromAdvancedRow($advancedrow)
	{
		$transtable = aqDbGetTransTable();
		foreach ($transtable as $brow)
		{
			if ($brow['aq_wiki_namespace'] == $advancedrow['aq_wiki_parent_namespace'])
			{
				return $brow;
			}
		}
		if (!$foundrow)
		{
			throw new Exception('The parent namespace for an advanced query was not found.');
		}
	}
	
	//Return an instance of AqueductInterface that can be used to do queries for the given row
	public static function getInterfaceForRow($basicrow)
	{
		switch ($basicrow['aq_source_type'])
			{
				case 'BB':
					$interface = new AqueductInterfaceBlackbook28($basicrow);
				break;
				case 'Embedded':
					$interface = new AqueductInterfaceEmbedded($basicrow);
				break;
				case 'BB28':
					$interface = new AqueductInterfaceBlackbook28($basicrow);
				break;
				case 'BB30':
				$interface = new AqueductInterfaceBlackbook30($basicrow);
				break;
				case 'Test':
					$interface = new AqueductInterfaceTest($basicrow);
				break;
				case 'Memcached':
					$interface = new AqueductInterfaceMemcached($basicrow);
				break;
				case 'Arc2':
					$interface = new AqueductInterfaceArc2($basicrow);
				break;
				default:
					throw new Exception('The datasource type for this namespace is invalid or unsupported.');
			}
		return $interface;
	}
	
	//Convenience function that finds the Aqueduct configuration row associated with a title
	//for the sole purpose of converting the title to a URI.
	//To avoid wasted effort, this function should not be called if any interface functions will
	//ever be called on the title.
	//This convenience function takes the title as a string (unlike the other ones) and it can
	//throw an exception if the namespace is not under Aqueduct's control.
	public static function titleToURIStatic($titlestring)
	{
		$t = Title::newFromText($titlestring);
		$basicrow = NULL;
		$advancedrow = NULL;
		AqueductUtility::getRowsForAqueductTitle($t,$basicrow,$advancedrow);
		return AqueductUtility::getInterfaceForRow($basicrow)->titleToURI($t);
	}
	
	//Return an array where reified RDF statements are seperated by subject, and all statements for a particular subject
	//are put under the appropriate key of the returned array.
	//This is non-trivial because complex structures involving bnodes make the subject that a bnode is ultimately associated
	//with non-obvious.
	//Input: An ARC2 SimpleIndex structure
	//Output: An array where the keys are the subject URIs and the values are arrays of reified RDF statements
	//(each statement being represented as an ARC2 SimpleIndex)
	public static function seperateReifiedRdfEntities($index)
	{
		$bnodeMappings = array();
		$output = array();
		//Look for reified RDF statements about the results
		foreach ($index as $nodeid=>$nodecontents)
		{
			if (isset($nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#subject']))
			{
				//This is a reified RDF statement
				$s = $nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#subject'][0]['value'];
				$t = $nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#subject'][0]['type'];
				$o = $nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#object'][0]['value'];
				$ot = $nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#object'][0]['type'];
				//See if we know what the bnode is connected to
				if ($t == 'bnode' && isset($bnodeMappings[$s]))
				{
					$s = $bnodeMappings[$s];
				}
				//Put the node under the proper key of the output array
				if (!isset($output[$s]))
				{
					$output[$s] = array();
				}
				$output[$s][$nodeid] = $nodecontents;
				//See if this statement will allow us to disambiguate the ultimate subject of another statement
				if ($ot == 'bnode')
				{
					//The ultimate subject of the connected bnode is this node's subject
					if (isset($bnodeMappings[$o]))
					{
						throw new Exception('Rdf bnode structure is not a tree');
					}
					$bnodeMappings[$o] = $s;
					//Transitively remap the subject of any child bnodes that were known
					foreach ($bnodeMappings as $mapFrom=>$mapTo)
					{
						if ($mapTo == $o)
						{
							$bnodeMappings[$mapFrom] = $s;
						}
					}
					if (isset($output[$o]))
					{
						$output[$s] = array_merge($output[$s],$output[$o]);
						unset($output[$o]);
					}
				}
				//Finished processing this statement
			}
		}
		//Statements are now organized by their ultimate subject. Do sanity checks: exactly one URI subject should be represented
		//in each key of the output array.  Also convert URIs to wiki titles.
		$outputWithTitles = array();
		foreach ($output as $subject=>$statements)
		{
			$subjectfound = FALSE;
			foreach ($statements as $statement)
			{
				$s = $statement['http://www.w3.org/1999/02/22-rdf-syntax-ns#subject'][0]['value'];
				$t = $statement['http://www.w3.org/1999/02/22-rdf-syntax-ns#subject'][0]['type'];
				if ($t=='uri')
				{
					if ($s==$subject)
					{
						$subjectfound = TRUE;
					}
					else
					{
						throw new Exception('RDF processing error: Two subjects per entity');
					}
				}
			}
			if (!$subjectfound)
			{
				throw new Exception('RDF processing error: Bnode not connected to subject');
			}
			$outputWithTitles[AqueductInterface::uriToTitle($subject)] = $statements;
		}
		return $outputWithTitles;
	}
	
	//Take a reified RDF ARC2 index, and create an un-reified index in its place.
	//Crashes if the original index contained any tree-like structures (reified statements about bnodes) because
	//this logic has not been coded yet
	public static function unReifyRDF($index)
	{
		//Look for reified RDF statements about the results
		$triples = array();
		foreach ($index as $nodeid=>$nodecontents)
		{
			if (isset($nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#subject']))
			{
				//This is a reified RDF statement
				$s = $nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#subject'][0]['value'];
				$t = $nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#subject'][0]['type'];
				$p = $nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate'][0]['value'];
				$o = $nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#object'][0]['value'];
				$ot = $nodecontents['http://www.w3.org/1999/02/22-rdf-syntax-ns#object'][0]['type'];

				//Do not allow bnodes
				if ($t == 'bnode')
				{
					throw new Exception('RDF processing error: Blank nodes were found in the source data, and are not supported for this RDF operation.');
				}
				$triples []= array('s'=>$s,'p'=>$p,'o'=>$o,'s_type'=>'uri','o_type'=>$ot);				
			}
		}
		aqProfile("arcUnReify");
		$retvalue = ARC2::getSimpleIndex($triples, false);
		aqProfile("aq");
		return $retvalue;	
	}
	
	
	
	/**
 * Determine the Unicode codepoint of a single-character UTF-8 sequence.
 * Does not check for invalid input data.
 *
 * @param $char String
 * @return Integer
 * @public
 */
	public static function utf8ToCodepoint( $char )
	{
	        # Find the length
	        $z = ord( $char{0} );
	        if ( $z & 0x80 ) {
	                $length = 0;
	                while ( $z & 0x80 ) {
	                        $length++;
	                        $z <<= 1;
	                }
	        } else {
	                $length = 1;
	        }

	        if ( $length != strlen( $char ) ) {
	                return false;
	        }
	        if ( $length == 1 ) {
	                return ord( $char );
	        }

	        # Mask off the length-determining bits and shift back to the original location
	        $z &= 0xff;
	        $z >>= $length;

	        # Add in the free bits from subsequent bytes
	        for ( $i=1; $i<$length; $i++ ) {
	                $z <<= 6;
	                $z |= ord( $char{$i} ) & 0x3f;
	        }

	        return $z;
	}
	
	public static function escapeSPARQLParameter($text) 
	{
		//Insert the  page name as a literal in the query where a double hash mark is seen
		//This is hard because the page name may contain characters that must be escaped
		//in a SPARQL literal.
		$n = str_replace('\\','\\\\',$text);
		$n = str_replace(array('"',"'"),array('\\"',"\\'"),$n);
		//Convert UTF-8 encoded codepoints into the appropriate SPARQL escape sequence
		$replace = '';
		$accum = '';
		for ($x=0;$x<strlen($n);$x++)
		{
			$c = $n[$x];
			if (ord($c)<128)
			{
				//Not a "unicode" character, can output literally
				$accum = '';
				$replace .= $c;
			}
			else
			{
				//Try to decode UTF-8
				$accum .= $c;
				$codepoint = AqueductUtility::utf8ToCodepoint($accum);
				if ($codepoint!==false)
				{
					//Complete UTF-8 sequence found
					$seq = dechex($codepoint);
					while (strlen($seq)!=4 && strlen($seq)<8)
					{
						$seq = '0' . $seq;
					}
					if (strlen($seq)==4)
					{
						$seq = '\\u' . $seq;
					}
					else
					{
						$seq = '\\U' . $seq;
					}
					$replace .= $seq;
					$accum = '';
				}
			}
		}
		return $replace;
	}
}