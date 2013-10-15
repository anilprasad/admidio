<?php
/******************************************************************************
 * Save profile/registration data
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * user_id    : ID of the user who should be edited
 * new_user   : 0 - Edit user of the user id
 *              1 - Create a new user
 *              2 - Create a registration
 *              3 - assign/accept a registration
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getUserId  = admFuncVariableIsValid($_GET, 'user_id', 'numeric', 0);
$getNewUser = admFuncVariableIsValid($_GET, 'new_user', 'numeric', 0);

// if current user has no login then only show registration dialog
if($gValidLogin == false)
{
    $getNewUser = 2;
}

// save form data in session for back navigation
$_SESSION['profile_request'] = $_POST;

if(!isset($_POST['usr_login_name']))
{
    $_POST['usr_login_name'] = '';
}
if(!isset($_POST['reg_org_id']))
{
    $_POST['reg_org_id'] = $gCurrentOrganization->getValue('org_id');
}

// read user data
if($getNewUser == 2 || $getNewUser == 3)
{
    // create user registration object and set requested organization
	$user = new UserRegistration($gDb, $gProfileFields, $getUserId);
	$user->setOrganization($_POST['reg_org_id']);
}
else
{
	$user = new User($gDb, $gProfileFields, $getUserId);
}

// pruefen, ob Modul aufgerufen werden darf
switch($getNewUser)
{
    case 0:
        // prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
        if($gCurrentUser->editProfile($user) == false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
        break;

    case 1:
        // prueft, ob der User die notwendigen Rechte hat, neue User anzulegen
        if($gCurrentUser->editUsers() == false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
        break;

    case 2:
    case 3:
        // Registrierung deaktiviert, also auch diesen Modus sperren
        if($gPreferences['registration_mode'] == 0)
        {
            $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
        }
        break;
}

/*------------------------------------------------------------*/
// Feldinhalte pruefen der User-Klasse zuordnen
/*------------------------------------------------------------*/

