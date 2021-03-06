/**
 * Registry Javascript actions
 *
 * @category   Ajax
 * @package    Registry
 * @author     Pablo Fischer <pablo@pablo.com.mx>
 * @copyright  2006-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
/**
 * Registry CallBack
 */
var RegistryCallback = {
    /**
     * Updates a registry key
     */
    setregistrykey: function(response) {
        showResponse(response);
    },
    /**
     * Updates an acl key
     */
    setaclkey: function(response) {
        showResponse(response);
    }  
}

/**
 * Converts an array to a WebFXTree (string)
 */
function convertToTree(keys, title) 
{
    var treeStructure = new Array();
    treeStructure['/'] = '/';
    var tree = new WebFXTree(title);
    tree.openIcon = 'images/xtree/openfoldericon.png';
    for(key in keys) {
        if (typeof(keys[key]) == 'function') {
            continue;
        }
        var itemSplit = key.split('/');
        var parentKey = '';           
        for(var i=0; i<itemSplit.length; i++) {
            var current  = itemSplit[i];
            if (!current.blank()) {
                var keyName  = parentKey + '/' + current + '_key';
                var lastKey  = parentKey + '_key';
                if (treeStructure[keyName] == undefined) {
                    treeStructure[keyName] = new WebFXTreeItem(current);
                    if (itemSplit.length == 2) {
                        treeStructure[keyName].action = "javascript: editKey('" + key + "');";
                        treeStructure[keyName].icon = webFXTreeConfig.fileIcon;
                    }                       
                    if (i == 1) {
                        tree.add(treeStructure[keyName]);
                    } else {
                        if (i == (itemSplit.length-1)) {
                            treeStructure[keyName].action = "javascript: editKey('" + key + "');";
                            treeStructure[keyName].icon = webFXTreeConfig.fileIcon;
                        }
                        treeStructure[lastKey].add(treeStructure[keyName]);
                    }
                } 
                parentKey += '/' + current;                    
            }
        }
    }
    delete objectName;
    return tree.toString();
}
/**
 * Initiate the UI
 */
function initUI(section)
{
    currentSection = section;
    var keys  = null;
    var title = '';
    if (section == 'registry') {
        keys  = RegistryAjax.callSync('getallregistry');
        title = registryMsg;
    } else {
        keys  = RegistryAjax.callSync('getallacl');
        title = aclMsg;
    }
    $('tree_area').innerHTML = convertToTree(keys, title);

}

/**
 * Edit a registry key
 */
function editKey(keyName) 
{
    var keyValue = '';
    if (currentSection == 'registry') {
        keyValue = RegistryAjax.callSync('getregistrykey', keyName);
    } else {
        keyValue = RegistryAjax.callSync('getaclkey', keyName);
    }
    $('key_name').value = keyName;
    $('key_value').value = keyValue;
    $('div_form').style.display = 'block';
}


/**
 * Saves a key
 */
function saveKey(form)
{
    if (currentSection == 'registry') {
        RegistryAjax.callAsync('setregistrykey', $('key_name').value, $('key_value').value);
    } else {
        RegistryAjax.callAsync('setaclkey', $('key_name').value, $('key_value').value);
    }
}

/**
 * Stop editing the key (hides the form)
 */
function cancelKey(form)
{
    $('key_name').value = '';
    $('key_name').value = '';
    $('div_form').style.display = 'none';
}

var currentSection = 'registry';

var RegistryAjax = new JawsAjax('Registry', RegistryCallback);

var tree = null;
