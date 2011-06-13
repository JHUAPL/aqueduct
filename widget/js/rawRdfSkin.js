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
// USE: The Raw RDF Skin just dumps tuples recieved into a table.

// Constructor
function RawRDFCisternSkin(div, baseWidget, useInitialQuery){
	this.div = jQuery("#"+div)
	this.baseWidget = baseWidget
	this.useInitialQuery = useInitialQuery

	this.loadingDiv = jQuery('<div><img src="'+wgScriptPath+'/extensions/AqueductExtension/widget/js/ajax-loader.gif" alt="Loading..." /></div>').css("display", "none")
	this.loadingDiv.css("position", "relative").css("float", "left").css("z-index", 1000)
	this.div.append(this.loadingDiv)

	// set up output selector
	this.selectbox = jQuery('<select><option value="JSON">JSON</option><option selected="true" value="Table">Table</option></select>')
	this.div.append("Output Type: ")
	this.div.append(this.selectbox)
	this.selectbox.change(function() {
		baseWidget.QueryCistern(useInitialQuery)
	});

	this.innerDiv = jQuery('<div></div>')
	this.div.append(this.innerDiv)

	this.inQuery = false
}

RawRDFCisternSkin.prototype.Destroy = function(){
	this.div = null
	this.baseWidget = null
	this.loadingDiv = null
	this.innerDiv = null
	this.selectbox = null
}

RawRDFCisternSkin.prototype.OnOutputTypeChange = function(){

}


RawRDFCisternSkin.prototype.QueryStarted = function(){
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

RawRDFCisternSkin.prototype.QueryError = function(error)
{
	this.innerDiv.text("There was an error while making the AJAX call:" + error);
	// leave this.inQuery mode
	this.inQuery = false;
	this.loadingDiv.css("display", "none");
	this.div.css("background-color", "white");
}

RawRDFCisternSkin.prototype.QuerySuccess = function(isInitial, response){

	if (this.selectbox[0].selectedIndex == 1)
	{
		this.innerDiv.text('RawRDFCisternSkin')
		var html = jQuery("<table class='rawrdfcisterntable'></table>")
		
		var row = jQuery("<tr></tr>")
		row.append(jQuery("<th>entity</th>"))
		row.append(jQuery("<th>field</th>"))
		row.append(jQuery("<th>value</th>"))

		html.append(row)


		for (r in response['records'])
		{
			
			for (f in response['records'][r]['fieldValues'])
			{
				var row = jQuery("<tr></tr>")
				// Note please, that all wiki titles returned by this function are now uriEncodeComponent()'d.
				var entityTitle = uriToTitle(response['records'][r]['entityNames'][0])
				row.append(jQuery("<td></td>").append(jQuery("<a/>").attr("href",wgServer + wgScript + "?title=" + encodeURIComponent(entityTitle)).text(entityTitle)));

				// field type
				row.append(jQuery("<td></td>").text(f))

				// field value
				if (response['records'][r]['fieldValues'][f][0].objType == "uri")
				{
					// Note please, that all wiki titles returned by this function are now uriEncodeComponent()'d.
					var uriWikiTitle = uriToTitle(response['records'][r]['fieldValues'][f].toString())
					row.append(jQuery("<td></td>").append(jQuery("<a/>").attr("href",wgServer + wgScript + "?title=" + encodeURIComponent(uriWikiTitle)).text(uriWikiTitle)));
				}
				else
				{
					row.append(jQuery("<td></td>").text(response['records'][r]['fieldValues'][f].toString()))
				}

				// role
				html.append(row)
			}
			
		}

		this.innerDiv.append(html)
		
		jQuery(".rawrdfcisterntable").css("font-family", "sans-serif").css("font-size", "smaller").css("border", "1px solid black")
		jQuery(".rawrdfcisterntable th").css("border", "1px solid black")
		jQuery(".rawrdfcisterntable td").css("border", "1px solid black")
	}
	else if (this.selectbox[0].selectedIndex == 0){
		this.innerDiv.text(this.Stringify(response['fullRDF']))
	}

	// leave this.inQuery mode
	this.inQuery = false

	this.loadingDiv.css("display", "none")
	this.div.css("background-color", "white")
}

RawRDFCisternSkin.prototype.Stringify = function(obj) {
    if (obj instanceof Array)
        var ret = "[";
    else
        var ret = "{";

    var prop;
    var x;

    count = 0;

    for (prop in obj) {
        if (count > 0)
            ret += ","

        if (obj[prop] instanceof Array) {
            ret += "'" + prop + "':[";
            for (x = 0; x < obj[prop].length; x++) {

                if (obj[prop][x] instanceof Object || obj[prop][x] instanceof Array) {
                    ret += this.Stringify(obj[prop][x]);

                    if (x == obj[prop].length - 1) {
                    }
                    else
                        ret += ",";
                }
                else {
                    ret += "'" + obj[prop][x];

                    if (x == obj[prop].length - 1)
                        ret += "'";
                    else
                        ret += "',";
                }
            }
            ret += " ]";
        }
        else if (obj[prop] instanceof Object) {
            ret += "'" + prop + "':";
            ret += this.Stringify(obj[prop]);
        }
        else
            ret += "'" + prop + "':'" + obj[prop] + "'";

        count++;
    }

    if (obj instanceof Array)
        ret += "]";
    else
        ret += "}";

    return ret
}
