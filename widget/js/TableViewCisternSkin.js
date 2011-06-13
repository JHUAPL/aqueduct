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
function TableViewCisternSkin(div, baseWidget, useInitialQuery){
	this.div = jQuery("#"+div)
	this.baseWidget = baseWidget
	this.useInitialQuery = useInitialQuery

	this.loadingDiv = jQuery('<div><img src="'+wgScriptPath+'/extensions/AqueductExtension/widget/js/ajax-loader.gif" alt="Loading..." /></div>').css("display", "hidden")
	this.loadingDiv.css("position", "relative").css("float", "left").css("z-index", 1000)
	this.div.append(this.loadingDiv)

	this.innerDiv = jQuery('<div></div>')
	this.div.append(this.innerDiv)

	this.div.css('overflow', 'auto');

	this.inQuery = false
}

TableViewCisternSkin.prototype.Destroy = function(){
	this.div = null
	this.baseWidget = null
	this.loadingDiv = null
	this.innerDiv = null
	this.selectbox = null
}

TableViewCisternSkin.prototype.OnOutputTypeChange = function(){

}


TableViewCisternSkin.prototype.QueryStarted = function(){
	var gridViewBounds = this.div
	var pnlPopupBounds = this.loadingDiv
	var x = Math.round(gridViewBounds.width() / 2) - Math.round(pnlPopupBounds.width() / 2);
	var y = Math.round(gridViewBounds.height() / 2) - Math.round(pnlPopupBounds.height() / 2);
	this.loadingDiv.css("top", y)
	this.loadingDiv.css("left", x)
	this.loadingDiv.css("display", "inline")
	this.loadingDiv.css("background-color", "white")
	this.div.css("background-color", "gray")
	this.inQuery = true
}

TableViewCisternSkin.prototype.QueryError = function(error)
{
	this.innerDiv.text("There was an error while making the AJAX call:" + error);
	// leave this.inQuery mode
	this.inQuery = false;
	this.loadingDiv.css("display", "none");
	this.div.css("background-color", "white");
}