// bei Registrierung muss Loginname und Pw geprueft werden
if($getNewUser == 2)
{
    if(strlen($_POST['usr_login_name']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_USERNAME')));
    }

    // Passwort sollte laenger als 6 Zeichen sein
    if(strlen($_POST['usr_password']) < 6)
    {
        $gMessage->show($gL10n->get('PRO_PASSWORD_LENGTH'));
    }

    // beide Passwortfelder muessen identisch sein
    if ($_POST['usr_password'] != $_POST['password2'])
    {
        $gMessage->show($gL10n->get('PRO_PASSWORDS_NOT_EQUAL'));
    }

    if(strlen($_POST['usr_password']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_PASSWORD')));
    }
}

// nun alle Profilfelder pruefen
foreach($gProfileFields->mProfileFields as $field)
{
    $post_id = 'usf-'. $field->getValue('usf_id');    
    
	// check and save only fields that aren't disabled
	if($gCurrentUser->editUsers() == true || $field->getValue('usf_disabled') == 0 || ($field->getValue('usf_disabled') == 1 && $getNewUser > 0))
	{
		if(isset($_POST[$post_id])) 
		{
			// Pflichtfelder muessen gefuellt sein
			// E-Mail bei Registrierung immer !!!
			if(($field->getValue('usf_mandatory') == 1 && strlen($_POST[$post_id]) == 0)
			|| ($getNewUser == 2 && $field->getValue('usf_name_intern') == 'EMAIL' && strlen($_POST[$post_id]) == 0))
			{
				$gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $field->getValue('usf_name')));
			}
			
			// if social network then extract username from url
			if($field->getValue('usf_name_intern') == 'FACEBOOK'
			|| $field->getValue('usf_name_intern') == 'GOOGLE_PLUS'
			|| $field->getValue('usf_name_intern') == 'TWITTER'
			|| $field->getValue('usf_name_intern') == 'XING')
			{
				if(strValidCharacters($_POST[$post_id], 'url')
    			&& strpos($_POST[$post_id], '/') !== false)
				{
					if(strrpos($_POST[$post_id], '/profile.php?id=') > 0)
					{
						// extract facebook id (not facebook unique name) from url
						$_POST[$post_id] = substr($_POST[$post_id], strrpos($_POST[$post_id], '/profile.php?id=') + 16);
					}
					else
					{
						if(strrpos($_POST[$post_id], '/posts') > 0)
						{
							$_POST[$post_id] = substr($_POST[$post_id], 0, strrpos($_POST[$post_id], '/posts'));
						}
						
						$_POST[$post_id] = substr($_POST[$post_id], strrpos($_POST[$post_id], '/') + 1);
						if(strrpos($_POST[$post_id], '?') > 0)
						{
						   $_POST[$post_id] = substr($_POST[$post_id], 0, strrpos($_POST[$post_id], '?'));
						}
					}
				}
			}

			// Wert aus Feld in das User-Klassenobjekt schreiben
			$returnCode = $user->setValue($field->getValue('usf_name_intern'), $_POST[$post_id]);
			
			// Ausgabe der Fehlermeldung je nach Datentyp
			if($returnCode == false)
			{
				if($field->getValue('usf_type') == 'CHECKBOX')
				{
					$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
				}
				elseif($field->getValue('usf_type') == 'DATE')
				{
					$gMessage->show($gL10n->get('SYS_DATE_INVALID', $field->getValue('usf_name'), $gPreferences['system_date']));
				}
				elseif($field->getValue('usf_type') == 'EMAIL')
				{
					$gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $field->getValue('usf_name')));
				}
				elseif($field->getValue('usf_type') == 'NUMERIC')
				{
					$gMessage->show($gL10n->get('PRO_FIELD_NUMERIC', $field->getValue('usf_name')));
				}
				elseif($field->getValue('usf_type') == 'URL')
				{
					$gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', $field->getValue('usf_name')));
				}
			}
		}
		else
		{
			// Checkboxen uebergeben bei 0 keinen Wert, deshalb diesen hier setzen
			if($field->getValue('usf_type') == 'CHECKBOX')
			{
				$user->setValue($field->getValue('usf_name_intern'), '0');
			}
			elseif($field->getValue('usf_mandatory') == 1)
			{
				$gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $field->getValue('usf_name')));
			}
		}
	}
}

$login_name_changed = false;
$forum_old_username = '';

if($gCurrentUser->isWebmaster() || $getNewUser > 0)
{
    // Loginname darf nur vom Webmaster bzw. bei Neuanlage geaendert werden    
    if($_POST['usr_login_name'] != $user->getValue('usr_login_name'))
    {
        if(strlen($_POST['usr_login_name']) > 0)
        {
            // pruefen, ob der Benutzername bereits vergeben ist
            $sql = 'SELECT usr_id FROM '. TBL_USERS. '
                     WHERE usr_login_name LIKE \''. $_POST['usr_login_name']. '\'';
            $gDb->query($sql);

            if($gDb->num_rows() > 0)
            {
                $row = $gDb->fetch_array();

                if(strcmp($row['usr_id'], $getUserId) != 0)
                {
                    $gMessage->show($gL10n->get('PRO_LOGIN_NAME_EXIST'));
                }
            }

            // pruefen, ob der Benutzername bereits im Forum vergeben ist, 
            // Benutzernamenswechsel und diese Dinge
            if($gPreferences['enable_forum_interface'])
            {
                // pruefen, ob der Benutzername bereits im Forum vergeben ist
                if($gForum->userExists($_POST['usr_login_name']))
                {
                    $gMessage->show($gL10n->get('SYS_FORUM_USER_EXIST'));
                }
                
                // bisherigen Loginnamen merken, damit dieser spaeter im Forum geaendert werden kann
                $forum_old_username = '';
                if(strlen($user->getValue('usr_login_name')) > 0)
                {
                    $forum_old_username = $user->getValue('usr_login_name');
                }
            }
        }

        $login_name_changed = true;
        if(!$user->setValue('usr_login_name', $_POST['usr_login_name']))
		{
			$gMessage->show($gL10n->get('SYS_FIELD_INVALID_CHAR', $gL10n->get('SYS_USERNAME')));
		}
    }    
}

