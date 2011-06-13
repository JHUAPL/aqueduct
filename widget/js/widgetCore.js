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
// Keep track of our cistern widget instances with this
___CisternWidgetInstances = []
___CisternWidgetPreviousQueries = []
// Hook window destroyed event!
jQuery(window).unload( function(){
	for (widget in ___CisternWidgetInstances){
		if (___CisternWidgetInstances[widget] != null)
			___CisternWidgetInstances[widget].Destroy()
	}
	___CisternWidgetInstances = null
})

// USE:
// create like: var widget = new CisternWidget(divName, skinName, cisternUrl, initialQuery, fullQuery);

// Constructor
function CisternWidget(divName, skinName, cisternUrl, initialQuery, fullQuery, layout, gridNotifier) {
	this.divName = divName
	this.skinName = skinName
	this.cisternUrl = cisternUrl
	this.initialQuery = initialQuery
	this.fullQuery = fullQuery
	this.layout = layout
	this.loaded = false
	this.gridNotifier = gridNotifier;

	___CisternWidgetInstances.push(this);
	jQuery(document).ready(this.OnLoadAll);
}

// initialize widget skin
CisternWidget.prototype.OnLoad = function(){

	if (!this.loaded){
		// get reference to skin class
		var useInitialQuery;
		if (this.fullQuery != "")
			useInitialQuery = false
		else
			useInitialQuery = true	
		var CisternSkin = eval(this.skinName)
		
		// construct skin class
		this.skin = new CisternSkin(this.divName, this, useInitialQuery)
		this.skin.QueryStarted()
		if (this.fullQuery != "")
			this.QueryCistern(false)
		else
			this.QueryCistern(true)
	}
	this.loaded = true
}

CisternWidget.prototype.OnLoadAll = function(){
	for (widget in ___CisternWidgetInstances){
		___CisternWidgetInstances[widget].OnLoad()
	}
}

CisternWidget.prototype.Destroy = function(){
	
	if (this.skin){
		this.skin.Destroy()
	}

	this.skin = null

	// remove one's self from ___CisternWidgetInstances
	for (widget in ___CisternWidgetInstances){
		if (this == ___CisternWidgetInstances[widget]){
			___CisternWidgetInstances[widget] = null
		}
	}
}


CisternWidget.prototype.RefreshInitialQuery = function(){
	this.QueryCistern(false)
}

CisternWidget.prototype.RefreshFullQuery = function(){
	this.QueryCistern(true)
}

CisternWidget.prototype.QueryCistern = function(useInitialQuery){
	var whichquery = useInitialQuery ? this.initialQuery : this.fullQuery;
	if (___CisternWidgetPreviousQueries[whichquery] == null)
	{
		var whichobject = this;
		___CisternWidgetPreviousQueries[whichquery] = [];
		//Do the query
		this.queryCisternTime = new Date();
		this.currentlyLoadingQuery = whichquery;
		jQuery.ajax({
			cache: false,
			type: "GET",
			url: this.cisternUrl,
			dataType: 'json',
			processData: true,
			data: {action:"aqueduct",format:"json",subject:whichquery},
			error: function(response){whichobject.OnQueryCisternError(response)},
			complete: function(response){whichobject.OnQueryCisternComplete(response)},
			success: function(response){whichobject.OnQueryCisternSuccess(response)}
		});
	}
	else
	{
		//Wait for someone else to do the query
		___CisternWidgetPreviousQueries[whichquery].push(this);
	}
}


