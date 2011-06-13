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
// Constructor
function LayoutSkin(div, baseWidget, useInitialQuery){
	this.data = ""

	this.div = jQuery("#"+div)
	this.baseWidget = baseWidget
	this.useInitialQuery = useInitialQuery

	this.loadingDiv = jQuery('<div><img src="'+wgScriptPath+'/extensions/AqueductExtension/widget/js/ajax-loader.gif" alt="Loading..." /></div>').css("display", "none")
	this.loadingDiv.css("position", "relative").css("float", "left").css("z-index", 1000)
	this.div.append(this.loadingDiv)

	this.innerDiv = jQuery('<div id="' + baseWidget.divName + '_innerdiv"></div>')

	this.div.append(this.innerDiv)

	this.inQuery = false
}

LayoutSkin.prototype.Destroy = function(){
	this.div = null
	this.baseWidget = null
	this.loadingDiv = null
	this.innerDiv = null
	this.selectbox = null
}

LayoutSkin.prototype.OnOutputTypeChange = function(){
}


LayoutSkin.prototype.QueryStarted = function(){
	var gridViewBounds = this.div
	var pnlPopupBounds = this.loadingDiv
	var x = /*gridViewBounds.offset().left +*/ Math.round(gridViewBounds.width() / 2) - Math.round(pnlPopupBounds.width() / 2);
	var y = /*gridViewBounds.offset().top +*/ Math.round(gridViewBounds.height() / 2) - Math.round(pnlPopupBounds.height() / 2);
	this.loadingDiv.css("top", y)
	this.loadingDiv.css("left", x)
	this.loadingDiv.css("display", "inline")
	this.loadingDiv.css("background-color", "white")
	this.div.css("background-color", "gray")
}

LayoutSkin.prototype.QueryError = function(error)
{
	this.innerDiv.text("There was an error while making the AJAX call:" + error);
	// leave this.inQuery mode
	this.inQuery = false;
	this.div.css("background-color", "white");
}

LayoutSkin.prototype.QuerySuccess = function(isInitial, response)
{
	this.data = response;
	this.data = this.CreateIndexedJSONRDF();

	// attach the template
	this.div.setTemplate(this.baseWidget.layout);
    
	// process the template
	this.div.processTemplate(this.data);

	this.loadingDiv.css("display", "none")
	this.div.css("background-color", "white")
}

LayoutSkin.prototype.CreateIndexedJSONRDF = function()
{
	var indexedJSONRDF = {}
	var linkElementPrefix = wgServer + wgScript + "?title=";
	for (record in this.data['records'])
	{
		var subject = {}
		for(entityName in this.data['records'][record]['entityNames'])
		{
			indexedJSONRDF[this.data['records'][record]['entityNames'][entityName]] = subject
		}
		for(predicate in this.data['records'][record]['fieldValues'])
		{
			var fieldVal = this.data['records'][record]['fieldValues'][predicate];
			var valuearray = {'value':'','link':'','role':''};
			valuearray['all'] = new Array();
			for (v in fieldVal)
			{
				if (valuearray.value.length>0)
				{
					valuearray.value = valuearray.value + ",";
					valuearray.link = valuearray.link + ",";
					valuearray.role = valuearray.role + ",";
				}
				var valueelement = {'role':fieldVal[v].role};
				// Check the data type to see if a link should be printed.
				if (fieldVal[v].objType == "uri")
				{
					// Note please, that all wiki titles returned by this function are now uriEncodeComponent()'d.
					var nText = uriToTitle(fieldVal[v].toString());
					valueelement.value = nText;
					valueelement.link = linkElementPrefix + encodeURIComponent(nText);
				}
				else
				{
					valueelement.value = fieldVal[v].toString();
					valueelement.link = '#';
				}
				
				valuearray.value = valuearray.value + valueelement.value;
				valuearray.link = valuearray.link + valueelement.link;
				valuearray.role = valuearray.role + valueelement.role;
				valuearray.all.push(valueelement);
			}
			var urisInPath = predicate.split(" ");
			subject[urisInPath[urisInPath.length - 1]] = valuearray;
		}
		var myvalue = uriToTitle(this.data['records'][record]['entityNames'][0]);
		subject['myvalue'] = myvalue;
		subject['mylink'] = linkElementPrefix + encodeURIComponent(myvalue);
	}
	return indexedJSONRDF
}