TableViewCisternSkin.prototype.QuerySuccess = function(isInitial, response)
{
	this.innerDiv.text('TableViewCisternSkin')

	this.baseWidget.ProfileWidget("Building HTML");
	var html = [];
	html.push("<table style='font-family:sans-serif;font-size:small;border:1px solid black;text-align:center;border-collapse:collapse;' class='tableviewcisterntable'>");
	
	var linkElementPrefix = "<a href='" + wgServer + wgScript + "?title=";
	var linkElementPrefixForTd = "<td style='padding:0px 5px;border:1px solid black; background-color:#F4F4F4;border-collapse: collapse'>" + linkElementPrefix;

	
	html.push("<th style='padding:0px 5px;border:1px solid black; background-color:#F4F4F4;border-collapse: collapse'>entity</th>");
	
	//Add the columns
	for (f in response['fields'])
	{
		html.push("<th style='padding:0px 5px;border:1px solid black; background-color:#F4F4F4;border-collapse: collapse'>");
		html.push(this.EncodeHTML(response['fields'][f].fieldName.toString()));
		html.push("</th>");
	}
	html.push("</tr>");

	for (r in response['records'])
	{
		html.push("<tr style='z-index:1'>");
		// Note please, that all wiki titles returned by this function are now uriEncodeComponent()'d.
		var entityTitle = uriToTitle(response['records'][r]['entityNames'][0]);
		html.push(linkElementPrefixForTd);
		html.push(encodeURIComponent(entityTitle));
		html.push("'>");
		html.push(this.EncodeHTML(entityTitle));
		html.push("</a></td>");
		
		for (f in response['fields'])
		{
			var fieldVal = response['records'][r]['fieldValues'][response['fields'][f].fullName];
			if (fieldVal)
			{
				var tdindex = html.length;
				html.push("<td style='padding:0px 5px;border:1px solid black;border-collapse: collapse;background-color:#F4F4F4'>");
				var longDataContainer = null;
				if (fieldVal.toString().length > 100)
				{
					longDataContainer = "<span style='font-size:smaller'><br/>(...click to expand...)</span><div id='data' style='display:none'>";
				}
				for (v in fieldVal)
				{
					// Check the data type to see if a link should be printed.
					if (fieldVal[v].objType == "uri")
					{
						// Note please, that all wiki titles returned by this function are now uriEncodeComponent()'d.
						var nText = uriToTitle(fieldVal[v].toString());
						var encodedText = this.EncodeHTML(nText);
						var n = linkElementPrefix + encodeURIComponent(nText)+"'>"+encodedText+"</a>";
					}
					else
					{
						var nText = fieldVal[v].toString();
						var encodedText = this.EncodeHTML(nText);
						var n = encodedText;
					}
					//Check if the cell contents will be too long for everything to fit
					if (!longDataContainer)
					{
						if (v > 0)
						{
							html.push(", ");
						}
						html.push(n);
					}
					else
					{
						if (v > 0)
						{
							html.push(", ");
						}
						else
						{
							//Print the first 100 characters of the first object for this column
							html.push(encodedText.substr(0,100));
						}
						longDataContainer = longDataContainer + n;
					}
				}
				if (longDataContainer)
				{
					html.push(longDataContainer + "</div>");
					//Slip the long data event handler into the HTML
					html[tdindex] = "<td onclick='TableViewCisternSkin.prototype.DisplayHiddenData(this)' style='border:1px solid black; border-collapse: collapse; background-color:#F4F4F4'>";
				}
				html.push("</td>");
			}
			else
			{
				html.push("<td style='padding:0px 5px;border:1px solid black;border-collapse: collapse;background-color:#F4F4F4'>&nbsp;</td>");
			}
		}

		html.push("</tr>");
	}
	html.push("</table>");
	
	this.baseWidget.ProfileWidget("Setting HTML");
	this.innerDiv.html(html.join(""));
	
	this.baseWidget.ProfileWidget("Finishing up");
	// leave this.inQuery mode
	this.inQuery = false

	this.loadingDiv.css("display", "none")
	this.div.css("background-color", "white")
}

TableViewCisternSkin.prototype.DisplayHiddenData = function (obj)
{
	var expandedDiv = jQuery("<div id='expanded'></div>")
	var width = 300 + Math.round(jQuery(obj).children("#data").text().length / 1000.0) * 100

	expandedDiv.text(jQuery(obj).children("#data").text())
	expandedDiv.css("position", "absolute").css("float", "left").css("z-index", 1000).css("width", width).click(TableViewCisternSkin.prototype.RemoveHiddenData)
	expandedDiv.css("display", "inline")


	// td - tr - tbody - table - div
	jQuery(obj).parent().parent().parent().parent().append(expandedDiv)


	var myHeight = jQuery(obj).offset().top

	expandedDiv.css("top", myHeight)

	// we need to set ourselves lower then the rows above us here

	var gridViewBounds = jQuery(obj)
	var x = (gridViewBounds.position().left + Math.round(gridViewBounds.innerWidth() / 2)) - Math.round(expandedDiv.width() / 2);
	var y = gridViewBounds.offset().top
	//expandedDiv.css("top", y)
	expandedDiv.css("left", x)
	expandedDiv.css("background-color", "#FFFFCC")
}

TableViewCisternSkin.prototype.RemoveHiddenData = function ()
{
	jQuery(this).remove()
}

TableViewCisternSkin.prototype.EncodeHTML = function (s)
{
	//We do not encode "unicode" characters, but the browser built-in HTML encoders don't
	//encode unicode characters either.
	//Some other things (like spaces and tabs) will not be encoded perfectly
	var a = s.replace("\r\n","<br/>");
	a = a.replace("\r","<br/>");
	a = a.replace("\n","<br/>");
	a = a.replace("&","&amp;");
	a = a.replace("<","&lt;");
	a = a.replace(">","&gt;");
	return a;
}
