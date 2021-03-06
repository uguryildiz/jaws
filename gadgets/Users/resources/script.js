/**
 * Users Javascript actions
 *
 * @category   Ajax
 * @package    Users
 * @author     Pablo Fischer <pablo@pablo.com.mx>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2004-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
/**
 * Use async mode, create Callback
 */
var UsersCallback = {
    adduser: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopUserAction();
            $('users_datagrid').addItem();
            $('users_datagrid').lastPage();
            getDG('users_datagrid');
        }
        showResponse(response);
    },

    updateuser: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopUserAction();
            getDG('users_datagrid');
        }
        showResponse(response);
    },

    updateuseracl: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopUserAction();
        }
        showResponse(response);
    },

    addusertogroups: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopUserAction();
        }
        showResponse(response);
    },

    updatepreferences: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopUserAction();
        }
        showResponse(response);
    },

    updatepersonal: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopUserAction();
        }
        showResponse(response);
    },

    updatemyaccount: function(response) {
        $('pass1').value = '';
        $('pass2').value = '';
        showResponse(response);
    },

    deleteuser: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopUserAction();
            $('users_datagrid').deleteItem();
            getDG('users_datagrid');
        }
        showResponse(response);
    },

    addgroup: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopGroupAction();
            $('groups_datagrid').addItem();
            $('groups_datagrid').lastPage();
            getDG('groups_datagrid');
        }
        showResponse(response);
    },

    updategroup: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopGroupAction();
            getDG('groups_datagrid');
        }
        showResponse(response);
    },

    deletegroup: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopGroupAction();
            $('groups_datagrid').deleteItem();          
            getDG('groups_datagrid');
        }
        showResponse(response);
    },

    updategroupacl: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopGroupAction();
        }
        showResponse(response);
    },

    adduserstogroup: function(response) {
        if (response[0]['css'] == 'notice-message') {
            stopGroupAction();
        }
        showResponse(response);
    },

    deletesession: function(response) {
        if (response[0]['css'] == 'notice-message') {
            clearTimeout(fTimeout);
            getOnlineUsers('onlineusers_datagrid');
        }
        showResponse(response);
    },

    ipblock: function(response) {
        showResponse(response);
    },

    agentblock: function(response) {
        showResponse(response);
    },

    savesettings: function(response) {
        showResponse(response);
    }
}

/**
 * On term key press, for compatibility Opera/IE with other browsers
 */
function OnTermKeypress(element, event)
{
    if (event.keyCode == 13) {
        element.blur();
        element.focus();
    }
}

/**
 * Search user function
 */
function searchUser()
{
    getUsers('users_datagrid', 0, true);
}

/**
 * Get users list
 */
function getUsers(name, offset, reset)
{
    var result = UsersAjax.callSync('getusers',
                                    $('filter_group').value,
                                    $('filter_type').value,
                                    $('filter_status').value,
                                    $('filter_term').value,
                                    $('order_type').value,
                                    offset);
    if (reset) {
        $(name).setCurrentPage(0);
        var total = UsersAjax.callSync('getuserscount',
                                       $('filter_group').value,
                                       $('filter_type').value,
                                       $('filter_status').value,
                                       $('filter_term').value);
    }
    resetGrid(name, result, total);
}

/**
 * Get groups list
 */
function getGroups(name, offset, reset)
{
    var result = UsersAjax.callSync('getgroups', offset);
    if (reset) {
        $(name).setCurrentPage(0);
        var total = UsersAjax.callSync('getgroupscount');
    }
    resetGrid(name, result, total);
}

/**
 * Get online users list
 */
function getOnlineUsers(name, offset, reset)
{
    var result = UsersAjax.callSync('getonlineusers');
    resetGrid(name, result, result.length);
    fTimeout = setTimeout("getOnlineUsers('onlineusers_datagrid');", 30000);
}

/**
 * Saves users data / changes
 */
