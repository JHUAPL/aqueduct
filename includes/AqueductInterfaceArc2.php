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

class AqueductInterfaceArc2 extends AqueductInterface
{
	protected function getEndpoint()
	{
		global $wgDBserver;
		global $wgDBname;
		global $wgDBuser;
		global $wgDBpassword;
		$config = array();
		$ep = null;
		if($this->mRow['aq_source_location'] == "")
		{
			$config = array(
				'db_host' => $wgDBserver,
				'db_name' => $wgDBname,
				'db_user' => $wgDBuser,
				'db_pwd' => $wgDBpassword,
				'store_name' => $this->mRow['aq_source_name']
				);
			$ep = ARC2::getStore($config);
			if (!$ep->isSetUp())
			{
			   $ep->setUp(); /* create MySQL tables */
			}
		}
		else
		{
			$config = array(
				'remote_store_endpoint' => $this->mRow['aq_source_location']
				);
			$ep = ARC2::getRemoteStore($config);
		}
		return $ep;
	
	}

	public function materialize($title)
	{
		$uri = $this->titleToURI($title);
		$ep = $this->getEndpoint();
		aqProfile("arc");
		$result = $ep->query('DESCRIBE <' . $uri . '>');
		aqProfile("aq");
		$triples = $result['result'];
		$result = array();
		$result[$title->getPrefixedDBkey()] = $triples;
		return $result;
    }
	
    public function advancedOperation($title,$advancedrow)
    {
		$query = $advancedrow['aq_query'];
		$ep = $this->getEndpoint();

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
					//Now we have the literal that we will insert into the query.
				}
				$query = str_replace('##',$replace,$query);
			}
		}

		aqProfile("arc");
		$result = $ep->query($query);
		aqProfile("aq");

		if($result['query_type'] == 'select')
		{
			$ret = array();
			$currentresultid = 1;
			foreach ($result['result']['rows'] as $row)
			{
				$resultnode = array();
				foreach ($result['result']['variables'] as $var)
				{
					$resultnode[$var] []= array('value'=>$row[$var],'type'=>$row[$var . ' type']);
				}
				$ret['_SPARQL_'.$currentresultid] = array('urn:sparqlresult'.$currentresultid=>$resultnode);
				$currentresultid++;
			}
		}
		else if ($result['query_type'] == 'describe')
		{
			$index = $result['result'];
			$ret = array();
			foreach ($index as $key => $value)
			{
				$myvalues = array();
				$myvalues[$key] = $value;
				$ret[AqueductInterface::uriToTitle($key)] = $myvalues;
			}
		}
		else
		{
			$index = $result['result'];
			$ret = array($title->getPrefixedDBkey() => $index);
		}
		
		return $ret;
    }

    public function hardSet($title, $predicateURI, $object, $objecttype)
    {
		$uri = $this->titleToURI($title);
		$ep = $this->getEndpoint();
		
		$ep -> insert(array(array('s'=>$uri,'p'=>$predicateURI,'o'=>$object,'o_type'=>$objecttype)),
			'urn:aqueduct:hardset');
		
    }
}