// falls Registrierung, dann die entsprechenden Felder noch besetzen
if($getNewUser == 2)
{
    $user->setValue('usr_password', $_POST['usr_password']);
}


// Falls der User sich registrieren wollte, aber ein Captcha geschaltet ist,
// muss natuerlich der Code ueberprueft werden
if ($getNewUser == 2 && $gPreferences['enable_registration_captcha'] == 1)
{
    if ( !isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) != admStrToUpper($_POST['captcha']) )
    {
		if($gPreferences['captcha_type']=='pic') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CODE_INVALID'));}
		else if($gPreferences['captcha_type']=='calc') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));}
    }
}

/*------------------------------------------------------------*/
// Benutzerdaten in Datenbank schreiben
/*------------------------------------------------------------*/
$gDb->startTransaction();

try
{
    // save changes; if it's a new registration than caught exception if email couldn't send

    if($user->getValue('usr_id') == 0)
    {
        // der User wird gerade angelegt und die ID kann erst danach in das Create-Feld gesetzt werden
        $user->save();
    
        if($getNewUser == 1)
        {
            $user->setValue('usr_usr_id_create', $gCurrentUser->getValue('usr_id'));
        }
        else
        {
            $user->setValue('usr_usr_id_create', $user->getValue('usr_id'));
        }
    }

    $ret_code = $user->save();
}
catch(AdmException $e)
{
    unset($_SESSION['profile_request']);
    $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
	$e->showHtml();
}

// wurde der Loginname vergeben oder geaendert, so muss ein Forumaccount gepflegt werden
// bei einer Bestaetigung der Registrierung muss der Account aktiviert werden
if($gPreferences['enable_forum_interface'] && ($login_name_changed || $getNewUser == 3))
{
    $set_admin = false;
    if($gPreferences['forum_set_admin'] == 1 && $user->isWebmaster())
    {
        $set_admin = true;
    }
    $gForum->userSave($user->getValue('usr_login_name'), $user->getValue('usr_password'), $user->getValue('EMAIL'), $forum_old_username, $getNewUser, $set_admin);
}

$gDb->endTransaction();

// wenn Daten des eingeloggten Users geaendert werden, dann Session-Variablen aktualisieren
if($user->getValue('usr_id') == $gCurrentUser->getValue('usr_id'))
{
    $gCurrentUser = $user;
}

unset($_SESSION['profile_request']);
$gNavigation->deleteLastUrl();

/*------------------------------------------------------------*/
// je nach Aufrufmodus auf die richtige Seite weiterleiten
/*------------------------------------------------------------*/

if($getNewUser == 1 || $getNewUser == 3)
{
	// assign a registration or create a new user

	if($getNewUser == 3)
	{
        try
        {
    		// accept a registration, assign neccessary roles and send a notification email
    		$user->acceptRegistration();
    		$messageId = 'PRO_ASSIGN_REGISTRATION_SUCCESSFUL';
        }
        catch(AdmException $e)
        {
            $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
        	$e->showHtml();
        }
	}
	else
	{
		// a new user is created with the user management module
		// then the user must get the neccessary roles
		$user->assignDefaultRoles();
		$messageId = 'SYS_SAVE_DATA';
	}
	
	// if current user has the right to assign roles then show roles dialog
	// otherwise go to previous url (default roles are assigned automatically)
	if($gCurrentUser->assignRoles())
	{
		header('Location: roles.php?usr_id='. $user->getValue('usr_id'). '&new_user='.$getNewUser);
		exit();
	}
	else
	{
		$gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
		$gMessage->show($gL10n->get($messageId));
	}
}
elseif($getNewUser == 2)
{
    // registration was successful then go to homepage
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_REGISTRATION_SAVED'));
}
elseif($getNewUser == 0 && $user->getValue('usr_valid') == 0)
{
    // a registration was edited then go back to profile view
    $gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
else
{
    // go back to profile view
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
?>