function saveUser()
{
    switch(currentAction) {
        case 'UserAccount':
            if ($('pass1').value != $('pass2').value) {
                alert(wrongPassword);
                return false;
            }

            if ($('username').value.blank() ||
                $('nickname').value.blank() ||
                $('email').value.blank())
            {
                alert(incompleteUserFields);
                return false;
            }

            if ($('exponent')) {
                setMaxDigits(128);
                var pub_key = new RSAPublicKey($('exponent').value, $('modulus').value, 128);
                var password = encryptedString(pub_key, $('pass1').value);
            } else {
                var password = $('pass1').value;
            }

            if ($('uid').value == 0) {
                if ($('pass1').value.blank()) {
                    alert(incompleteUserFields);
                    return false;
                }

                UsersAjax.callAsync('adduser',
                                    $('username').value,
                                    password,
                                    $('nickname').value,
                                    $('email').value,
                                    $('superadmin').value,
                                    $('logins').value,
                                    $('expiry_date').value,
                                    $('status').value);
            } else {
                UsersAjax.callAsync('updateuser',
                                    $('uid').value,
                                    $('username').value,
                                    password,
                                    $('nickname').value,
                                    $('email').value,
                                    $('superadmin').value,
                                    $('logins').value,
                                    $('expiry_date').value,
                                    $('status').value);
            }

            break;

        case 'UserACL':
            UsersAjax.callAsync('updateuseracl', $('uid').value, changedACLs);
            break;

        case 'UserGroups':
            var inputs  = $('user_workarea').getElementsByTagName('input');
            var keys    = new Array();
            var counter = 0;
            for (var i=0; i<inputs.length; i++) {
                if (inputs[i].name.indexOf('user_groups') == -1) {
                    continue;
                }

                if (inputs[i].checked) {
                    keys[counter] = inputs[i].value;
                    counter++;
                }
            }

            UsersAjax.callAsync('addusertogroups', $('uid').value, keys);
            break;

        case 'UserPersonal':
            UsersAjax.callAsync('updatepersonal',
                                $('uid').value,
                                $('fname').value,
                                $('lname').value,
                                $('gender').value,
                                $('dob').value,
                                $('url').value,
                                $('about').value,
                                $('avatar').value,
                                $('privacy').value);
            break;

        case 'UserPreferences':
            UsersAjax.callAsync('updatepreferences',
                                $('uid').value,
                                $('language').value,
                                $('theme').value,
                                $('editor').value,
                                $('timezone').value);
            break;
    }

}

/**
/**
 * Logout an user
 */
function deleteSession(rowElement, sid) {
    selectGridRow('onlineusers_datagrid', rowElement.parentNode.parentNode);
    if (confirm(confirmThrowOut)) {
        UsersAjax.callAsync('deletesession', sid);
    }
    unselectGridRow('onlineusers_datagrid');
}

/**
 * User's IP block
 */
function ipBlock(rowElement, ip) {
    selectGridRow('onlineusers_datagrid', rowElement.parentNode.parentNode);
    if (confirm(confirmBlockIP)) {
        UsersAjax.callAsync('ipblock', ip);
    }
    unselectGridRow('onlineusers_datagrid');
}

/**
 * User's Agent block
 */
function agentBlock(rowElement, agent) {
    selectGridRow('onlineusers_datagrid', rowElement.parentNode.parentNode);
    if (confirm(confirmBlockAgent)) {
        UsersAjax.callAsync('agentblock', agent);
    }
    unselectGridRow('onlineusers_datagrid');
}

/**
 * Delete user
 */
function deleteUser(rowElement, uid)
{
    stopUserAction();
    selectGridRow('users_datagrid', rowElement.parentNode.parentNode);
    if (confirm(confirmUserDelete)) {
        UsersAjax.callAsync('deleteuser', uid);
    }
    unselectGridRow('users_datagrid');
}

/**
 * Delete group
 */
function deleteGroup(rowElement, gid)
{
    stopGroupAction();
    selectGridRow('groups_datagrid', rowElement.parentNode.parentNode);
    if (confirm(confirmGroupDelete)) {
        UsersAjax.callAsync('deletegroup', gid);
    }
    unselectGridRow('groups_datagrid');
}

/**
 * Save the group ACL keys
 */
function saveGroupACL()
{
    UsersAjax.callAsync('savegroupacl', selectedGroup, changedACLs);
}

/**
 * Edit user
 */
