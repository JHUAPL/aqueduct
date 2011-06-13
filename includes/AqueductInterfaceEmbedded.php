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


class AqueductInterfaceEmbedded extends AqueductInterface
{
	public function materialize($title)
	{
		$forest = $this->extractTriplesFromPage($title, NULL);
		if ($this->mRow['aq_search_fragments'])
		{
			$s = $title->getPrefixedText();
			$l = strpos($s, '\\');
			if ($l !== FALSE)
			{
				//Strip the fragment and make newtitle
				if ($l==0)
				{
					throw new Exception('Unexpected hash mark appeared in query URI');
				}
				$newtitle = Title::newFromText(substr($s,0,$l));
				$tree = $this->extractTriplesFromPage($newtitle,$title);
				if (isset($forest[$s]))
				{
					//Triples for the title were found both on the "base" and the "fragmented" page, so merge them
					$forest[$s] = ARC2::getMergedIndex($tree,$forest[$s]);
				}
				else
				{
					//Only found triples on the "base" page
					$forest[$s] = $tree;
				}
			}
		}
		return $forest;	
	}
	
	//If desiredtitle is NULL, return a key-value set of RDF-JSON structures, keyed by the title plus the found fragments
	//If desiredtitle is NOT NULL, just return an RDF-JSON structure representing all triples that match desiredtitle after appending the found fragment
	//The first and second param must be a Title object
	protected function extractTriplesFromPage($title,$desiredtitle)
	{
		global $wgAqueductTriplesAdded;
		$result = array();
		$wgAqueductTriplesAdded = array();
		//1. Parse the page that we are looking on and collect triples
		$p = new Parser();
		wfAqueductSetParserHooks($p);
		$p->disableCache();
		$po = new ParserOptions();
		$a = new Article($title);
		$p->parse($a->getRawText(),$title,$po);
		$triples = $wgAqueductTriplesAdded;
		unset($GLOBALS['wgAqueductTriplesAdded']);
		//2. Do title->uri conversion on the current page
		$uri = $this->titleToURI($title);
		$titletext = $title->getPrefixedDBkey();
		$desireduri = NULL;
		if ($desiredtitle !== NULL)
		{
			$desireduri = $this->titleToURI($desiredtitle);
		}
		//4. Construct an ARC2 index by appending the fragments to the base URIs for subject, use the literal predicates, and the uri or literal objects if they exist
		$tripleindex = array();
		$tripletitlecache = array();
		foreach ($triples as $triple)
		{
			//Determine the URI subject of the triple
			$triplesubject = $uri;
			if ($triple['fragment'] !== NULL)
			{
				$triplesubject = $triplesubject . '#' . $triple['fragment'];
			}
			//Determine if the triple will be thrown out (for having the wrong subject)
			if ($desireduri===NULL || $desireduri == $triplesubject)
			{
				//Do not throw out this triple
				if ($desireduri == NULL)
				{
					//We could return multiple named RDF graphs here. Figure out which graph to return
					if ($triple['fragment'] !== NULL)
					{
						if (isset($tripletitlecache[$triplesubject]))
						{
							$graphname = $tripletitlecache[$triplesubject];
						}
						else
						{
							$graphname = AqueductInterface::uriToTitle($triplesubject);
							$tripletitlecache[$triplesubject] = $graphname;					
						}
					}
					else
					{
						$graphname = $titletext;
					}
				}
				else
				{
					//Not returning multiple named graphs
					$graphname = NULL;
				}
				//Now we know the URI subject of the RDF triple, and the name of the RDF graph we will add it to
				$uripredicate = $triple['predicate'];
				//Determine the object of the RDF triple
				if ($triple['objecttype'] === NULL || $triple['objecttype'] == 'literal')
				{
					$object = $triple['object'];
					$objecttype = 'literal';
				}
				else if ($triple['objecttype'] == 'page')
				{
					$object = AqueductUtility::titleToURIStatic($triple['object']);
					$objecttype = 'uri';
				}
				else if ($triple['objecttype'] == 'uri')
				{
					$object = $triple['object'];
					$objecttype = 'uri';
				}
				else
				{
					throw new Exception('Bad object type in embedded triple');
				}
				//Add the RDF triple to the graph
				if ($graphname !== NULL)
				{
					$result[$graphname][$triplesubject][$uripredicate][] = array('value' => $object, 'type' => $objecttype);
				}
				else
				{
					$result[$triplesubject][$uripredicate][] = array('value' => $object, 'type' => $objecttype);
				}
			}
		}
		return $result;
	}
			
	public function advancedOperation($title,$advancedrow)
	{
		throw new Exception('Advanced operations are not supported for the embedded datasource.');	
	}

	public function hardSet($title, $predicateURI, $object, $objecttype)
	{
		throw new Exception('Cannot persist to the embedded datasource.');	
	}
}
