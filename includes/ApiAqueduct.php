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
require_once('extensions/AqueductExtension/includes/AqueductDbCalls.php');
require_once('extensions/AqueductExtension/includes/AqueductInterface.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceBlackbook.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceTest.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceEmbedded.php');
require_once('extensions/AqueductExtension/includes/AqueductUtility.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceBlackbook28.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceBlackbook30.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceMemcached.php');
require_once('extensions/AqueductExtension/includes/AqueductInterfaceArc2.php');
require_once('extensions/AqueductExtension/arc/ARC2.php');

class ApiAqueduct extends ApiBase
{
	public function __construct($main, $action)
	{
		parent :: __construct($main, $action);
	}

	public function execute()
	{
		aqProfile("aq");
		$params = $this->extractRequestParams();
		$t = Title::newFromText($params['subject']);
		$result = $this->getForest($t);
		//The result "forest" is a set of named ARC2 index structures
		//We will have to convert each one to RDF/JSON
		aqProfile("arc");
		$ser = ARC2::getRDFJSONSerializer();
		aqProfile("aq");
		foreach ($result as $key=>$value)
		{
			aqProfile("arcJSONRDFser");
			$sertriples = $ser->getSerializedIndex($value);
			aqProfile("aq");
			$this->getResult()->addValue(null, $key, json_decode($sertriples));
		}
		//If profiling is on, dump the profiling data with the RDF-JSON structure
		global $wgAqueductProfile,$aqProfileData;
		if ($wgAqueductProfile === TRUE)
		{
			$this->getResult()->addValue(null, "profiling", $aqProfileData);
		}
		aqProfile("mw");
	}
	
	protected function getForest($title)
	{
		global $wgAqueductDataAdded,$wgAqueductQueryAdded;
		$allforests = array();
		//Someone is requesting the data (forest) associated with the title $title
		//This function will return the forest
		if ($title->getArticleID() != 0)
		{
			//A wiki page is associated with the title. This page could have data inclusion tags (addData tags), meaning that
			//this function should combine RDF from multiple places
			//Parse the wiki page to see if it has any addData tags
			$wgAqueductDataAdded = array();
			$wgAqueductQueryAdded = array();
			$p = new Parser();
			wfAqueductSetParserHooks($p);
			$p->disableCache();
			$po = new ParserOptions();
			$a = new Article($title);
			$p->parse($a->getRawText(),$title,$po);
			$morepages = $wgAqueductDataAdded;
			$queryrows = $wgAqueductQueryAdded;
			unset($GLOBALS['wgAqueductDataAdded']);
			unset($GLOBALS['wgAqueductQueryAdded']);
			//At this point, $morepages has the titles specified in any addData tags
			foreach($morepages as $othertitle)
			{
				//There was an addData tag on the page associated with $title
				//Merge this title's data with the other title's data
				array_push($allforests, $this->getForest($othertitle));
			}
			//At this point, $queryrows has a collection of advanced row objects specified by any addQuery tags
			foreach($queryrows as $inlinequery)
			{
				//Merge this query's data with any other data
				array_push($allforests, $this->getForestFromRow(NULL,AqueductUtility::getBasicRowFromAdvancedRow($inlinequery),$inlinequery));
			}
		}
		
		//Also add the default model, if it exists
		$defaultmodel = $this->getForestDefaultModel($title,count($allforests)>0);
		if ($defaultmodel !== NULL)
		{
			array_push($allforests, $defaultmodel);
		}
		
		//Merge the forests  and return
		//Keep track of a disambiguation value that we will prepend to anything that starts with an underscore
		//(trees starting with an underscore must never be merged with other trees, even if the name is the same.
		//any name collisions are only due to autogeneration)
		$blanktreenumber = 0;
		$result = array();
		foreach ($allforests as $forest)
		{
			$blanktreenumber++;
			foreach ($forest as $treename => $tree)
			{
				if (substr($treename,0,1) == '_')
				{
					$n = '_' . $blanktreenumber . $treename;
				}
				else
				{
					$n = $treename;
				}
				if (isset($result[$n]))
				{
					//Tree already exists. Merge triples from two queries into a merged tree for display
					aqProfile("arcMergeForests");
					$result[$n] = ARC2::getMergedIndex($tree,$result[$n]);
					aqProfile("aq");
				}
				else
				{
					//Tree does not exist yet
					$result[$n] = $tree;
				}
			}
		}
		return $result;
	}
	
	protected function getForestFromRow($title, $basicrow, $advancedrow)
	{
		if (!isset($title) && $advancedrow === NULL)
		{
			throw new Exception('Specify a title to materialize.');
		}

		$interface = AqueductUtility::getInterfaceForRow($basicrow);
		if ($advancedrow!==NULL)
		{
			$result = $interface->advancedOperation($title,$advancedrow);
		}
		else
		{
			$result = $interface->materialize($title);
		}
		return $result;	
	}
	
	protected function getForestDefaultModel($t, $ignoreMissingModel = FALSE)
	{
		$basicrow = NULL;
		$advancedrow = NULL;
		AqueductUtility::getRowsForAqueductTitle($t, $basicrow, $advancedrow);
		if ($basicrow === NULL)
		{
			//Not a basic or advanced query
			if ($ignoreMissingModel)
			{
				return NULL;
			}				
			throw new Exception('No semantic data is available for '. $params['subject'] . ' (namespace ' . $ns . ').');
		}
		else
		{
			return $this->getForestFromRow($t, $basicrow, $advancedrow);
		}
	}
			
	public function getAllowedParams()
	{
		return array ('subject' => null);
	}
	
	public function getParamDescription()
	{
		return array ('subject' => 'RDF subject or query that you want to perform the action on, formatted as a wiki title.');
	}
	
	public function getDescription()
	{
		return array('Aqueduct RDF data API.');
	}

	public function getVersion()
	{
		return __CLASS__ . '1.0i';
	}
}
						