function editUser(rowElement, uid)
{
    $('uid').value = uid;
    currentAction = 'UserAccount';
    $('legend_title').innerHTML  = editUser_title;
    $('user_workarea').innerHTML = cachedUserForm;
    initDatePicker('expiry_date');
    selectGridRow('users_datagrid', rowElement.parentNode.parentNode);

    var uInfo = UsersAjax.callSync('getuser', uid, true);
    $('username').value    = uInfo['username'];
    $('nickname').value    = uInfo['nickname'].defilter();
    $('email').value       = uInfo['email'];
    $('superadmin').value  = Number(uInfo['superadmin']);
    $('logins').value      = uInfo['concurrent_logins'];
    $('expiry_date').value = uInfo['expiry_date'];
    $('status').value      = uInfo['status'];
}

/**
 * edit user-ACL keys
 */
function editUserACL(rowElement, uid)
{
    $('uid').value = uid;
    currentAction = 'UserACL';
    $('legend_title').innerHTML  = editUserACL_title;
    var aclKeys = UsersAjax.callSync('getuseraclkeys', uid);
    $('user_workarea').innerHTML = convertToTree(aclKeys);
    selectGridRow('users_datagrid', rowElement.parentNode.parentNode);
    changedACLs = [];
}

/**
 * Edit the groups of user
 */
function editUserGroups(rowElement, uid)
{
    $('uid').value = uid;
    currentAction = 'UserGroups';
    $('legend_title').innerHTML  = editUserGroups_title;
    if (cachedUserGroupsForm == null) {
        cachedUserGroupsForm = UsersAjax.callSync('usergroupsui');
    }
    $('user_workarea').innerHTML = cachedUserGroupsForm;
    selectGridRow('users_datagrid', rowElement.parentNode.parentNode);

    var uGroups = UsersAjax.callSync('getusergroups', uid);
    uGroups.each(function(gid, index) {
        if ($('group_' + gid)) {
            $('group_' + gid).checked = true;
        }
    });
}

/**
 * Edit user's personal information
 */
function editPersonal(rowElement, uid)
{
    $('uid').value = uid;
    currentAction = 'UserPersonal';
    $('legend_title').innerHTML  = editPersonal_title;
    if (cachedPersonalForm == null) {
        cachedPersonalForm = UsersAjax.callSync('personalui');
    }
    $('user_workarea').innerHTML = cachedPersonalForm;
    initDatePicker('dob');
    selectGridRow('users_datagrid', rowElement.parentNode.parentNode);

    var uInfo = UsersAjax.callSync('getuser', uid, false, true);
    $('fname').value   = uInfo['fname'];
    $('lname').value   = uInfo['lname'];
    $('gender').value  = Number(uInfo['gender']);
    $('dob').value     = uInfo['dob'];
    $('url').value     = uInfo['url'];
    $('about').value   = uInfo['about'];
    $('avatar').value  = 'false';
    $('image').src     = uInfo['avatar']+ '?'+ (new Date()).getTime();
    $('privacy').value = Number(uInfo['privacy']);
}

/**
 * Edit user's preferences
 */
function editPreferences(rowElement, uid)
{
    $('uid').value = uid;
    currentAction = 'UserPreferences';
    $('legend_title').innerHTML  = editPreferences_title;
    if (cachedPreferencesForm == null) {
        cachedPreferencesForm = UsersAjax.callSync('preferencesui');
    }
    $('user_workarea').innerHTML = cachedPreferencesForm;
    selectGridRow('users_datagrid', rowElement.parentNode.parentNode);

    var uInfo = UsersAjax.callSync('getuser', uid, false, false, true);
    $('language').value = uInfo['language'] == null? '-default-': uInfo['language'];
    $('theme').value    = uInfo['theme']    == null? '-default-': uInfo['theme'];
    $('editor').value   = uInfo['editor']   == null? '-default-': uInfo['editor'];
    $('timezone').value = uInfo['timezone'] == null? '-default-': uInfo['timezone'];
}

/**
 * Uploads the avatar
 */
function upload() {
    showWorkingNotification();
    var iframe = new Element('iframe', {id:'ifrm_upload', name:'ifrm_upload'});
    $('user_workarea').adopt(iframe);
    $('frm_avatar').submit();
}

/**
 * Loads and sets the uploaded avatar
 */
function onUpload(response) {
    hideWorkingNotification();
    if (response.type === 'error') {
        alert(response.message);
        $('frm_avatar').reset();
    } else {
        var filename = response.message + '&' + (new Date()).getTime();
        $('image').src = base_script + '?gadget=Users&action=LoadAvatar&file=' + filename;
        $('avatar').value = response.message;
    }
    $('ifrm_upload').destroy();
}

