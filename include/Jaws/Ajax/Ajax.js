/**
 * Shortcut function for using instead of getElementById
 */
if (typeof Prototype  == 'undefined') {
    $ = function(element) {
        return document.getElementById(element);
    }
}

/**
 * Shortcut function for using instead of getElementsByName
 */
function $N(name, doc) {
  return $A((doc || document).getElementsByName(name));
}

/**
 * Repaints a combo
 */
function paintCombo(combo, oddColor, evenColor)
{
    if (evenColor == undefined) {
        evenColor = '#fff';
    }

    var color  = evenColor;
    for(var i=0; i<combo.length; i++) {
        combo.options[i].style.backgroundColor = color;;
        if (i % 2 == 0) {
            color = oddColor;
        } else {
            color = evenColor;
        }
    }
}

/**
 * Change the value of the editor/textarea
 */
function changeEditorValue(name, value)
{
    var usingMCE = typeof tinyMCE  == 'undefined' ? false : true;
    var usingCKE = typeof CKEDITOR == 'undefined' ? false : true;
    if (usingMCE) {
        var editor = tinyMCE.get(name);
        if (editor) {
            editor.setContent(value);
         } else {
            $(name).value = value;
         }
    } else if (usingCKE) {
        var editor = CKEDITOR.instances[name];
        if (editor.status == 'unloaded') {
            $(name).value = value;
        } else {
            editor.setData(value);
        }
    } else {
        $(name).value = value;
    }
}

/**
 * Get the value of the editor/textarea
 */
function getEditorValue(name)
{
    var usingMCE = typeof tinyMCE  == 'undefined' ? false : true;
    var usingCKE = typeof CKEDITOR == 'undefined' ? false : true;
    if (usingMCE) {
        var editor = tinyMCE.get(name);
        return editor.getContent();
    } else if (usingCKE) {
        var editor = CKEDITOR.instances[name];
        if (editor.status != 'unloaded') {
            return editor.getData();
        }
    }

    return $(name).value;
}

/**
 * Reset a (piwi)datagrid:
 *  - Clean all data
 *  - Set the new data
 *  - Repaint
 */
function resetGrid(name, data, rowsSize)
{
    $(name).reset();
    $(name).fillWithArray(data);
    if (rowsSize != undefined) {
        $(name).rowsSize = rowsSize;
    }
    $(name).updatePageCounter();
    $(name).repaint();
}

//Which row selected in DataGrid
var selectedRows = new Array();
var selectedRowsColor = new Array();

/**
 * Select a (piwi)datagrid row
 */
function selectGridRow(name, rowElement)
{
    if (selectedRows[name]) {
        if (typeof(selectedRows[name]) == 'object') {
            selectedRows[name].style.backgroundColor = selectedRowsColor[name];
        } else {
            $(selectedRows[name]).style.backgroundColor = selectedRowsColor[name];
        }
    }

    if (typeof(rowElement) == 'object') {
        selectedRowsColor[name] = rowElement.style.backgroundColor;
        rowElement.style.backgroundColor = '#ffffcc';
    } else {
        selectedRowsColor[name] = $(rowElement).style.backgroundColor;
        $(rowElement).style.backgroundColor = '#ffffcc';
    }

    selectedRows[name] = rowElement;
}

/**
 * Unselect a (piwi)datagrid row
 *
 */
function unselectGridRow(name)
{
    if (selectedRows[name]) {
        if (typeof(selectedRows[name]) == 'object') {
            selectedRows[name].style.backgroundColor = selectedRowsColor[name];
        } else {
            $(selectedRows[name]).style.backgroundColor = selectedRowsColor[name];
        }
    }

    selectedRows[name] = null;
    selectedRowsColor[name] = null;
}

/**
 * Class JawsDatagrid
 */
var JawsDataGrid = {

    /**
     * Get the first Values and prepares the datagrid
     */
    getFirstValues: function() {
        var firstValues = $(this.name).getFirstPagerValues();
        var ajaxObject  = $(this.name).objectName;
        var result      = ajaxObject.getdata(firstValues, $(this.name).id);
        resetGrid($(this.name), result);
        $(this.name).firstPage();
    },

    /**
     * Get the previous Values and prepares the datagrid
     */
    getPreviousValues: function() {
        var previousValues = $(this.name).getPreviousPagerValues();
        var ajaxObject     = $(this.name).objectName;
        var result         = ajaxObject.getdata(previousValues, $(this.name).id);
        resetGrid($(this.name), result);
        $(this.name).previousPage();
    },

    /**
     * Get the next Values and prepares the datagrid
     */
    getNextValues: function() {
        var nextValues     = $(this.name).getNextPagerValues();
        var ajaxObject     = $(this.name).objectName;
        var result         = ajaxObject.getdata(nextValues, $(this.name).id);
        resetGrid($(this.name), result);
        $(this.name).nextPage();
    },

    /**
     * Get the last Values and prepares the datagrid
     */
    getLastValues: function() {
        var lastValues = $(this.name).getLastPagerValues();
        var ajaxObject = $(this.name).objectName;
        var result     = ajaxObject.getdata(lastValues, $(this.name).id);
        resetGrid($(this.name), result);
        $(this.name).lastPage();
    },

    /**
     * Only retrieves information with the current page the pager has and prepares the datagrid
     */
    getData: function() {
        var currentPage = $(this.name).getCurrentPage();
        var ajaxObject  = $(this.name).objectName;
        var result      = ajaxObject.getdata(currentPage, $(this.name).id);
        resetGrid($(this.name), result);
    }
}

