<?php
/**
 * @package pragyan
 * @copyright (c) 2008 Pragyan Team
 * @license http://www.gnu.org/licenses/ GNU Public License
 * For more details, see README
 */
/**
 * Form doesnt have ability to associate or unassociate with a group.
 * That is done through groups.
 *
 * If it is not associated with a group
 * 		When associated, copy all regiestered users to groups table -> done by groups
 * 		Have a function which returns userids of all users registered in a form.
 * *
 * If login required is changed from off to on at any time,
 * 		then give warning, and delete all anonymous entries.
 *
 * If loginrequired is turned from on to off, allow only if it is not associted with a group
 * give error msg with the group namem and don't allow him
 *
 * To put deleteall registrants in edit registrants
 *
 *TODO: one maintenance script : If user doesn't exist in form_regdata, remove from form_elementdata
 *
 */
 
 /*
  * TODO:: 
  * 
  * 
  * URGENT:: Send user a confirmation mail on registration
  * 
  * 
  * 
  * */
$database="festember";
$sqlhost="localhost";
$sqluser="paramesh";
$sqlpass="pass";
$ERRORSTRING="";
$INFOSTRING="";
$WARNINGSTRING="";
define("MYSQL_DATABASE_PREFIX","festember_");
$mcid=0;if(isset($_POST['mcid']))$mcid=$_POST['mcid'];if(isset($_SESSION['mcid']))$mcid=$_SESSION['mcid'];
$userId=1;if(isset($_POST['userId']))$userId=$_POST['userId'];if(isset($_SESSION['userId']))$userId=$_SESSION['userId'];
$con=mysql_connect($sqlhost,$sqluser,$sqlpass);
mysql_select_db($database);

require_once("form/common.lib.php");
require_once("form/editform.php");
require_once("form/editformelement.php");
require_once("form/registrationformgenerate.php");
require_once("form/registrationformsubmit.php");
require_once("form/viewregistrants.php");