CisternWidget.prototype.ProcessJSONRdf = function(useInitial, responseRDF)
{
	//
	//This class extracts the records from the JSON- RDF
	//
	//Usage:
	//Call newEntity() before feeding it the RDF associated with each entity in the result set
	//Call statement() with each RDF triple. If statement() returns false, save the triple to a reprocessing list (all triples with the same subject may also be saved).
	//After processing everything you can, keep reprocessing the reprocessing list until it's empty
	//Then call getStructure() to get the final structure
	
	
	function RdfProcessor(responseRDF)
	{
		this.structure = new Object();
		this.structure['fullRDF'] = responseRDF;
		this.structure['fields'] = new Array();
		this.structure['records'] = new Array();
		
		//Cache which subjects and fields we've seen before
		this.knownfields = new Object();
		this.knownsubjects = new Object();
		this.subjectcount = 0;
	}
	
	RdfProcessor.prototype.newEntity = function()
	{
		//Remember if the entity was associated with multiple subjects (not yet supported)
		this.readSubject = false;
		
		//This object associates bnode subjects (used for tree-like properties) with their underlying URI subjects
		this.bnodemappings = new Object();		
		
		//This object associates bnode subjects (used for tree-like properties) with a space-delimited list of predicates that must be traversed from the subject
		this.bnodepaths = new Object();		
	}
	
	RdfProcessor.prototype.statement = function(subj,subjIsBnode,obj,objType,pred,role)
	{
		if (!subjIsBnode)
		{
			//This is a reified statement with a URI subject -- so we  may have to create a row for this subject in the data structure
			var predpath = pred;
			if (this.knownsubjects[subj] == null)
			{
				if (this.readSubject)
				{
					throw "Multiple subjects for an entity were detected. This is not yet supported.";
				}
				this.readSubject = true;
				//Grow the records array, and remember the array index for this subject
				this.knownsubjects[subj] = this.subjectcount;
				this.structure['records'][this.subjectcount] = new Object();
				this.structure['records'][this.subjectcount]["entityNames"] = new Array(subj);
				this.structure['records'][this.subjectcount]["fieldValues"] = new Object();
				this.subjectcount++;
			}
		}
		else 
		{
			if (this.bnodemappings[subj] != null)
			{
				var predpath = this.bnodepaths[subj] + " " + pred;
				subj = this.bnodemappings[subj];
			}
			else
			{
				//Don't know what subject this bnode is associated with yet
				//So can't do anything
				return false;
			}
		}
		if (objType == "bnode")
		{
			//This defines a complex tree-like property; instead of setting the property, remember the subject that corresponds with our bnode...
			this.bnodemappings[obj] = subj;
			this.bnodepaths[obj] = predpath;
		}
		else 
		{
			//This RDF triple represents data that we can actually send off to the widget.
			obj = new String(obj);
			obj.role = role;
			obj.objType = objType;
			if (this.structure['records'][this.knownsubjects[subj]]["fieldValues"][predpath] == null)
			{
				this.structure['records'][this.knownsubjects[subj]]["fieldValues"][predpath] = new Array();
			}
				this.structure['records'][this.knownsubjects[subj]]["fieldValues"][predpath].push(obj);
			//Build up the fields structure while building the records structure
			if (this.knownfields[predpath] == null)
			{
				this.knownfields[predpath] = true;
				var fieldNameParts = pred.split(/[/:#]/);
				var f = {'predicate' : pred, 'fieldName' : fieldNameParts[fieldNameParts.length-1], 'fullName' : predpath, 'entityNumber' : 0};
				this.structure['fields'].push(f);
			}
		}
		return true;
	}
	
	RdfProcessor.prototype.getStructure = function()
	{
		var sortFieldsFunction = function(a,b)
		{
			return a.fullName.localeCompare(b.fullName);
		}
	
		//Roughly sort the fields by path (this could be improved later on)
		this.structure['fields'].sort(sortFieldsFunction);
		
		return this.structure;
	}

	
	
	var processor = new RdfProcessor(responseRDF);
	var foundReifiedStatement = false;
	
	for (whichtree in responseRDF)
	{
		processor.newEntity();
		var rdftree = responseRDF[whichtree];
		var currentNodeList = new Array();
		var nextNodeList = new Array();
		//Initialize the list of nodes that remain to be processed
		for (blanknodeid in rdftree)
		{
			currentNodeList.push(blanknodeid);
		}
		
		while (currentNodeList.length > 0)
		{
			var origlength = currentNodeList.length;
			//Fill in the structure from the reified RDF data
			for (blanknodeid in currentNodeList)
			{
				var blanknode = rdftree[currentNodeList[blanknodeid]];
				if (blanknode["http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"]!=null
					&& blanknode["http://www.w3.org/1999/02/22-rdf-syntax-ns#object"]!=null
					&& blanknode["http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"]!=null				
					)
				{
					foundReifiedStatement = true;
					var subj = blanknode["http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"][0]["value"];
					var subjtype = blanknode["http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"][0]["type"];
					var obj = blanknode["http://www.w3.org/1999/02/22-rdf-syntax-ns#object"][0]["value"];
					var objType = blanknode["http://www.w3.org/1999/02/22-rdf-syntax-ns#object"][0]["type"];
					var pred = blanknode["http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"][0]["value"];
					if (blanknode["urn:blackbook:role"]==null)
					{
						var role = "UNKNOWN";
					}
					else
					{
						var role = blanknode["urn:blackbook:role"][0]["value"];
					}
					var success = true;
					if (subjtype == "uri")
					{
						success = processor.statement(subj,false,obj,objType,pred,role);
					}			
					else if (subjtype == "bnode")
					{
						success = processor.statement(subj,true,obj,objType,pred,role);
					}
					if (!success)
					{
						nextNodeList.push(currentNodeList[blanknodeid]);
					}
				}			
			}
			currentNodeList = nextNodeList;
			nextNodeList = new Array();
			if (currentNodeList.length>0 && origlength == currentNodeList.length)
			{
				throw "The reified RDF processing entered an infinite loop.";
			}
		}
	}
	
	if (!foundReifiedStatement)
	{
		//This may be non-reified RDF. Try the processing again
		for (whichtree in responseRDF)
		{
			processor.newEntity();
			var rdftree = responseRDF[whichtree];
			var currentSubjList = new Array();
			var nextSubjList = new Array();
			var bnodes = new Object();
			for (subjectid in rdftree)
			{
				//Make a list of subjects that are really bnodes and not uris
				for (predicateid in rdftree[subjectid])
					for (objectindex in rdftree[subjectid][predicateid])
						{
							statement = rdftree[subjectid][predicateid][objectindex];
							if (statement['type'] == 'bnode')
								bnodes[statement['value']] = true;
						}
				//Initialize the list of subjects that remain to be processed
				currentSubjList.push(subjectid);
			}
			
			while (currentSubjList.length > 0)
			{
				var origlength = currentSubjList.length;
				
				for (subjectindex in currentSubjList)
				{
					var subj = currentSubjList[subjectindex]
					var redoSubject = false;
					for (predicateid in rdftree[subj])
					{
						for (objectindex in rdftree[subj][predicateid])
						{
							statement = rdftree[subj][predicateid][objectindex];
							if (!processor.statement(subj,bnodes[subj]?true:false,statement['value'],statement['type'],predicateid,'UNKNOWN'))
							{
								redoSubject = true;
								break;
							}
						}
						if (redoSubject) break;
					}
					if (redoSubject)
					{
						nextSubjList.push(subj);
					}
				}
			
				currentSubjList = nextSubjList;
				nextSubjList = new Array();
				if (currentSubjList.length>0 && origlength == currentSubjList.length)
				{
					throw "The RDF processing entered an infinite loop.";
				}
			}
		}
	}
	
	var structure = processor.getStructure();
	
	return structure;
}

CisternWidget.prototype.OnQueryCisternError = function(response){
	//Notify all consumers of this query about the error
	var otherQueries = ___CisternWidgetPreviousQueries[this.currentlyLoadingQuery];
	___CisternWidgetPreviousQueries[this.currentlyLoadingQuery] = null;
	this.currentlyLoadingQuery = null;
	this.skin.QueryError(response.status);
	for (query in otherQueries)
	{
		otherQueries[query].skin.QueryError(response.status);
	}
}

CisternWidget.prototype.OnQueryCisternComplete = function(response){

}

CisternWidget.prototype.OnQueryCisternSuccess = function(response){
	var otherQueries = ___CisternWidgetPreviousQueries[this.currentlyLoadingQuery];
	___CisternWidgetPreviousQueries[this.currentlyLoadingQuery] = null;
	this.currentlyLoadingQuery = null;
	
	if (response['error'] && response['error']['code'] && response['error']['info'])
	{
		//There was an error in the JSON RDF, so notify all the consumers of it
		this.skin.QueryError(response['error']['code'] + " " + response['error']['info']);
		for (query in otherQueries)
		{
			otherQueries[query].skin.QueryError(response['error']['code'] + " " + response['error']['info']);
		}
	}
	else
	{
		//Profile and process the JSON RDF
		var loadCompleteTime = (new Date()).getTime();
		var processedRDF = this.ProcessJSONRdf(this.isInitial,response);
		var processEndTime = (new Date()).getTime();
		var profileoutput = null;
		if (response['profiling'])
		{
			var total = 0;
			profileoutput = 'Server Profiling:\n';
			for (service in response['profiling'])
			{
				total += response['profiling'][service];
				profileoutput+= service + ": " + response['profiling'][service] + '\n';
			}
			profileoutput+= "Call overhead: " + ((loadCompleteTime - this.queryCisternTime.getTime())/1000-total);
			profileoutput+= "\nJSON processing: " + ((processEndTime - loadCompleteTime)/1000);
		}
		//Notify all consumers of the successful load
		this.QuerySuccess(processedRDF, profileoutput);
		for (query in otherQueries)
		{
			otherQueries[query].QuerySuccess(processedRDF, response['profiling']?"":null);
		}
	}
}

CisternWidget.prototype.QuerySuccess = function(processedRDF, profile) {
	//There was a successful JSON-RDF load, so display it 
	this.widgetProfilingResult = "\n\nWidget profiling results:";
	this.currentlyProfilingDesc = "Widget unknown";
	this.currentlyProfiling = new Date();
	this.skin.QuerySuccess(this.isInitial, processedRDF);
	this.ProfileWidget("Done");
	if (profile!=null)
	{
		alert(profile + this.widgetProfilingResult);			
	}
	if (this.gridNotifier)
	{
		this.gridNotifier(this);
	}
}

CisternWidget.prototype.ProfileWidget = function(description) {
	var endtime = (new Date()).getTime();
	this.widgetProfilingResult += '\n' + this.currentlyProfilingDesc + ": " + ((endtime - this.currentlyProfiling.getTime()))+" ms";
	this.currentlyProfilingDesc = description;
	this.currentlyProfiling = new Date();
}

function uriToTitle(uri)
{
	var legalcharpattern = new RegExp(/[\-;/?:@&=+$.!~*(%),\\'#\w]/g);
	var legalstringpattern = new RegExp(/^[\-;/?:@&=+$.!~*(%),\\'#\w]+$/g);

	//1. Check for illegal characters
	if (uri.match(legalstringpattern) == null)
	{
		throw "Illegal URI:" + uri;
	}

	//2. Detect which URI prefix is being used to select the configuration row, and remove the URI prefix
	aqTransTable = eval(aqTransTable);
	var matchingrow = null;
	var row;
	for (row = 0; row < aqTransTable.length; row++)
	{
		//Use a configuration row if the URI prefix matches and another configuration row with a better (longer) prefix was not found
		if (uri.indexOf(aqTransTable[row]["aq_source_uri"]) == 0)
		{
			if (matchingrow == null || matchingrow["aq_source_uri"].length < aqTransTable[row]["aq_source_uri"].length)
			{
				matchingrow = aqTransTable[row];
			}
		}
	}
	if (matchingrow)
	{
		uri = uri.substr(matchingrow["aq_source_uri"].length);
	}

	//3. Convert the sequences of octets into unicode characters.
	var decodeduri = "";
	var currentchar = 0;
	while (currentchar < uri.length)
	{
		var c = uri.charAt(currentchar);
		if (c == "%")
		{
			if (currentchar + 3 > uri.length)
			{
				throw "Malformed escape sequence in URI: " + uri;
			}
			var currentseqhex = uri.substr(currentchar+1,2);
			var currentseqdec = parseInt(currentseqhex,16);
			var currentseqbin = String.fromCharCode(currentseqdec);
			if (currentseqdec < 128)
			{
				//This is a "non-encoded" character (code point <128)
				var encodeme = false;
				if (legalcharpattern.test(currentseqbin) != false)
				{
					//URL-legal character was unnecessarily encoded. Output this in encoded form to preserve canonicalization
					encodeme = true;
				}
				else if (currentseqdec < 32)
				{
					//Keep control characters encoded
					encodeme = true;
				}
				else if ("<>[]|{}\`^% ".indexOf(currentseqbin) != -1)
				{
					//Keep a character that we will not want to use in a wiki title encoded
					encodeme = true;
				}
				if (encodeme)
				{
					decodeduri = decodeduri + "%-" + currentseqhex;
				}
				else
				{
					decodeduri = decodeduri + currentseqbin;
				}					
				currentchar = currentchar + 3;
			}
			else
			{
				//This is part of a UTF-8 encoded sequence
				var UTF8 = UTF8Decode(uri, currentchar);
				decodeduri = decodeduri + UTF8.character;
				currentchar = currentchar + UTF8.length;
			}
		}
		else
		{
			decodeduri = decodeduri + c;
			currentchar++;
		}
	}

	//4. The hash mark # could have been present in an unescaped form the URI, which would cause it to remain in the title at this point. 
	//This character is illegal in the wiki. Convert it to a backslash \. If the hash mark was at the beginning of the title, convert it to a double backslash instead
	
	//5. Colons can be confused for namespace prefixes, so convert them to a backtick `
	decodeduri = decodeduri.replace(/#/g,"\\");
	decodeduri = decodeduri.replace(/:/g,"`");
	if (decodeduri.charAt(0) == '\\')
	{
		decodeduri = '\\' + decodeduri;
	}

	//6. Some characters will be conditionally escaped if they will cause problems.
	//Any period not adjacent with a character other than another period or the forward slash is escaped to ^
	//In a sequence of underscores, insert a ^ between all underscores, because Mediawiki will collapse sequences of underscores. Example: ___ turns into _caret_caret_ (caret means ^ )
	//Use the new length, as the %- encoding above can lengthen the uri past its original bounds.
	//Do the same in a sequence of tildes.
	var printedunderscore = false;
	var printedtilde = false;
	var periodokay = false;
	var escapeduri = "";
	for (currentchar = 0; currentchar < decodeduri.length; currentchar++)
	{
		c = decodeduri.charAt(currentchar);
		if (c == "_")
		{
			if (printedunderscore)
			{
				escapeduri = escapeduri + "^";
			}
			escapeduri = escapeduri + "_";
			printedunderscore = true;
			printedtilde = false;
			periodokay = true;
		}
		else if (c == "~")
		{
			if (printedtilde)
			{
				escapeduri = escapeduri + "^";
			}
			escapeduri = escapeduri + "~";
			printedunderscore = false;
			printedtilde = true;
			periodokay = true;
		}
		else if (c == ".")
		{
			if (periodokay)
			{
				//The period is "armored" by the character on the left, so just print it
				escapeduri = escapeduri + ".";
			}
			else if (currentchar + 1 < decodeduri.length)
			{
				n = decodeduri.charAt(currentchar+1);
				if (n != "." && n != "/")
				{
					//Period armored by the character on the right
					escapeduri = escapeduri + ".";
				}
				//Unarmored period
				escapeduri = escapeduri + "^.";
			}
			else
			{
				//Unarmored period at the end of the string
				escapeduri = escapeduri + "^.";
			}
			printedunderscore = false;
			printedtilde = false;
			periodokay = false;
		}
		else
		{
			printedunderscore = false;
			printedtilde = false;
			//Not an underscore or period or tilde, so we don't handle it in this loop.
			escapeduri = escapeduri + c;
			if (c != "/")
			{
				periodokay = true;
			}
		}
	}
	//If the URI contains any semicolons ;, escape all ampersands & to ^a
	if (escapeduri.indexOf(";") != -1)
	{
		escapeduri = escapeduri.replace(/&/g,"^a");
	}
	//If the string ends with an underscore at this point, put a caret at the end so Mediawiki does not get rid of the underscore. 
	if (escapeduri.charAt(escapeduri.length - 1) == "_")
	{
		escapeduri = escapeduri + "^";
	}

	//7.  Logic for characters that Mediawiki will modify only at the beginning of a string
	prependbackslash = false;
	if (escapeduri.charAt(0).toUpperCase() == escapeduri.charAt(0))
	{
		if (matchingrow && matchingrow["aq_initial_lowercase"] == "1")
		{
			prependbackslash = true;
		}
	}
	else if (escapeduri.charAt(0).toLowerCase() == escapeduri.charAt(0))
	{
		if (!matchingrow || matchingrow["aq_initial_lowercase"] == "0")
		{
			prependbackslash = true;
		}
	}
	else if (escapeduri.charAt(0) == "_")
	{
		prependbackslash = true;
	}

	escapeduri = escapeduri.charAt(0).toUpperCase() + escapeduri.substr(1);

	if (prependbackslash)
	{
		escapeduri = "\\" + escapeduri;
	}

	//8. Prepend the namespace
	if (matchingrow)
	{
		if (matchingrow["aq_wiki_namespace_id"] > 0)
		{
			escapeduri = matchingrow["aq_wiki_namespace"] + ":" + escapeduri;
		}
	}
	else
	{
		escapeduri = "Unknown:" + escapeduri;
	}
	return escapeduri;
}

// Decode the UTF8 sequence at the given position in the uri.
function UTF8Decode(uri, position)
{
	var decodeduri = "";
	var buffer = "";

	if (uri.charAt(position) != "%")
	{
		throw "No UTF-8 sequence at position: " + position + " in uri: " + uri;
	}

	// Parse the XX in %XX.
	currentseqhex = uri.substr(position+1,2);
	currentseqdec = parseInt(currentseqhex,16);
	currentseqbin = String.fromCharCode(currentseqdec);

	while (currentseqdec >= 128)
	{
		// Buffer the current sequence.
		buffer = buffer + "%" + currentseqhex;

		// Collect the next %XX sequence.
		position = position + 3;
		if (position > uri.length)
		{
			if (buffer.length < 6)
			{
				throw "Malformed UTF-8 sequence in URI (Unexpected End of URI): " + uri;
			}
			else
			{
				break;	
			}
		}

		// Check for the end of the sequence.
		c = uri.charAt(position);
		if (c != "%")
		{ 
			if (buffer.length < 6)
			{
				throw "Malformed UTF-8 sequence in URI (Incomplete): " + uri;
			}
			else 
			{
				break;
			}
		}
					
		// Parse the XX in %XX.
		currentseqhex = uri.substr(position+1,2);
		currentseqdec = parseInt(currentseqhex,16);
		currentseqbin = String.fromCharCode(currentseqdec);
		
		// Check %XX sequence for > 127.
		if (currentseqdec > 127)
		{
			// if so, continue loop...
			continue;
		}
		else
		{
			// This is not part of this UTF-8 character, but a separate encoded character!
			position = position - 3;
			if (buffer.length < 6)
			{
				throw "Malformed UTF-8 sequence in URI (Incomplete, followed by ASCII): " + uri;
			}
			else 
			{
				break;
			}
		}
	}
	// else, decode the buffer to get the character and add it to the uri.
	var returner = new Object;
	returner.character = decodeURI(buffer);
	returner.length = buffer.length;
	return returner;
}