/**
 * Prepares the datagrid with basic data
 */
function initDataGrid(name, objectName, dataFunc)
{
    if ($(name) == undefined || objectName == undefined) {
        return true;
    }

    $(name).objectName = objectName;
    if (dataFunc == undefined) {
        JawsDataGrid.name = name;
        $(name + '_pagerFirstAnchor').onclick = function() {
            JawsDataGrid.getFirstValues();
        }

        $(name + '_pagerPreviousAnchor').onclick = function() {
            JawsDataGrid.getPreviousValues();
        }

        $(name + '_pagerNextAnchor').onclick = function() {
                JawsDataGrid.getNextValues();
        }

        $(name + '_pagerLastAnchor').onclick = function() {
                JawsDataGrid.getLastValues();
        }

        getDG();
    } else {
        $(name).dataFunc = dataFunc;

        $(name + '_pagerFirstAnchor').onclick = function() {
            var offset = $(name).getFirstPagerValues();
            getDG(name, offset);
            $(name).firstPage();
        }

        $(name + '_pagerPreviousAnchor').onclick = function() {
            var offset = $(name).getPreviousPagerValues();
            getDG(name, offset);
            $(name).previousPage();
        }

        $(name + '_pagerNextAnchor').onclick = function() {
            var offset = $(name).getNextPagerValues();
            getDG(name, offset);
            $(name).nextPage();
        }

        $(name + '_pagerLastAnchor').onclick = function() {
            var offset = $(name).getLastPagerValues();
            getDG(name, offset);
            $(name).lastPage();
        }

        getDG(name);
    }
}

/**
 * Fast method to retrieve datagrid data
 */
function getDG(name, offset, reset)
{
    if (name == undefined) {
        JawsDataGrid.getData();
    } else {
        dataFunc = eval($(name).dataFunc);

        if (offset == undefined) {
            var offset = $(name).getCurrentPage();
        }

        reset = (reset == true) || ($(name).rowsSize == 0);
        dataFunc(name, offset, reset);
        if (reset) {
            $(name).setCurrentPage(0);
        }
    }
}


/**
 * Changes the text of a button with a stock
 */
function changeButtonText(button, message)
{
    var buttonInner = button.innerHTML.substr(0, button.innerHTML.indexOf("&nbsp;"));
    button.innerHTML = buttonInner + "&nbsp;" + message;
    button.value     = message;
}

/**
 * Similar to PHP-trim fuction
 */
function jawsTrim(str)
{
    return str.replace(/^\s*|\s*$/g, '');
}

function initJawsObservers()
{
    var forms = document.getElementsByTagName('form');
    for(var i=0; i<forms.length; i++) {
        new Form.Observer(forms[i], 1, jawsFormCallback);
    }
}


function jawsFormCallback(form, elements)
{
    elements = elements.split('&');
    for(var i=0; i<elements.length; i++) {
        var elementName = elements[i].split('=')[0];
        var element     = form.elements[elementName];
        switch(element.type) {
        case 'text':
            element.value = element.value.unescapeHTML();
            break;
        }
        //var element = $(element
        //alert($elements.type);
    }
}

/**
 * Creates an image link
 *
 *   <a href="link"><img src="imgSrc" border="0" title="text" /></a>
 */
function createImageLink(imgSrc, link, text, space)
{
    var linkElement = document.createElement('a');
    linkElement.href = link;
    if (space == true) {
        linkElement.style.paddingRight = '3px';
    }

    var image = document.createElement('img');
    image.border = '0';
    image.src = imgSrc;
    image.title = text;

    linkElement.appendChild(image);

    return linkElement;
}

/**
 * Resets a combo
 */
function resetCombo(combo)
{
    while(combo.options.length != 0) {
        combo.options[0] = null;
    }
}

/**
 * Prepares the datepicker with basic data
 */
function initDatePicker(name)
{
    dpTable = $(name + '_table');
    var script = dpTable.nextSibling;
    var newScript = document.createElement('script');
    newScript.type = "text/javascript";
    newScript.text = script.text;
    Element.remove(script);
    dpTable.parentNode.appendChild(newScript);
}