/**
 * Removes the avatar
 */
function removeAvatar() {
    $('avatar').value = '';
    $('frm_avatar').reset();
    $('image').src = 'gadgets/Users/images/avatar.png';
}

/**
 * Stops doing a certain action
 */
function stopUserAction()
{
    $('uid').value = 0;
    currentAction = 'UserAccount';
    unselectGridRow('users_datagrid');
    $('legend_title').innerHTML  = addUser_title;
    $('user_workarea').innerHTML = cachedUserForm;
    initDatePicker('expiry_date');
}

/**
 * Edit group
 */
function editGroup(rowElement, gid)
{
    $('gid').value = gid;
    currentAction = 'Group';
    $('legend_title').innerHTML   = editGroup_title;
    $('group_workarea').innerHTML = cachedGroupForm;
    selectGridRow('groups_datagrid', rowElement.parentNode.parentNode);

    var gInfo = UsersAjax.callSync('getgroup', gid);
    $('name').value        = gInfo['name'];
    $('title').value       = gInfo['title'].defilter();
    $('description').value = gInfo['description'].defilter();
    $('enabled').value     = Number(gInfo['enabled']);
}

/**
 * edit group-ACL keys
 */
function editGroupACL(rowElement, gid)
{
    $('gid').value = gid;
    currentAction = 'GroupACL';
    $('legend_title').innerHTML  = editGroupACL_title;
    var aclKeys = UsersAjax.callSync('getgroupaclkeys', gid);
    $('group_workarea').innerHTML = convertToTree(aclKeys);
    selectGridRow('groups_datagrid', rowElement.parentNode.parentNode);
    changedACLs = [];
}

/**
 * Edit the members of group
 */
function editGroupUsers(rowElement, gid)
{
    $('gid').value = gid;
    currentAction = 'GroupUsers';
    $('legend_title').innerHTML  = editGroupUsers_title;
    if (cachedGroupUsersForm == null) {
        cachedGroupUsersForm = UsersAjax.callSync('groupusersui');
    }
    $('group_workarea').innerHTML = cachedGroupUsersForm;
    selectGridRow('users_datagrid', rowElement.parentNode.parentNode);

    var gUsers = UsersAjax.callSync('getgroupusers', gid);
    gUsers.each(function(user, index) {
        if ($('user_' + user['id'])) {
            $('user_' + user['id']).checked = true;
        }
    });
}

/**
 * Saves data / changes on the group's form
 */
function saveGroup()
{
    switch(currentAction) {
        case 'Group':
            if ($('name').value.blank() || $('title').value.blank()) {
                alert(incompleteGroupFields);
                return false;
            }

            if ($('gid').value == 0) {
                UsersAjax.callAsync('addgroup', 
                                    $('name').value,
                                    $('title').value,
                                    $('description').value,
                                    $('enabled').value);
            } else {
                UsersAjax.callAsync('updategroup',
                                    $('gid').value,
                                    $('name').value,
                                    $('title').value,
                                    $('description').value,
                                    $('enabled').value);
            }

            break;

        case 'GroupACL':
            UsersAjax.callAsync('updategroupacl', $('gid').value, changedACLs);
            break;

        case 'GroupUsers':
            var inputs  = $('group_workarea').getElementsByTagName('input');
            var keys    = new Array();
            var counter = 0;
            for (var i=0; i<inputs.length; i++) {
                if (inputs[i].name.indexOf('group_users') == -1) {
                    continue;
                }

                if (inputs[i].checked) {
                    keys[counter] = inputs[i].value;
                    counter++;
                }
            }

            UsersAjax.callAsync('adduserstogroup', $('gid').value, keys);
            break;
    }

}

/**
 * Stops doing a certain action
 */
function stopGroupAction()
{
    $('gid').value = 0;
    currentAction = 'Group';
    unselectGridRow('groups_datagrid');
    $('legend_title').innerHTML   = addGroup_title;
    $('group_workarea').innerHTML = cachedGroupForm;
}

/**
 * Converts an ACL struct (array) to an xtree obj returning its XHTML content
 */