if(isset($_GET['action']))
$action=$_GET['action'];


		
		if($action=="editform")
		 $content=actionEditform();
		else if($action=="viewregistrants")
		 $content=actionViewregistrants();
		/*
		else if($action=="editregistrants")
		 $content=actionEditregistrants();
		else if($action=="reports")
		 $content=actionReports();
		*/
		else $content=actionView();



		echo "<div id='toplinks' style='position:absolute;top:20px;right:200px'>
		<a href='./'>View</a>
		<a href='./?action=editform'>Edit Form</a>
		<a href='./?action=viewregistrants'>View Registrants</a>
		</div>
		<div id='page-content'> $ERRORSTRING.$INFOSTRING.$WARNINGSTRING.$OTHER.$content </div>";
		/* Options to be displayed :
		 *
		 * View registrants				View the ppl who hav registered (without permission to edit)
		 * Edit registrants				View the ppl who hav registered (with edit permission)
		 * Edit form structure			Edit the structure of fields and their attributes
		 * Register						Show registration page (View function)
		 */


	 function getFileAccessPermission($pageId,$moduleComponentId,$userId, $fileName) {
		/* in view, if its one of his files, show him */
		if(getPermissions($userId,$pageId,"editregistrants")||getPermissions($userId,$pageId,"viewregistrants")) {
			return true;
		}
		$uploadedQuery = "SELECT `d.form_elementdata`
FROM `form_elementdata` d
	JOIN `form_elementdesc` e ON (`d.page_modulecomponentid` = `e.page_modulecomponentid`
		AND d.form_elementid = e.form_elementid )
WHERE `d.page_modulecomponentid` = '$moduleComponentId' AND `d.user_id` = '$userId' AND `d.form_elementdata` = \"$fileName\"";
		$uploadedResult = mysql_query($uploadedQuery) or displayerror(mysql_error() . "form.lib L:181");
		if(mysql_num_rows($uploadedResult)>0 && getPermissions($userId, $pageId, "view"))
			return true;
		else return false;
	}

	 function getUploadableFileProperties(&$fileTypesArray,&$maxFileSizeInBytes) {
		$fileTypesArray = array('jpg','jpeg','png','doc','pdf','gif','bmp','css','js','html','xml','ods','odt','oft','pps','ppt','tex','tiff','txt','chm','mp3','mp2','wave','wav','mpg','ogg','mpeg','wmv','wma','wmf','rm','avi','gzip','gz','rar','bmp','psd','bz2','tar','zip','swf','fla','flv','eps','xcf','xls','exe','7z');
		$maxFileSizeInBytes = 30*1024*1024;
	}

	 function actionView() {
		/**
		 * Find from db if registration form is editable,
		 *
		 * that is 1) Expiry date has not passed and 2) form_allowuseredit is true
		 * 3) If form is of type allow logged in users only then make sure user is logged in
		 *
		 * We will do captcha and send confirmation once everything else is done.
		 */
		global $sourceFolder;		global $moduleFolder;global $mcid;global $userId;

		$formDescQuery='SELECT `form_loginrequired`, `form_expirydatetime`, (NOW() >= `form_expirydatetime`) AS `form_expired`, `form_sendconfirmation`, ' .
				'`form_usecaptcha`, `form_allowuseredit`, `form_allowuserunregister`, `form_closelimit` ' .
				'FROM `form_desc` WHERE `page_modulecomponentid`='."'".$mcid."'";
		$formDescResult=mysql_query($formDescQuery);
		
		if (!$formDescResult) {
			displayerror('E69 : Invalid query: ' . mysql_error());
			return '';
		}
		$formDescRow = mysql_fetch_assoc($formDescResult);

		if($formDescRow['form_loginrequired'] == 1) {
			if($userId <= 0) {
				displayerror('You must be logged in to fill this form. <a href="./+login">Click here</a> to login.');
				return '';
			}
			/* Check if the user has a completed profile. Otherwise, prompt to complete.
			else if(!verifyUserProfileFilled($userId)) {
				displayinfo('Your profile information is incomplete. Please complete your profile information before filling this form. <a href="./+profile">Click here</a> to complete your profile.');
				return '';
			}
			*/
		}

		if($formDescRow['form_expired'] != 0 && $formDescRow['form_expirydatetime']!="0000-00-00 00:00:00") {
			displayerror('The last date to register to this form ('.$formDescRow['form_expirydatetime'].') is over.');
			return '';
		}
		if($formDescRow['form_allowuseredit']==0 &&  verifyUserRegistered($mcid,$userId)) {
			displayerror('You have already registered to this form once. You cannot register again. Contact the administrator for further queries.');
			return '';
		}
		//print_r($formDescRow['f']);
		if($formDescRow['form_closelimit']!= '-1'){
			$usersRegisteredQuery = " SELECT COUNT( * ) FROM `form_regdata` WHERE `page_modulecomponentid` ='".$mcid."'";
			
			echo $usersRegisteredQuery;
			$usersRegisteredResult = mysql_fetch_array(mysql_query($usersRegisteredQuery));
			if(($usersRegisteredResult[0]>=$formDescRow['form_closelimit'])&&(!verifyUserRegistered($mcid,$userId))){
				displayerror('Form registration limit has been reached.');
				return '';	
			}
			
		}

		if(isset($_POST['submitreg_form_'.$mcid])){
			submitRegistrationForm($mcid,$userId);
			if($formDescRow['form_allowuseredit']==0)
				return;
			
		}
		if($formDescRow['form_allowuserunregister'] == 1 && isset($_GET['subaction'])&&($_GET['subaction']=="unregister"))
			unregisterUser($mcid,$userId);

		$unregisterBody = '';
		if($formDescRow['form_allowuserunregister'] == 1 && verifyUserRegistered($mcid, $userId)) {
			$unregisterBody =
						'<br /><p>If you wish to unregister from this form, click here : <input type="button" ' .
						'value = "Unregister" onclick="if(confirm(\'Are you sure you want to unregister from this form?\')) window.location=\'./&subaction=unregister\';" />';
		}

		return generateRegistrationForm($mcid,$userId).$unregisterBody;
	}

	/**
	 * Determines User Ids of all users registered to a form
	 * @param $moduleComponentId Module Component Id of the form
	 * @return Array of User Ids of all the users registered to the form with the given Module Component Id
	 */
	function getRegisteredUserArray($moduleComponentId) {
		$userQuery = "SELECT `user_id` FROM `form_regdata` WHERE `page_modulecomponentid` = '$moduleComponentId'";
		$userResult = mysql_query($userQuery);
		$registeredUsers = array();
		while($userRow = mysql_fetch_row($userResult))
			$registeredUsers[] = $userRow[0];
		return $registeredUsers;
	}

	 function getRegisteredUserCount($moduleComponentId) {
		$userQuery = "SELECT COUNT(`user_id`) FROM `form_regdata` WHERE `page_modulecomponentid` = '$moduleComponentId'";
		$userResult = mysql_query($userQuery);
		$userRow = mysql_fetch_row($userResult);
		return $userRow[0];
	}

	 function isGroupAssociable($moduleComponentId) {
		$validQuery = 'SELECT `form_loginrequired`, `form_allowuserunregister` FROM `form_desc` WHERE `page_modulecomponentid` = ' ."'". $moduleComponentId."'";
		$validResult = mysql_query($validQuery);
		$validRow = mysql_fetch_row($validResult);

		if(!$validResult || !$validRow) {
			displayerror('Error trying to retrieve data from the database: form.lib.php:L163');
			return false;
		}

		return $validRow[0];
	}

	/**
	 * Performs the action "Edit Form" on a form
	 */
	 function actionEditform() {
		global $sourceFolder;		global $moduleFolder;global $mcid;

		if(
			isset($_GET['subaction']) && $_GET['subaction'] == 'editformelement' &&
			isset($_POST['elementid']) && ctype_digit($_POST['elementid']) &&
			isset($_POST['txtElementDesc']) && isset($_POST['selElementType']) &&
			isset($_POST['txtToolTip']) && isset($_POST['txtElementName'])
			)
			submitEditFormElementDescData($mcid,escape($_POST['elementid']));

		if(
			isset($_GET['subaction']) && ($_GET['subaction']=='editformelement')&&
			isset($_GET['elementid']) && ctype_digit($_GET['elementid'])
			)
			return generateEditFormElementDescBody($mcid,escape($_GET['elementid']));
		if(isset($_POST['addformelement_descsubmit']))
			addDefaultFormElement($mcid);
		if(isset($_GET['subaction'])&&($_GET['subaction']=='deleteformelement')&&isset($_GET['elementid']))
			deleteFormElement($mcid,escape($_GET['elementid']));
		if(isset($_GET['subaction'])&&(($_GET['subaction']=='moveUp')||($_GET['subaction']=='moveDown'))&&isset($_GET['elementid']))
			moveFormElement($mcid,escape($_GET['subaction']),escape($_GET['elementid']));

		$html = generateFormDescBody($mcid).generateFormElementDescBody($mcid);
		global $ICONS;
		return "<fieldset><legend>{$ICONS['Form Edit']['small']}Edit Form</legend>$html</fieldset>";
	}

	 function actionViewregistrants() {
		global $sourceFolder, $moduleFolder;
		global $mcid;
		$sortField = 'registrationdate'; /// Default Values
		$sortOrder = 'asc';
		if(isset($_GET['sortfield']))
			$sortField = escape($_GET['sortfield']);
		if(isset($_GET['sortorder']) && ($_GET['sortorder'] == 'asc' || $_GET['sortorder'] == 'desc'))
			$sortOrder = escape($_GET['sortorder']);
		global $ICONS;
		$html= generateFormDataTable($mcid, $sortField, $sortOrder);
		return "<fieldset><legend>{$ICONS['Form Registrants']['small']}View Form Registrants</legend>
		<form action='./+viewregistrants' method='POST'>
		<input type='submit' name='save_as_excel' value='Save as Excel'/>
		</form>
		$html</fieldset>";
	}

	 function actionEditregistrants() {
		/**
		 * After view registrants completes, generateFormDataRow() will be used here also
		 * then manually prepend the "delete" button and "edit" button columns
		 *
		 * After  generateFormDataTable() completes, move the code to generate the top header row (with the sort
		 * 			by this column images) to a new function and call it here also and manually prepened two <th>s of
		 * 			delete and edit
		 *
		 * The delete button should point to a subaction through get vars
		 *
		 * Clicking edit button, should do something like edit in 2nd form in "editform" does with a twist :
		 *	to generate the edit form simply call generateRegistrationForm($moduleCompId,$userId,$action=)
		 *  with action ./+editregistrants&subaction=editregistrant&useremail=<useremail>
		 *
		 * and when submitted call submitRegistrationForm()
		 */
		global $sourceFolder, $moduleFolder;

		if(isset($_GET['subaction']) && isset($_GET['useremail'])) {
			if($_GET['subaction'] == 'edit') {
				if(isset($_POST['submitreg_form_' . $mcid])) {
					submitRegistrationForm($mcid, getUserIdFromEmail(escape($_GET['useremail'])), true, true);
				}

				return (
						'<a href="./+editregistrants">&laquo; Back</a><br />' .
						generateRegistrationForm($mcid, $userId, './+editregistrants&subaction=edit&useremail=' . escape($_GET['useremail']), true) .
						'<br /><a href="./+editregistrants">&laquo; Back</a><br />'
				);
			}
			elseif($_GET['subaction'] == 'delete') {
				if($_GET['useremail']=="Anonymous")
					$userIdTemp = escape($_GET['registrantid']);
				else
					$userIdTemp = getUserIdFromEmail(escape($_GET['useremail']));
				if(!unregisterUser($mcid, $userIdTemp))
					displayerror('Error! User with the given e-mail ' . escape($_GET['useremail']) . ' was not found.');
			}
		}
		elseif(isset($_GET['subaction']) && $_GET['subaction'] == 'getsuggestions' && isset($_GET['forwhat'])) {
			echo $this->getUnregisteredUsersFromPattern(escape($_GET['forwhat']));
			disconnect();
			exit();
		}
		elseif(isset($_POST['btnAddUserToForm']) && isset($_POST['useremail'])) {
			$hyphenPos = strpos($_POST['useremail'], '-');
			if($hyphenPos >= 0) {
				$userEmail = escape(trim(substr($_POST['useremail'], 0, $hyphenPos - 1)));
			}
			else {
				$userEmail = escape($_POST['useremail']);
			}

			$targetUserId = getUserIdFromEmail($userEmail);
			if($targetUserId > 0) {
				if(verifyUserRegistered($mcid, $targetUserId)) {
					displayerror('The given user is already registered to this form.');
				}
				else {
					registerUser($mcid, $targetUserId);
				}
			}
			else {
				displayerror('A user registered with the e-mail ID you entered was not found.');
			}
		}
		elseif(isset($_POST['btnEmptyRegistrants'])) {
			$registeredUsers = form::getRegisteredUserArray($mcid);
			$registeredUserCount = count($registeredUsers);
			for($i = 0; $i < $registeredUserCount; $i++) {
				unregisterUser($mcid, $registeredUsers[$i], true);
			}
			displayinfo('All registrations to this form have been deleted.');
		}

		$sortField = 'registrationdate'; /// Default Values
		$sortOrder = 'asc';
		if(isset($_GET['sortfield']))
			$sortField = escape($_GET['sortfield']);
		if(isset($_GET['sortorder']) && ($_GET['sortorder'] == 'asc' || $_GET['sortorder'] == 'desc'))
			$sortOrder = escape($_GET['sortorder']);
		global $ICONS;
		$html= generateFormDataTable($mcid, $sortField, $sortOrder, 'editregistrants');
		return "<fieldset><legend>{$ICONS['Form Registrants']['small']}Edit Form Registrants</legend>$html</fieldset>";
	}
	
	 function actionReports() {
		 global $userId,$urlRequestRoot;
		 $query = "SELECT `page_id`, `page_modulecomponentid` FROM `".MYSQL_DATABASE_PREFIX."pages` WHERE `page_module`='form'";
		 $resource = mysql_query($query);
		 $report=<<<CSS
		  <style type="text/css">
		  
    #reports tbody tr.even td {
      background-color: #f0f8ff;
      color: #000;
    }
    #reports tbody tr td a, a:link, a:visited {
    	color: #000;
    }
    #reports tbody tr.odd  td {
      background-color: #fff;color: #000;
    }
  </style>