/**
 * Show Dialog Box
 */
function showDialogBox(name, dTitle, url, dHeight, dWidth)
{
    var dRect = document.viewport.getDimensions();
    var dLeft = (dWidth  > dRect.width )? 0 : Math.round(dRect.width  / 2 - dWidth  / 2) + 'px';
    var dTop  = (dHeight > dRect.height)? 0 : Math.round(dRect.height / 2 - dHeight / 2) + 'px';

    if ($(name) == undefined) {
        var overlay = new Element('div', {'id':name+'_overlay', 'class':'dialog_box_overlay'}).hide();
        var iframe  = new Element('iframe', {'id':name+'_iframe', frameborder:0});
        var close   = new Element('span', {'class': 'dialog_box_close'});
        var title   = new Element('div', {'class':'dialog_box_title'}).insert(dTitle).insert(close);
        var dialog  = new Element('div', {'id':name, 'class':'dialog_box'}).insert(title).insert(iframe).hide();
        iframe.observe('load', function() {
            hideWorkingNotification();
            dialog.show();
            Event.observe(iframe.contentWindow.document, 'keydown', function(e) {
                if (e.keyCode == Event.KEY_ESC) {
                    hideDialogBox(name);
                }
            });
        });
        iframe.observe('cached:load', function() {
            hideWorkingNotification();
            dialog.show();
        });
        close.observe('click', function() {hideDialogBox(name);});
        overlay.observe('mousedown', function(e) {Event.stop(e);});
        document.observe('keydown', function(e) {
            if (dialog.visible() && e.keyCode == Event.KEY_ESC) {
                hideDialogBox(name);
            }
        });
        document.body.insert(overlay);
        document.body.insert(dialog);
    }

    $(name+'_overlay').show();
    showWorkingNotification();
    $(name+'_iframe').setStyle({height:dHeight+'px', width:dWidth+'px'});
    $(name).setStyle({left:dLeft, top:dTop});
    if ($(name+'_iframe').src == url) {
        $(name+'_iframe').fire('cached:load');
    } else {
        $(name+'_iframe').src = url;
    }
}

/**
 * Hide Dialog Box
 */
function hideDialogBox(name)
{
    $(name).hide();
    $(name+'_overlay').hide();
}

/**
 * Server error handler
 */
function Jaws_Ajax_ServerError(error) 
{
    //Take the error and parse to see if it's a JawsServerError or a bug in the code
    var errorMessage = error.message;
    //JawsServerError pattern
    var pattern = /^\[(.*?)\]\s+(-)\s+(.*?)/;
    //Test..
    if (pattern.test(errorMessage)) {
        var errorSplitted = errorMessage.split(pattern);
        var errorCode     = errorSplitted[1];
        errorMessage      = errorSplitted[4];
        switch(errorCode) {
        case 'NOPERMISSION': //Not granted?
            alert(errorMessage);
            break;
        case 'NOSESSION': //No session?
            // FIXME, using href of base tag instead of admin.php or if empty parsing URL
            window.location = 'admin.php';
            break;
        case 'NOTLOGGED': //Session expired?
            alert(errorMessage + '...');
            // FIXME, using href of base tag instead of admin.php or if empty parsing URL
            window.location = 'admin.php';
            break;            
        }
    }
}

/**
 * Show the response
 */
function showResponse(message, goTop)
{
    if (typeof(goTop) == 'undefined' || goTop) {
        new Effect.ScrollTo($(document.body));
    }

    messages = new Array();
    if (message[0] == undefined) {
        messages[0] = message;
    } else {
        messages = message;
    }

    $('msgbox-wrapper').innerHTML = '';
    for(var i = 0; i < messages.length; i++) {
        var messageDiv = document.createElement('div');
        $('msgbox-wrapper').appendChild(messageDiv);
        messageDiv.innerHTML = messages[i]['message'];
        messageDiv.className = messages[i]['css'];
        messageDiv.id = 'msgbox_'+i;
        new Effect.Appear(messageDiv);
        hideResponseBox(messageDiv);
    }
}

/**
 * Hide response boxes - Fast Code
 */
function hideResponseBox(name, timehide)
{
    if (typeof(timehide) == 'undefined') {
        timehide = '3000';
    }

    setTimeout('hideResponseBoxCallback("' + name.id + '")', timehide);
}

/**
 * Hide response boxes - JS Action (callback)
 */
function hideResponseBoxCallback(name)
{
    new Effect.Fade(name);
}

/**
 * Show working notification.
 */
function showWorkingNotification(msg)
{
    if (!msg) {
        msg = loading_message;
    }
    $('working_notification').innerHTML = msg;
    $('working_notification').style.visibility = 'visible';
    loading_message = default_loading_message;
}

/**
 * Hide working notification
 */
function hideWorkingNotification()
{
    $('working_notification').style.visibility = 'hidden';
}