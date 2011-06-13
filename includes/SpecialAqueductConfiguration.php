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

        require_once('extensions/AqueductExtension/includes/AqueductDbCalls.php');

	class SpecialAqueductConfiguration extends SpecialPage
	{
		var $action;
		public function __construct()
		{
			parent::__construct( 'AqueductConfiguration','editinterface' );
			wfLoadExtensionMessages('AqueductExtension');
			$titleObj = Title::makeTitle( NS_SPECIAL, 'AqueductConfiguration' );
			$this->action = $titleObj->escapeLocalURL();
		}

		// Takes in a row to be checked for validity before it is inserted. If the row is being modified, the old NS id and name are passed in.

		// NOTE: that the functionality of this function is to return true if things complete properly and to toss an EXCEPTION if it fails
		// 	 This is meant to be inside a catch block!
		protected function confirmBasicRow($row, $oldID = null, $oldName = null, $oldURI = null)
		{
			global $wgCanonicalNamespaceNames;

			// Variable to hold all messages that will get thrown to the user.
			$errors = '';
			
			// Flags for use at end of function. Documented there.
			$oldnsrm = false;
			
			// Check that the namespace is a valid namespace.
			$legalcharpattern = '/^[A-Z][a-zA-Z0-9]*$/';
			// Check that the NS ID is even.
			if ($row['aq_wiki_namespace_id'] === '' || $row['aq_wiki_namespace_id'] % 2 !== 0)
			{
				$errors .= 'The namespace ID must be an even number.<br/>';
			}
			if ($row['aq_wiki_namespace'] === '' || !preg_match($legalcharpattern,$row['aq_wiki_namespace']))
			{
				$errors .= 'The namespace must be alphanumeric and start with a capital letter.<br/>';
			}
			else
			{
				// Check if this is a modification.
				if ($oldID !== null && $oldName !== null)
				{
					// Modification case
					// If nothing changed in the NS id or name, don't bother looking further.
					if ($oldID !== $row['aq_wiki_namespace_id'] || $oldName !== $row['aq_wiki_namespace'])
					{
						// Otherwise...
						// If there is content in the NS, don't change!
						$pages = aqNSHasPages($row['aq_wiki_namespace_id']);
						if (empty($pages) === false)
						{
							$errors .= 'This namespace has active content and cannot be modified without abandoning pages. Delete all the pages in this namespace if you really want to modify it. Alternatively, you can delete this row and create a new one.<br/>';
						}
						else
						{
							// If the name or id exist in oldns, give the "proper recycling" error
							$oldnsrows = aqDbTableGetAll('oldns');
							foreach ($oldnsrows as $oldnsrow)
							{
								if (($oldnsrow['aq_wiki_namespace_id'] === $row['aq_wiki_namespace_id'] && 
								     $oldnsrow['aq_wiki_namespace'] !== $row['aq_wiki_namespace']) ||
								    ($oldnsrow['aq_wiki_namespace_id'] !== $row['aq_wiki_namespace_id'] && 
								     $oldnsrow['aq_wiki_namespace'] === $row['aq_wiki_namespace']))
								{
									$errors .= 'The oldns row ['.$oldnsrow['aq_wiki_namespace_id'].'-'.$oldnsrow['aq_wiki_namespace'].'] must be properly recycled. Add a DIFFERENT row that matches both the ID *and* name of the oldns row you wish to recycle.<br/>';
									break;
								}
								
								// If the values both match a row in the database, add it to the table and delete it from the oldns database
								else if ($oldnsrow['aq_wiki_namespace_id'] === $row['aq_wiki_namespace_id'] && 
								    $oldnsrow['aq_wiki_namespace'] === $row['aq_wiki_namespace'])
								{
									// at this point this variable is a misnomer
									$oldnsrm = true;
									break;
								}
							}
							if ($oldnsrm === false)
							{
								// check namespaces for a conflict.
								foreach ($wgCanonicalNamespaceNames as $id => $name)
								{
									if ($id == $row['aq_wiki_namespace_id'])
									{
										$errors .= 'This namespace ID already exists.<br/>';
									}
									if ($name === $row['aq_wiki_namespace'])
									{
										$errors .= 'This namespace already exists.<br/>';
									}
								}
							}
						}
					}
				}
				else
				{
					// Addition case
					// Check oldns table for matches, if BOTH id and name match, flag for removal of row
					$oldnsrows = aqDbTableGetAll('oldns');
					foreach ($oldnsrows as $oldnsrow)
					{
						if (($oldnsrow['aq_wiki_namespace_id'] === $row['aq_wiki_namespace_id'] && 
						     $oldnsrow['aq_wiki_namespace'] !== $row['aq_wiki_namespace']) ||
						    ($oldnsrow['aq_wiki_namespace_id'] !== $row['aq_wiki_namespace_id'] && 
						     $oldnsrow['aq_wiki_namespace'] === $row['aq_wiki_namespace']))
						{
							$errors .= 'The oldns row ['.$oldnsrow['aq_wiki_namespace_id'].'-'.$oldnsrow['aq_wiki_namespace'].'] must be properly recycled. Add a DIFFERENT row that matches both the ID *and* name of the oldns row you wish to recycle.<br/>';
							break;
						}
						
						if ($oldnsrow['aq_wiki_namespace_id'] === $row['aq_wiki_namespace_id'] && 
						    $oldnsrow['aq_wiki_namespace'] === $row['aq_wiki_namespace'])
						{
							$oldnsrm = true;
							break;
						}
					}

					// Else, check namespaces for a conflict
					if ($oldnsrm === false)
					{
						foreach ($wgCanonicalNamespaceNames as $id => $name)
						{
							if ($id == $row['aq_wiki_namespace_id'])
							{
								$errors .= 'This namespace ID already exists.<br/>';
							}
							if ($name == $row['aq_wiki_namespace'])
							{
								$errors .= 'This namespace already exists.<br/>';
							}
						}
					}
				}
			}
	

			// Check the datastore uri prefix
			if ($row['aq_source_uri'] === '')
			{
				$errors .= 'Datastore URI prefix must not be empty.<br/>';
			}
			else
			{
				// If this is a modification, check to see if the URI even changed before querying the database!
				if ($oldURI === null || $oldURI !== $row['aq_source_uri'])
				{
					$basicrows = aqDbTableGetAll();
					foreach ($basicrows as $basicrow)
					{
						// Check URI against those in the table.
						if ($basicrow['aq_source_uri'] === $row['aq_source_uri'])
						{
							$errors .= 'This datastore URI already exists in the standard Aqueduct table.<br/>';
							break;
						}
					}
				}
			}
			
			$dsType = $row['aq_source_type'];

			// Check if the location, cert path, and cert pass exist if type is not test.
			if ($dsType !== 'Test' && $dsType !== 'Embedded' && $dsType != 'Memcached' && $dsType != 'Arc2')
			{
				if ($row['aq_source_name'] === '' || $row['aq_source_location'] === '' || $row['aq_source_cert_path'] === '' || $row['aq_source_cert_pass'] === '')
				{
					$errors .= 'If the datastore type is not "Test" or "Embedded" or "Memcached" or "Arc2" then the Name, Location, Cert Path, and Cert Password must be included.<br/>';
				}
			}

			if ($errors === '')
			{
				// Check if the oldns row needs to be removed.
				if ($oldnsrm === true)
				{
					aqDbDeleteRow(array('aq_wiki_namespace_id'=>$oldnsrow['aq_wiki_namespace_id']), 'oldns');
				}

				return true;
			}
			else
			{
				throw new Exception('The following errors occured:<br/>' . $errors);
			}
		}

		// NOTE: that the functionality of this function is to return true if things complete properly and to toss an EXCEPTION if it fails.
		// 	 This is meant to be inside a catch block!
		protected function confirmAdvancedRow($row, $oldID = null, $oldName = null)
		{
			global $wgCanonicalNamespaceNames;


			// Variable to hold all messages that will get thrown to the user.
			$errors = '';
			
			// Flags for use at end of function. Documented there.
			$oldnsrm = false;

                        // Check that the NS ID is even.        
                        if ($row['aq_wiki_namespace_id'] === '' || $row['aq_wiki_namespace_id'] % 2 !== 0)
			{                       
                		$errors .= 'The namespace ID must be an even number.<br/>';
                        }                                               

                        // Check that the namespace is a valid namespace.
                        $legalcharpattern = '/^[A-Z][a-zA-Z0-9]*$/';
                        $legalcharpatternsmall = '/^[a-zA-Z0-9]+$/';
                        if (($row['aq_wiki_parent_namespace'] === '' || !preg_match($legalcharpattern,$row['aq_wiki_parent_namespace']))
                           || ($row['aq_wiki_namespace_tag'] === '' || !preg_match($legalcharpatternsmall,$row['aq_wiki_namespace_tag'])))
			{                                       
                                $errors .= 'The namespace must be alphanumeric and start with a capital letter.<br/>';
                        }                                       
                        else                                            
                        {                                                   
				$rows = aqDbTableGetAll();
				$found = false;
				foreach ($rows as $basicrow)
				{
					if ($basicrow['aq_wiki_namespace'] === $row['aq_wiki_parent_namespace'])
					{
						$found = true;
						break;
					}
				}

				if ($found === false)
				{
					$errors .= 'The associated namespace must match a namespace in the standard Aqueduct table.<br/>';
				}

                                // Check if this is a modification.
                                if ($oldID !== null && $oldName !== null)
                                {                               
                                        // Modification case
					// If nothing changed in the NS id or name, don't bother looking further.
	                                if ("$oldID" !== $row['aq_wiki_namespace_id'] || "$oldName" !== $row['aq_wiki_parent_namespace'].'_'.$row['aq_wiki_namespace_tag'])
                                        {
	                                        // Otherwise...
                                                // If there is content in the NS, don't change!
						$pages = aqNSHasPages($row['aq_wiki_namespace_id']);
						if (empty($pages))
                                                {
							$errors .= 'This namespace has active content and cannot be modified without abandoning pages. Delete all the pages in this namespace if you really want to modify it. Alternatively, you can delete this row and create a new one.<br/>';
                                                }
                                                else
                                                {
	                                                // If the name or id exist in oldns, give the "proper recycling" error
                                                        $oldnsrows = aqDbTableGetAll('oldns');
                                                        foreach ($oldnsrows as $oldnsrow)
                                                        {
                                                                if (($oldnsrow['aq_wiki_namespace_id'] === $row['aq_wiki_namespace_id'] &&
                                                                     $oldnsrow['aq_wiki_namespace'] !== $row['aq_wiki_parent_namespace'].'_'.$row['aq_wiki_namespace_tag']) ||
                                                                    ($oldnsrow['aq_wiki_namespace_id'] !== $row['aq_wiki_namespace_id'] &&
                                                                     $oldnsrow['aq_wiki_namespace'] === $row['aq_wiki_parent_namespace'].'_'.$row['aq_wiki_namespace_tag']))
                                                                {
									$errors .= 'The oldns row ['.$oldnsrow['aq_wiki_namespace_id'].'-'.$oldnsrow['aq_wiki_namespace'].'] must be properly recycled. Add a DIFFERENT row that matches both the ID *and* name of the oldns row you wish to recycle.<br/>';
                                                                        break;
                                                                }
                                                		
								// If this does match the oldns row, move it to the new row in this database
								if ($oldnsrow['aq_wiki_namespace_id'] === $row['aq_wiki_namespace_id'] &&
                                                		    $oldnsrow['aq_wiki_namespace'] === $row['aq_wiki_parent_namespace'].'_'.$row['aq_wiki_namespace_tag'])
                                                		{
                                                		        $oldnsrm = true;
									break;
                                                		}
                                                        }
							if ($oldnsrm === false)
                                                        {
                                	                        // check namespaces for a conflict.
                                        	                foreach ($wgCanonicalNamespaceNames as $id => $name)
                                                	        {
                                                                        if ($id == $row['aq_wiki_namespace_id'])
                                                                        {
                                                                                $errors .= 'This namespace ID already exists.<br/>';
                                                                        }
                                                                        if ($name == $row['aq_wiki_parent_namespace'].'_'.$row['aq_wiki_namespace_tag'])
                                                                        {
                                                                                $errors .= 'This namespace already exists.<br/>';
                                                                        }
                                                                }
                                                        }
	                                        }
	                                }
	                        }
                                else
                                {
                                        // Addition case
                                        // Check oldns table for matches, if BOTH id and name match, flag for removal of row
                                        $oldnsrows = aqDbTableGetAll('oldns');
                                        foreach ($oldnsrows as $oldnsrow)
                                        {
						// check for the case when the id and name are mismatched  
                                                if (($oldnsrow['aq_wiki_namespace_id'] === $row['aq_wiki_namespace_id'] &&
                                                     $oldnsrow['aq_wiki_namespace'] !== $row['aq_wiki_parent_namespace'].'_'.$row['aq_wiki_namespace_tag']) ||
                                                    ($oldnsrow['aq_wiki_namespace_id'] !== $row['aq_wiki_namespace_id'] &&
                                                     $oldnsrow['aq_wiki_namespace'] === $row['aq_wiki_parent_namespace'].'_'.$row['aq_wiki_namespace_tag']))
                                                {
							$errors .= 'The oldns row ['.$oldnsrow['aq_wiki_namespace_id'].'-'.$oldnsrow['aq_wiki_namespace'].'] must be properly recycled. Add a DIFFERENT row that matches both the ID *and* name of the oldns row you wish to recycle.<br/>';
                                                        break;
						}

                                                if ($oldnsrow['aq_wiki_namespace_id'] === $row['aq_wiki_namespace_id'] &&
                                                    $oldnsrow['aq_wiki_namespace'] === $row['aq_wiki_parent_namespace'].'_'.$row['aq_wiki_namespace_tag'])
                                                {
                                                        $oldnsrm = true;
							break;
                                                }
                                        }

                                        // Else, check namespaces for a conflict
                                        if ($oldnsrm === false)
                                        {
                                                foreach ($wgCanonicalNamespaceNames as $id => $name)
                                                {
                                                        if ($id == $row['aq_wiki_namespace_id'])
                                                        {
                                                                $errors .= 'This namespace ID already exists.<br/>';
                                                        }
                                                        if ($name == $row['aq_wiki_parent_namespace'].'_'.$row['aq_wiki_namespace_tag'])
                                                        {
                                                                $errors .= 'This namespace already exists.<br/>';
                                                        }
                                                }
                                        }
                                }
                        }

			// Check Query Type
			if ($row['aq_query_type'] !== 'ExecuteAlgorithm')
			{
				$errors .= 'The query type must be one of the following: ExecuteAlgorithm.<br/>';
			}
			
			// Check Datasource Name
			if ($row['aq_datasource'] === '')
			{
				$errors .= 'The datastore name must be specified.<br/>';
			}
			
			// Check the query
			if ($row['aq_query'] === '')
			{
				$errors .= 'The query must be specified.<br/>';
			}

			if ($errors === '')
			{
				// Check if the oldns row needs to be removed.
				if ($oldnsrm === true)
				{
					aqDbDeleteRow(array('aq_wiki_namespace_id'=>$oldnsrow['aq_wiki_namespace_id']), 'oldns');
				}
			
				return true;
			}
			else
			{
				throw new Exception('The following errors occured:<br/>' . $errors);
			}
		}




		// **********************************************
		// Actual start of the workhorse for the program
		// **********************************************
		public function execute()
		{
			global $wgOut, $wgUser, $wgRequest, $IP, $wgTitle;
	
			// Declare the names of the columns in the databases.
			$uriColumns = array('aq_wiki_namespace_id',
					'aq_wiki_namespace', 
					'aq_source_uri', 
					'aq_source_type', 
					'aq_source_name', 
					'aq_source_location', 
					'aq_source_cert_path', 
					'aq_source_cert_pass', 
					'aq_initial_lowercase',
					'aq_search_fragments');
			$queryColumns = array('aq_wiki_namespace_id', 
					'aq_wiki_parent_namespace', 
					'aq_wiki_namespace_tag', 
					'aq_query_type', 
					'aq_datasource', 
					'aq_algorithm', 
					'aq_query_uri_param',
					'aq_query');

			// Declare the unique ID for the tables.
			$uriIDColumn = $uriColumns[0];
			$queryIDColumn = $queryColumns[0];

			//Is the user trying to access the special page without permissions?
			//Even though the wiki omits the restricted special page from the special page list for users without permission, we must check here
			//in case the user accesses the page through a bookmark.
			if ( $this->userCanExecute( $wgUser ) )
			{
				//Process the previously posted commands
				foreach($_POST as $submitkey => $submitval)
				{
					if ($submitkey === "add_row_uri")
					{
						// Create an associative array to pass
						$values = array();
			                        $row = array();
						for ($i = 0; $i < sizeof($uriColumns); $i += 1)
						{
							$values[$i] = $_POST["new_uri_"."$uriColumns[$i]"];
							$row[$uriColumns[$i]] = $values[$i];
						}

						// Checkboxes need a little modification
                                                if ($row['aq_initial_lowercase'] === NULL)
                                                {
                                                        $row['aq_initial_lowercase'] = '0';
                                                }

                                                if ($row['aq_search_fragments'] === NULL)
                                                {
                                                        $row['aq_search_fragments'] = '0';
                                                }

						try {
							$this->confirmBasicRow($row);
							aqDbInsertRow($row);
						} catch (Exception $e) {
							$wgOut->addHTML('<span style="color:red;">' . $e->getMessage() . '</span>');
						}
					}
					else if (strpos($submitkey,"delete_uri-") === 0)
					{
						$ID = substr($submitkey, 11);
						$name = $_POST['name-'.$ID];
						try {

							// Make sure no query row relies on this namespace
							$advrows = aqDbTableGetAll('query');
							foreach ($advrows as $advrow)
							{
								// Check URI against those in the table.
								if ($advrow['aq_wiki_parent_namespace'] === $name)
								{
									$errors .= 'A row in the advanced query Aqueduct table relies on this row. Delete the row(s) that reference this namespace before deleting this row.<br/>';
									throw new Exception('The following errors occured:<br/>' . $errors);
								}
							}

							// Check if NS has pages, if so, add to oldns table
							$pages = aqNSHasPages($ID);
							if (empty($pages) === false)
							{
								aqDbInsertRow(array('aq_wiki_namespace_id'=>$ID, 'aq_wiki_namespace'=>$name), 'oldns');
							}

							aqDbDeleteRow(array($uriIDColumn=>$ID));                                                
						} catch (Exception $e) {
							$wgOut->addHTML('<span style="color:red;">' . $e->getMessage() . '</span>');
                                                }

					}
					else if (strpos($submitkey,"modify_uri-") === 0)
					{
						$ID = substr($submitkey, 11);
						$name = null;
						$uri = null;
						$row = array();

						for ($i = 0; $i < sizeof($uriColumns); $i += 1)
							$row[$uriColumns[$i]] = $_POST[$uriColumns[$i]."-".$ID];

						if ($row['aq_initial_lowercase'] === NULL)
						{
							$row['aq_initial_lowercase'] = '0';
						}

						if ($row['aq_search_fragments'] === NULL)
						{
							$row['aq_search_fragments'] = '0';
						}

						$name = $_POST['name-'.$ID];
						$uri = $_POST['uri-'.$ID];

						try {
							$this->confirmBasicRow($row, $ID, $name, $uri);
							aqDbReplaceRow(array($uriIDColumn=>$ID), $row);
						} catch (Exception $e) {
							$wgOut->addHTML('<span style="color:red;">' . $e->getMessage() . '</span>');
						}
					}
					else if ($submitkey === "add_row_query")
					{
						// Create an associative array to pass
						$values = array();
			                        $row = array();
						for ($i = 0; $i < sizeof($queryColumns); $i += 1)
						{
							$values[$i] = $_POST["new_query_"."$queryColumns[$i]"];
							$row[$queryColumns[$i]] = $values[$i];
						}

						// Checkboxes need a little modification
                                                if ($row['aq_query_uri_param'] === NULL)
                                                {
                                                        $row['aq_query_uri_param'] = '0';
                                                }

						try {
							$this->confirmAdvancedRow($row, false);
							aqDbInsertRow($row, 'query');
						} catch (Exception $e) {
							$wgOut->addHTML('<span style="color:red;">' . $e->getMessage() . '</span>');
						}
					}
					else if (strpos($submitkey,"delete_query-") === 0)
					{
						$ID = substr($submitkey, 13);
						$name = $_POST['query_name-'.$ID];
						try {

							// Check if NS has pages, if so, add to oldns table
							$pages = aqNSHasPages($ID);
							if (empty($pages) === false)
							{
								aqDbInsertRow(array('aq_wiki_namespace_id'=>$ID, 'aq_wiki_namespace'=>$name), 'oldns');
							}

							aqDbDeleteRow(array($queryIDColumn=>$ID), 'query');
						} catch (Exception $e) {
							$wgOut->addHTML('<span style="color:red;">' . $e->getMessage() . '</span>');
                                                }
					}
					else if (strpos($submitkey,"modify_query-") === 0)
					{
						$ID = substr($submitkey, 13);
						$name = null;
						$row = array();

						for ($i = 0; $i < sizeof($queryColumns); $i += 1)
							$row[$queryColumns[$i]] = $_POST[$queryColumns[$i]."-".$ID];

						$name = $_POST["query_name-".$ID];

                                                if ($row['aq_query_uri_param'] === NULL)
                                                {
                                                        $row['aq_query_uri_param'] = '0';
                                                }
						
						try {
							$this->confirmAdvancedRow($row, $ID, $name);
							aqDbReplaceRow(array($queryIDColumn=>$ID), $row, 'query');
						} catch (Exception $e) {
							$wgOut->addHTML('<span style="color:red;">' . $e->getMessage() . '</span>');
						}
					}
				}


				//User is allowed to access the special page. Show the configuration table output.
				$this->setHeaders();
				$wgOut->addScript('<style type="text/css">input.aq,textarea {width:90%;} td {text-align:center} th {vertical-align:bottom}</style>');
                                $wgOut->addHTML('<form name="aquaform" action="'.$this->action.'" method="post">');
				$wgOut->addHTML('<p>Columns marked with a * must be unique!</p>');
                                $wgOut->addHTML('<table>');
                                $wgOut->addHTML('<tr>');
                                $wgOut->addHTML('<th>Wiki Namespace ID*</th>');
                                $wgOut->addHTML('<th>Wiki Namespace*</th>');
                                $wgOut->addHTML('<th>Datastore URI prefix*</th>');
                                $wgOut->addHTML('<th>Datastore Type</th>');
                                $wgOut->addHTML('<th>Datastore Name</th>');
                                $wgOut->addHTML('<th>Datastore&nbsp;Location</th>');
                                $wgOut->addHTML('<th>Datastore Certificate Path</th>');
                                $wgOut->addHTML('<th>Certificate Password</th>');
				$wgOut->addHTML('<th>Title Initial Lowercase</th>');
				$wgOut->addHTML('<th>Search for Fragments</th>');
                                $wgOut->addHTML('<th></th>');
                                $wgOut->addHTML('<th></th>');
                                $wgOut->addHTML('</tr>');

                                $rows = aqDbTableGetAll();
                                foreach($rows as $row)
                                {
					$rowID = $row[$uriIDColumn];
        			        $wgOut->addHTML("<tr>");
					
					$value = htmlspecialchars($row['aq_wiki_namespace_id'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_wiki_namespace_id-$rowID' value='$value'></td>");

					$value = htmlspecialchars($row['aq_wiki_namespace'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_wiki_namespace-$rowID' value='$value'></td>");

                                        $value = htmlspecialchars($row['aq_source_uri'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_source_uri-$rowID' value='$value'></td>");

					// A dropdown for items with specific answers
                                        $value = htmlspecialchars($row['aq_source_type'],ENT_QUOTES);

					$wgOut->addHTML("<td><select name='aq_source_type-$rowID'>");
					$wgOut->addHTML("<option ".(($value === 'BB') ? "selected='selected'" : "")." value='BB'>BB</option>");
					$wgOut->addHTML("<option ".(($value === 'BB28') ? "selected='selected'" : "")." value='BB28'>BB28</option>");
	                                $wgOut->addHTML("<option ".(($value === 'BB30') ? "selected='selected'" : "")." value='BB30'>BB30</option>");
	                                $wgOut->addHTML("<option ".(($value === 'Test') ? "selected='selected'" : "")." value='Test'>Test</option>");
	                                $wgOut->addHTML("<option ".(($value === 'Embedded') ? "selected='selected'" : "")." value='Embedded'>Embedded</option>");
	                                $wgOut->addHTML("<option ".(($value === 'Memcached') ? "selected='selected'" : "")." value='Memcached'>Memcached</option>");
	                                $wgOut->addHTML("<option ".(($value === 'Arc2') ? "selected='selected'" : "")." value='Arc2'>Arc2</option>");
					$wgOut->addHTML("</select>");
					$wgOut->addHTML("</td>");

                                        $value = htmlspecialchars($row['aq_source_name'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_source_name-$rowID' value='$value'></td>");

                                        $value = htmlspecialchars($row['aq_source_location'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_source_location-$rowID' value='$value'></td>");

                                        $value = htmlspecialchars($row['aq_source_cert_path'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_source_cert_path-$rowID' value='$value'></td>");

                                        $value = htmlspecialchars($row['aq_source_cert_pass'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_source_cert_pass-$rowID' value='$value'></td>");

					// Checkboxes for the boolean values
					$value = htmlspecialchars($row['aq_initial_lowercase'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='checkbox' name='aq_initial_lowercase-$rowID' value='1' ".(($value === '1') ? "checked='yes'" : "")."/></td>");

					$value = htmlspecialchars($row['aq_search_fragments'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='checkbox' name='aq_search_fragments-$rowID' value='1' ".(($value === '1') ? "checked='yes'" : "")."/></td>");

                                	$wgOut->addHTML("<td><input class='aq' type='submit' style='width:inherit' name='modify_uri-$rowID' value='Modify'/></td>");
                                	$wgOut->addHTML("<td><input class='aq' type='submit' style='width:inherit' name='delete_uri-$rowID' value='Delete'/></td>");
                                	$wgOut->addHTML("<td><input class='aq' type='hidden' name='name-$rowID' value='{$row['aq_wiki_namespace']}'/></td>");
                                	$wgOut->addHTML("<td><input class='aq' type='hidden' name='uri-$rowID' value='{$row['aq_source_uri']}'/></td></tr>");
				}
				
                                $wgOut->addHTML("<tr>");

                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_uri_aq_wiki_namespace_id'></td>");
                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_uri_aq_wiki_namespace'></td>");
                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_uri_aq_source_uri'></td>");

                                // A dropdown for items with specific answers
                                $wgOut->addHTML("<td><select name='new_uri_aq_source_type'>");
                                $wgOut->addHTML("<option value='BB'>BB</option>");
                                $wgOut->addHTML("<option value='BB28'>BB28</option>");
                                $wgOut->addHTML("<option value='BB30'>BB30</option>");
                                $wgOut->addHTML("<option value='Test'>Test</option>");
                                $wgOut->addHTML("<option value='Embedded'>Embedded</option>");
                                $wgOut->addHTML("<option value='Memcached'>Memcached</option>");
	                        $wgOut->addHTML("<option value='Arc2'>Arc2</option>");
                                $wgOut->addHTML("</select>");

                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_uri_aq_source_name'></td>");
                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_uri_aq_source_location'></td>");
                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_uri_aq_source_cert_path'></td>");
                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_uri_aq_source_cert_pass'></td>");

                                // Checkboxes for the boolean values
                                $wgOut->addHTML("<td><input class='aq' type='checkbox' name='new_uri_aq_initial_lowercase' value='1'/></td>");
                                $wgOut->addHTML("<td><input class='aq' type='checkbox' name='new_uri_aq_search_fragments' value='1'/></td>");

                                $wgOut->addHTML("<td><input class='aq' type='submit' style='width:inherit' name='add_row_uri' value='Add'/></td></tr>");
                                $wgOut->addHTML('</table>');
				$wgOut->addHTML('<br/><br/><br/>');


				// ***********************************************************
				//Show the configuration table output for the SPARQL queries.
				// ***********************************************************
                                $wgOut->addHTML('<table>');
                                $wgOut->addHTML('<tr>');
                                $wgOut->addHTML('<th>New Namespace ID*</th>');
                                $wgOut->addHTML('<th colspan="2">Associated Namespace<br/>& Query Tag</th>');
                                $wgOut->addHTML('<th>Query Type</th>');
                                $wgOut->addHTML('<th>Datastore Name</th>');
                                $wgOut->addHTML('<th>Algorithm Name</th>');
                                $wgOut->addHTML('<th>Use URI as Query Parameter</th>');
                                $wgOut->addHTML('<th>Query Text</th>');
                                $wgOut->addHTML('<th></th>');
                                $wgOut->addHTML('<th></th>');
                                $wgOut->addHTML('</tr>');

                                $rows = aqDbTableGetAll('query');
                                foreach($rows as $row)
                                {
					$rowID = $row[$uriIDColumn];
        			        $wgOut->addHTML("<tr>");


					$value = htmlspecialchars($row['aq_wiki_namespace_id'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_wiki_namespace_id-$rowID' value='$value'/></td>");

					$value = htmlspecialchars($row['aq_wiki_parent_namespace'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_wiki_parent_namespace-$rowID' value='$value'/></td>");

					$value = htmlspecialchars($row['aq_wiki_namespace_tag'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_wiki_namespace_tag-$rowID' value='$value'/></td>");

					$value = htmlspecialchars($row['aq_query_type'],ENT_QUOTES);
					$wgOut->addHTML("<td><select name='aq_query_type-$rowID'>");
					$wgOut->addHTML("<option ".(($value === 'ExecuteAlgorithm') ? "selected='selected'" : "")." value='ExecuteAlgorithm'>ExecuteAlgorithm</option>");
					$wgOut->addHTML("</select></td>");

					$value = htmlspecialchars($row['aq_datasource'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='text' name='aq_datasource-$rowID' value='$value'/></td>");

					$value = htmlspecialchars($row['aq_algorithm'],ENT_QUOTES);
					$wgOut->addHTML("<td><select name='aq_algorithm-$rowID'>");
					$wgOut->addHTML("<option ".(($value === 'LuceneKeyword') ? "selected='selected'" : "")." value='LuceneKeyword'>LuceneKeyword</option>");
					$wgOut->addHTML("<option ".(($value === 'SparqlConstructQuery') ? "selected='selected'" : "")." value='SparqlConstructQuery'>SparqlConstructQuery</option>");
					$wgOut->addHTML("<option ".(($value === 'SparqlSelectQuery') ? "selected='selected'" : "")." value='SparqlSelectQuery'>SparqlSelectQuery</option>");
					$wgOut->addHTML("<option ".(($value === 'SparqlDescribeQuery') ? "selected='selected'" : "")." value='SparqlDescribeQuery'>SparqlDescribeQuery</option>");
					$wgOut->addHTML("</select></td>");

					$value = htmlspecialchars($row['aq_query_uri_param'],ENT_QUOTES);
					$wgOut->addHTML("<td><input class='aq' type='checkbox' name='aq_query_uri_param-$rowID' value='1' ".(($value === '1') ? "checked='yes'" : "")."/></td>");

					$value = htmlspecialchars($row['aq_query'],ENT_QUOTES);
					$wgOut->addHTML("<td style='width:25%'><textarea rows='2' name='aq_query-$rowID'>$value</textarea></td>");

                                	$wgOut->addHTML("<td><input class='aq' type='submit' style='width:inherit' name='modify_query-$rowID' value='Modify'/></td>");
                                	$wgOut->addHTML("<td><input class='aq' type='submit' style='width:inherit' name='delete_query-$rowID' value='Delete'/></td>");
                                	$wgOut->addHTML("<td><input class='aq' type='hidden' name='query_name-$rowID' value='{$row['aq_wiki_parent_namespace']}_{$row['aq_wiki_namespace_tag']}'/></td></tr>");
				}
				
                                $wgOut->addHTML('<tr>');
                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_query_aq_wiki_namespace_id'/></td>");
                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_query_aq_wiki_parent_namespace'/></td>");
                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_query_aq_wiki_namespace_tag'/></td>");

                                $wgOut->addHTML("<td><select name='new_query_aq_query_type'>");
                                $wgOut->addHTML("<option value='ExecuteAlgorithm'>ExecuteAlgorithm</option>");
                                $wgOut->addHTML("</select></td>");

                                $wgOut->addHTML("<td><input class='aq' type='text' name='new_query_aq_datasource'/></td>");

                                $wgOut->addHTML("<td><select name='new_query_aq_algorithm'>");
                                $wgOut->addHTML("<option value='LuceneKeyword'>LuceneKeyword</option>");
                                $wgOut->addHTML("<option value='SparqlConstructQuery'>SparqlConstructQuery</option>");
                                $wgOut->addHTML("<option value='SparqlSelectQuery'>SparqlSelectQuery</option>");
                                $wgOut->addHTML("<option value='SparqlDescribeQuery'>SparqlDescribeQuery</option>");
                                $wgOut->addHTML("</select></td>");

                                $wgOut->addHTML("<td><input class='aq' type='checkbox' name='new_query_aq_query_uri_param' value='1'/></td>");
                                $wgOut->addHTML("<td style='width:25%'><textarea rows='2' name='new_query_aq_query'></textarea></td>");

                                $wgOut->addHTML("<td><input class='aq' type='submit' style='width:inherit' name='add_row_query' value='Add'/></td></tr>");
                                $wgOut->addHTML('</table>');

				$wgOut->addHTML('<br/><br/><br/>');


                                $rows = aqDbTableGetAll('oldns');
				if (empty($rows) === false)
				{
                                	$wgOut->addHTML('<table>');
                                	$wgOut->addHTML('<tr>');
                                	$wgOut->addHTML('<th style="padding-right:10px">Old Namespace IDs</th>');
                                	$wgOut->addHTML('<th>Old Namespace Names</th>');
                                	$wgOut->addHTML('</tr>');
	
	                                foreach($rows as $row)
	                                {
						$rowID = $row[0];
	        			        $wgOut->addHTML("<tr>");
						$wgOut->addHTML("<td style='border:1px solid black'><span name='oldns_id-$rowID'>".htmlspecialchars($row[0],ENT_QUOTES)."</span></td>");
						$wgOut->addHTML("<td style='border:1px solid black'><span name='oldns_name-$rowID'>".htmlspecialchars($row[1],ENT_QUOTES)."</span></td>");
					}
	                                $wgOut->addHTML('</table>');
				}

                                $wgOut->addHTML('</form>');
			}
			else
			{
				//User is not allowed to see this page
				$this->displayRestrictionError();
			}
		}
	}
?>