CSS;
			$report .='<table id="reports"><tbody><tr><td>Form</td><td>No. of registrants</td></tr>'; 
			$class = 'even';
		 while($result = mysql_fetch_assoc($resource)) {
		 	$permission = getPermissions($userId,$result[page_id],'viewRegistrant','form');
			if($permission) {
				$pageId = $result['page_id'];
				$parentPageId = getParentPage($pageId);
				$parentTitle = getPageTitle($parentPageId);
				$formTitle = getPageTitle($pageId);
				$formInfo = $parentTitle.'_'.$formTitle;
				$formPath = getPagePath($pageId);
				$query = "SELECT count(distinct(`user_id`)) FROM `form_regdata` WHERE `page_modulecomponentid`='$result[page_modulecomponentid]'";
				$resource2 = mysql_query($query) ;//or die(mysql_error());
				$result2 = mysql_fetch_row($resource2);
				
				if(!strpos($formPath,'qaos'))
				{
					if($class=='even')
						{
							$class='odd';
						}
					else {
						$class = 'even';
					}
				$report .= "<tr class=\"$class\"><td><a href=\"$urlRequestRoot$formPath\">$formInfo</a></td><td>$result2[0]</td></tr>";
				}
			}
		 }
			$report .='</tbody></table>';
		 return $report;
	}

	function getUnregisteredUsersFromPattern($pattern) {
		$registeredUserArray = form::getRegisteredUserArray($mcid);
		if(count($registeredUserArray) > 0) {
			$registeredUserArray = implode(',', $registeredUserArray);
		}
		else {
			$registeredUserArray = '0';
		}
		$suggestionsQuery = "SELECT IF(`user_email` LIKE \"$pattern%\", 1, " .
			"IF(`user_fullname` LIKE \"$pattern%\", 2, " .
			"IF(`user_fullname` LIKE \"% $pattern%\", 3, " .
			"IF(`user_email` LIKE \"%$pattern%\", 4, " .
			"IF(`user_fullname` LIKE \"%$pattern%\", 5, 6" .
			"))))) AS `relevance`,	`user_email`, `user_fullname` FROM `".MYSQL_DATABASE_PREFIX."users` WHERE " .
			"`user_activated` = 1 AND (`user_email` LIKE \"%$pattern%\" OR `user_fullname` LIKE \"%$pattern%\") " .
			"AND `user_id` NOT IN ($registeredUserArray) ORDER BY `relevance`";
		$suggestionsResult = mysql_query($suggestionsQuery);
		if(!$suggestionsResult) return $pattern;

		$suggestions = array($pattern);
		while($suggestionsRow = mysql_fetch_row($suggestionsResult)) {
			$suggestions[] = $suggestionsRow[1] . ' - ' . $suggestionsRow[2];
		}

		return join($suggestions, ',');
	}

	function createModule($moduleComponentId) {
		global $sourceFolder, $moduleFolder;
		$query = "INSERT INTO `form_desc` (`page_modulecomponentid`, `form_heading`,`form_loginrequired`,`form_headertext`)
					VALUES ('".$moduleComponentId."', '',1,'Coming up Soon');";
		$result = mysql_query($query) or die(mysql_error()."form.lib L:157");
		addDefaultFormElement($moduleComponentId);
	}