function convertToTree(keys)
{
    var imageCheck = 'gadgets/Users/images/checkbox.png',
        imageAllow = 'gadgets/Users/images/check-allow.png',
        imageDeny  = 'gadgets/Users/images/check-deny.png',
        legend     = 
            '(<img src="' + imageAllow + '" />' + permissionAllow +
            ' <img src="' + imageDeny  + '" />' + permissionDeny  +
            ' <img src="' + imageCheck + '" />' + permissionNone  + ')';

    tree = new WebFXTree('<strong>' + permissionsMsg + '</strong> ' + legend);
    for (gadget in keys) {
        if (typeof(keys[gadget]) == 'function') {
            continue;
        }
        var gadgetItem = new WebFXTreeItem(keys[gadget]['name']);

        for (aclKey in keys[gadget]) {
            if (keys[gadget][aclKey]['desc'] == undefined) {
                continue;
            }

            switch (keys[gadget][aclKey]['value']) {
                case null:
                    value = 0;
                    image = imageCheck;
                    break;
                case true:
                    value = 1;
                    image = imageAllow;
                    break;
                case false:
                    value = -1;
                    image = imageDeny;
                    break;
            }

            // Creates 3 state checkbox with its label and all that nice stuff
            var div   = new Element('div'),
                img   = new Element('img'),
                label = new Element('label').set('html', keys[gadget][aclKey]['desc']);
            img.setProperty('id', keys[gadget][aclKey]['name']);
            img.setProperty('alt', value);
            img.setProperty('src', image);
            div.adopt(img);
            div.adopt(label);

            var aclItem = new WebFXTreeItem(div.innerHTML, "javascript:onACLNodeClick('" + keys[gadget][aclKey]['name'] + "')");
            gadgetItem.add(aclItem);
        }
        tree.add(gadgetItem);
    }
    return tree.toString();
}

/**
 * Changes the status of ACL checkbox
 */
function onACLNodeClick(imgID)
{
    var img   = $(imgID),
        value = img.getProperty('alt');
    switch (value) {
        case '0':
            img.alt = 1;
            img.src = 'gadgets/Users/images/check-allow.png';
            changedACLs[imgID] = true;
            break;
        case '1':
            img.alt = -1;
            img.src = 'gadgets/Users/images/check-deny.png';
            changedACLs[imgID] = false;
            break;
        case '-1':
            img.alt = 0;
            img.src = 'gadgets/Users/images/checkbox.png';
            changedACLs[imgID] = null;
            break;
    }
}

/**
 * Save settings
 */
function saveSettings()
{
    var method     = $('auth_method').value;
    var anon       = $('anon_register').value;
    var repetitive = $('anon_repetitive_email').value;
    var act        = $('anon_activation').value;
    var group      = $('anon_group').value;
    var recover    = $('password_recovery').value;

    UsersAjax.callAsync('savesettings', method, anon, repetitive, act, group, recover);
}

/**
 * Update myAccount
 */
function updateMyAccount()
{
    if ($('pass1').value != $('pass2').value) {
        alert(wrongPassword);
        return false;
    }

    if ($('username').value.blank() ||
        $('nickname').value.blank() ||
        $('email').value.blank())
    {
        alert(incompleteUserFields);
        return false;
    }

    if ($('exponent')) {
        encryptedElement($('pass1'), $('exponent').value, $('modulus').value, true, 128);
        $('pass2').value = $('pass1').value;
    }
    UsersAjax.callAsync('updatemyaccount',
                        $('uid').value,
                        $('username').value,
                        $('pass1').value,
                        $('nickname').value,
                        $('email').value);
}

var UsersAjax = new JawsAjax('Users', UsersCallback);

// timeout id
var fTimeout = null;
    
//current group
var selectedGroup = null;
//show all users
var showAll = false;
//Combo colors
var evenColor = '#fff';
var oddColor  = '#edf3fe';

//Cached form variables
var cachedPersonalForm    = null,
    cachedPreferencesForm = null,
    cachedUserGroupsForm  = null,
    cachedGroupUsersForm  = null;

//Cache for saving the group|user-form template
var cacheForm = null;
//Cache for group-user management
var cacheUserGroupForm = null;

//Which action are we runing?
var currentAction = null;
//We already loaded the xtree lib?
var xtreeLoaded = false;
//xtree obj
var xtree = null;

var changedACLs = [];
