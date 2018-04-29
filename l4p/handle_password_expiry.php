<?php

require_once (__DIR__ . '/../adm_program/system/common.php');

require_once (__DIR__ . '/engine/bootstrap.php');

if ( $GLOBALS['gPreferences']['registration_mode'] == 0) {
	$GLOBALS['gMessage']->show($GLOBALS['gL10n']->get('SYS_MODULE_DISABLED'));
	// => EXIT
}

require_once (__DIR__ . '/../adm_program/system/common.php');
require_once (__DIR__ . '/../adm_program/system/login_valid.php');

require_once (__DIR__ . '/engine/bootstrap.php');

/**
 * handle request
 */
function handle_request () {
	
	# org
	$organisation_id = $GLOBALS['gCurrentOrganization']->getValue('org_id');
	
	# read user data
	$datum_user = $GLOBALS['gCurrentUser'];
	
	# safety
	if (\strcasecmp($datum_user->getValue('L4P_DB_TEMP_PASS_EXPIRATION'), \date('Y-m-d')) >= 0) {
		admRedirect(ADMIDIO_URL . '/adm_program/index.php');
		// => EXIT
	}
	
	if (\sizeof($_POST) > 0) {
		handle_request_post($organisation_id, $datum_user);
	} else {
		handle_request_get($organisation_id, $datum_user);
	}
}

/**
 * handle the form POST
 */
function handle_request_post ( $organisation_id, $datum_user ) {
	
	# regenrate password and save
	$autogenerated_password = \cantabnyc\auto_generate_password();
	
	$password_expires = \date('Y-m-d', \time() + \cantabnyc\get_configs()->expiry*24*60*60);
	
	$sql = 'UPDATE ' . \TBL_USER_DATA . ' SET usd_value=\'' . $password_expires       . '\' WHERE usd_usf_id = \'' . $GLOBALS['gProfileFields']->getProperty('L4P_DB_TEMP_PASS_EXPIRATION', 'usf_id') . '\' AND usd_usr_id=\'' . $datum_user->getValue('usr_id') . '\' LIMIT 1';
	$GLOBALS['gDb']->query( $sql );
	
	$sql = 'UPDATE ' . \TBL_USER_DATA . ' SET usd_value=\'' . $autogenerated_password . '\' WHERE usd_usf_id = \'' . $GLOBALS['gProfileFields']->getProperty('L4P_DB_TEMP_PASSWORD',        'usf_id') . '\' AND usd_usr_id=\'' . $datum_user->getValue('usr_id') . '\' LIMIT 1';
	$GLOBALS['gDb']->query( $sql );
	
	$sql = 'UPDATE ' . \TBL_USER_DATA . ' SET usd_value=\'2\'                               WHERE usd_usf_id = \'' . $GLOBALS['gProfileFields']->getProperty('L4P_DB_TEMP_PASS_CHANGED',    'usf_id') . '\' AND usd_usr_id=\'' . $datum_user->getValue('usr_id') . '\' LIMIT 1';
	$GLOBALS['gDb']->query( $sql );
	
	# logout
	$GLOBALS['gCurrentSession']->logout();
	
	# send the email
	$systemMail = new SystemMail($GLOBALS['gDb']);
	$systemMail->addRecipient($datum_user->getValue('EMAIL'), $datum_user->getValue('FIRST_NAME'). ' '. $datum_user->getValue('LAST_NAME'));
	$systemMail->sendSystemMail('SYSMAIL_REGISTRATION_USER', $datum_user);
	
	# message
	$GLOBALS['gMessage']->setForwardUrl( ADMIDIO_URL );
	$GLOBALS['gMessage']->show( $GLOBALS['gL10n']->get('L4P_PASSWORD_EXPIRY_MESSAGE') );
	// => EXIT
}

/**
 * build the page
 */
function handle_request_get ($organisation_id, $datum_user) {
	
	# set headline of the script
	$headline = $GLOBALS['gL10n']->get('L4P_PASSWORD_EXPIRY');
	
	$GLOBALS['gNavigation']->addUrl(CURRENT_URL, $headline);
	
	// create html page object
	$page = new HtmlPage($headline);
	
	$page->hideMenu();
	
	$form = new HtmlForm('password_expiry_form', ADMIDIO_URL . '/l4p/handle_password_expiry.php');
	
	$html =<<<EOD
<p>Your temporary password has expied.<p>
<p>Please click here to have a new password emailed to you</p>
EOD;
	
	$form->addHtml( "{$html}<br />" );
	
	$form->addSubmitButton('btn_save', $GLOBALS['gL10n']->get('L4P_PASSWORD_EXPIRY_RESEND'), array('icon' => THEME_URL.'/icons/options.png', 'class' => ' col-sm-offset-3'));
	
	$page->addHtml( $form->show(false) );
	
	$page->addCssFile( "l4p/asset/css/handle_password_expiry.min.css" );
	
	$page->show();
}

###
handle_request();