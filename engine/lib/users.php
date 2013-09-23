<?php
/**
 * Elgg users
 * Functions to manage multiple or single users in an Elgg install
 *
 * @package Elgg.Core
 * @subpackage DataModel.User
 */

/// Map a username to a cached GUID
/**
 * @var int[] $USERNAME_TO_GUID_MAP_CACHE
 * @access private
 */
global $USERNAME_TO_GUID_MAP_CACHE;
$USERNAME_TO_GUID_MAP_CACHE = array();

/**
 * Return the user specific details of a user by a row.
 *
 * @param int $guid The ElggUser guid
 *
 * @return mixed
 * @access private
 */
function get_user_entity_as_row($guid) {
	global $CONFIG;

	$guid = (int)$guid;
	return get_data_row("SELECT * from {$CONFIG->dbprefix}users_entity where guid=$guid");
}

/**
 * Disables all of a user's entities
 *
 * @param int $owner_guid The owner GUID
 *
 * @return bool Depending on success
 */
function disable_user_entities($owner_guid) {
	global $CONFIG;
	$owner_guid = (int) $owner_guid;
	if ($entity = get_entity($owner_guid)) {
		if (elgg_trigger_event('disable', $entity->type, $entity)) {
			if ($entity->canEdit()) {
				$query = "UPDATE {$CONFIG->dbprefix}entities
					set enabled='no' where owner_guid={$owner_guid}
					or container_guid = {$owner_guid}";

				$res = update_data($query);
				return $res;
			}
		}
	}

	return false;
}

/**
 * Ban a user
 *
 * @param int    $user_guid The user guid
 * @param string $reason    A reason
 *
 * @return bool
 */
function ban_user($user_guid, $reason = "") {
	global $CONFIG;

	$user_guid = (int)$user_guid;

	$user = get_entity($user_guid);

	if (($user) && ($user->canEdit()) && ($user instanceof ElggUser)) {
		if (elgg_trigger_event('ban', 'user', $user)) {
			// Add reason
			if ($reason) {
				create_metadata($user_guid, 'ban_reason', $reason, '', 0, ACCESS_PUBLIC);
			}

			// invalidate memcache for this user
			static $newentity_cache;
			if ((!$newentity_cache) && (is_memcache_available())) {
				$newentity_cache = new ElggMemcache('new_entity_cache');
			}

			if ($newentity_cache) {
				$newentity_cache->delete($user_guid);
			}

			// Set ban flag
			$query = "UPDATE {$CONFIG->dbprefix}users_entity set banned='yes' where guid=$user_guid";
			return update_data($query);
		}

		return false;
	}

	return false;
}

/**
 * Unban a user.
 *
 * @param int $user_guid Unban a user.
 *
 * @return bool
 */
function unban_user($user_guid) {
	global $CONFIG;

	$user_guid = (int)$user_guid;

	$user = get_entity($user_guid);

	if (($user) && ($user->canEdit()) && ($user instanceof ElggUser)) {
		if (elgg_trigger_event('unban', 'user', $user)) {
			create_metadata($user_guid, 'ban_reason', '', '', 0, ACCESS_PUBLIC);

			// invalidate memcache for this user
			static $newentity_cache;
			if ((!$newentity_cache) && (is_memcache_available())) {
				$newentity_cache = new ElggMemcache('new_entity_cache');
			}

			if ($newentity_cache) {
				$newentity_cache->delete($user_guid);
			}


			$query = "UPDATE {$CONFIG->dbprefix}users_entity set banned='no' where guid=$user_guid";
			return update_data($query);
		}

		return false;
	}

	return false;
}

/**
 * Makes user $guid an admin.
 *
 * @param int $user_guid User guid
 *
 * @return bool
 */
function make_user_admin($user_guid) {
	global $CONFIG;

	$user = get_entity((int)$user_guid);

	if (($user) && ($user instanceof ElggUser) && ($user->canEdit())) {
		if (elgg_trigger_event('make_admin', 'user', $user)) {

			// invalidate memcache for this user
			static $newentity_cache;
			if ((!$newentity_cache) && (is_memcache_available())) {
				$newentity_cache = new ElggMemcache('new_entity_cache');
			}

			if ($newentity_cache) {
				$newentity_cache->delete($user_guid);
			}

			$r = update_data("UPDATE {$CONFIG->dbprefix}users_entity set admin='yes' where guid=$user_guid");
			_elgg_invalidate_cache_for_entity($user_guid);
			return $r;
		}

		return false;
	}

	return false;
}

/**
 * Removes user $guid's admin flag.
 *
 * @param int $user_guid User GUID
 *
 * @return bool
 */
function remove_user_admin($user_guid) {
	global $CONFIG;

	$user = get_entity((int)$user_guid);

	if (($user) && ($user instanceof ElggUser) && ($user->canEdit())) {
		if (elgg_trigger_event('remove_admin', 'user', $user)) {

			// invalidate memcache for this user
			static $newentity_cache;
			if ((!$newentity_cache) && (is_memcache_available())) {
				$newentity_cache = new ElggMemcache('new_entity_cache');
			}

			if ($newentity_cache) {
				$newentity_cache->delete($user_guid);
			}

			$r = update_data("UPDATE {$CONFIG->dbprefix}users_entity set admin='no' where guid=$user_guid");
			_elgg_invalidate_cache_for_entity($user_guid);
			return $r;
		}

		return false;
	}

	return false;
}

/**
 * Get a user object from a GUID.
 *
 * This function returns an ElggUser from a given GUID.
 *
 * @param int $guid The GUID
 *
 * @return ElggUser|false
 */
function get_user($guid) {
	// Fixes "Exception thrown without stack frame" when db_select fails
	if (!empty($guid)) {
		$result = get_entity($guid);
	}

	if ((!empty($result)) && (!($result instanceof ElggUser))) {
		return false;
	}

	if (!empty($result)) {
		return $result;
	}

	return false;
}

/**
 * Get user by username
 *
 * @param string $username The user's username
 *
 * @return ElggUser|false Depending on success
 */
function get_user_by_username($username) {
	global $CONFIG, $USERNAME_TO_GUID_MAP_CACHE;

	$username = sanitise_string($username);
	$access = _elgg_get_access_where_sql();

	// Caching
	if ((isset($USERNAME_TO_GUID_MAP_CACHE[$username]))
			&& (_elgg_retrieve_cached_entity($USERNAME_TO_GUID_MAP_CACHE[$username]))) {
		return _elgg_retrieve_cached_entity($USERNAME_TO_GUID_MAP_CACHE[$username]);
	}

	$query = "SELECT e.* FROM {$CONFIG->dbprefix}users_entity u
		JOIN {$CONFIG->dbprefix}entities e ON e.guid = u.guid
		WHERE u.username = '$username' AND $access";

	$entity = get_data_row($query, 'entity_row_to_elggstar');
	if ($entity) {
		$USERNAME_TO_GUID_MAP_CACHE[$username] = $entity->guid;
	} else {
		$entity = false;
	}

	return $entity;
}

/**
 * Get user by remember me code
 *
 * @param string $code The remember me code
 *
 * @return ElggUser
 */
function get_user_by_code($code) {
	if (!$code) {
		return null;
	}

	$db = _elgg_services()->db;	
	$prefix = $db->getTablePrefix();
	$code = $db->sanitizeString($code);

	$query = "SELECT guid FROM {$prefix}users_remember_me_cookies
		WHERE code = '$code'";
	try {
		$result = $db->getDataRow($query);
	} catch (DatabaseException $e) {
		if (false !== strpos($e->getMessage(), "users_remember_me_cookies' doesn't exist")) {
			// schema has not been updated so we swallow this exception
			return null;
		} else {
			throw $e;
		}
	}

	if ($result) {
		return get_user($result->guid);
	}

	return null;
}

/**
 * Get an array of users from an email address
 *
 * @param string $email Email address.
 *
 * @return array
 */
function get_user_by_email($email) {
	global $CONFIG;

	$email = sanitise_string($email);

	$access = _elgg_get_access_where_sql();

	$query = "SELECT e.* FROM {$CONFIG->dbprefix}entities e
		JOIN {$CONFIG->dbprefix}users_entity u ON e.guid = u.guid
		WHERE email = '$email' AND $access";

	return get_data($query, 'entity_row_to_elggstar');
}

/**
 * Return users (or the number of them) who have been active within a recent period.
 *
 * @param array $options Array of options with keys:
 *
 *   seconds (int)  => Length of period (default 600 = 10min)
 *   limit   (int)  => Limit (default 10)
 *   offset  (int)  => Offset (default 0)
 *   count   (bool) => Return a count instead of users? (default false)
 *
 *   Formerly this was the seconds parameter.
 *
 * @param int   $limit   Limit (deprecated usage, use $options)
 * @param int   $offset  Offset (deprecated usage, use $options)
 * @param bool  $count   Count (deprecated usage, use $options)
 *
 * @return ElggUser[]|int
 */
function find_active_users($options = array(), $limit = 10, $offset = 0, $count = false) {

	if (!is_array($options)) {
		elgg_deprecated_notice("find_active_users() now accepts an \$options array", 1.9);
		$options = array('seconds' => $options);
	}

	$options = array_merge(array(
		'limit' => $limit,
		'offset' => $offset,
		'count' => $count,
	), $options);

	// cast options we're sending to hook
	foreach (array('seconds', 'limit', 'offset') as $key) {
		$options[$key] = (int)$options[$key];
	}
	$options['count'] = (bool)$options['count'];

	// allow plugins to override
	$params = array(
		'seconds' => $options['seconds'],
		'limit' => $options['limit'],
		'offset' => $options['offset'],
		'count' => $options['count'],
		'options' => $options,
	);
	$data = elgg_trigger_plugin_hook('find_active_users', 'system', $params, null);
	// check null because the handler could legitimately return falsey values.
	if ($data !== null) {
		return $data;
	}

	$dbprefix = elgg_get_config('dbprefix');
	$time = time() - $options['seconds'];
	return elgg_get_entities(array(
		'type' => 'user',
		'limit' => $options['limit'],
		'offset' => $options['offset'],
		'count' => $options['count'],
		'joins' => array("join {$dbprefix}users_entity u on e.guid = u.guid"),
		'wheres' => array("u.last_action >= {$time}"),
		'order_by' => "u.last_action desc",
	));
}

/**
 * Generate and send a password request email to a given user's registered email address.
 *
 * @param int $user_guid User GUID
 *
 * @return bool
 */
function send_new_password_request($user_guid) {
	$user_guid = (int)$user_guid;

	$user = get_entity($user_guid);
	if ($user instanceof ElggUser) {
		// generate code
		$code = generate_random_cleartext_password();
		$user->setPrivateSetting('passwd_conf_code', $code);

		// generate link
		$link = elgg_get_site_url() . "resetpassword?u=$user_guid&c=$code";

		// generate email
		$ip_address = _elgg_services()->request->getClientIp();
		$email = elgg_echo('email:resetreq:body', array($user->name, $ip_address, $link));

		return notify_user($user->guid, elgg_get_site_entity()->guid,
			elgg_echo('email:resetreq:subject'), $email, array(), 'email');
	}

	return false;
}

/**
 * Low level function to reset a given user's password.
 *
 * This can only be called from execute_new_password_request().
 *
 * @param int    $user_guid The user.
 * @param string $password  Text (which will then be converted into a hash and stored)
 *
 * @return bool
 */
function force_user_password_reset($user_guid, $password) {
	$user = get_entity($user_guid);
	if ($user instanceof ElggUser) {
		$ia = elgg_set_ignore_access();

		$user->salt = generate_random_cleartext_password();
		$hash = generate_user_password($user, $password);
		$user->password = $hash;
		$result = (bool)$user->save();

		elgg_set_ignore_access($ia);

		return $result;
	}

	return false;
}

/**
 * Validate and execute a password reset for a user.
 *
 * @param int    $user_guid The user id
 * @param string $conf_code Confirmation code as sent in the request email.
 *
 * @return mixed
 */
function execute_new_password_request($user_guid, $conf_code) {
	global $CONFIG;

	$user_guid = (int)$user_guid;
	$user = get_entity($user_guid);

	if ($user instanceof ElggUser) {
		$saved_code = $user->getPrivateSetting('passwd_conf_code');

		if ($saved_code && $saved_code == $conf_code) {
			$password = generate_random_cleartext_password();

			if (force_user_password_reset($user_guid, $password)) {
				remove_private_setting($user_guid, 'passwd_conf_code');
				// clean the logins failures
				reset_login_failure_count($user_guid);
				
				$email = elgg_echo('email:resetpassword:body', array($user->name, $password));

				return notify_user($user->guid, $CONFIG->site->guid,
					elgg_echo('email:resetpassword:subject'), $email, array(), 'email');
			}
		}
	}

	return false;
}

/**
 * Simple function that will generate a random clear text password
 * suitable for feeding into generate_user_password().
 *
 * @see generate_user_password
 *
 * @return string
 */
function generate_random_cleartext_password() {
	return substr(md5(microtime() . rand()), 0, 8);
}

/**
 * Generate a password for a user, currently uses MD5.
 *
 * @param ElggUser $user     The user this is being generated for.
 * @param string   $password Password in clear text
 *
 * @return string
 */
function generate_user_password(ElggUser $user, $password) {
	return md5($password . $user->salt);
}

/**
 * Simple function which ensures that a username contains only valid characters.
 *
 * This should only permit chars that are valid on the file system as well.
 *
 * @param string $username Username
 *
 * @return bool
 * @throws RegistrationException on invalid
 */
function validate_username($username) {
	global $CONFIG;

	// Basic, check length
	if (!isset($CONFIG->minusername)) {
		$CONFIG->minusername = 4;
	}

	if (strlen($username) < $CONFIG->minusername) {
		$msg = elgg_echo('registration:usernametooshort', array($CONFIG->minusername));
		throw new RegistrationException($msg);
	}
	
	// username in the database has a limit of 128 characters
	if (strlen($username) > 128) {
		$msg = elgg_echo('registration:usernametoolong', array(128));
		throw new RegistrationException($msg);
	}

	// Blacklist for bad characters (partially nicked from mediawiki)
	$blacklist = '/[' .
		'\x{0080}-\x{009f}' . // iso-8859-1 control chars
		'\x{00a0}' .          // non-breaking space
		'\x{2000}-\x{200f}' . // various whitespace
		'\x{2028}-\x{202f}' . // breaks and control chars
		'\x{3000}' .          // ideographic space
		'\x{e000}-\x{f8ff}' . // private use
		']/u';

	if (preg_match($blacklist, $username)) {
		// @todo error message needs work
		throw new RegistrationException(elgg_echo('registration:invalidchars'));
	}

	// Belts and braces
	// @todo Tidy into main unicode
	$blacklist2 = '\'/\\"*& ?#%^(){}[]~?<>;|¬`@-+=';

	for ($n = 0; $n < strlen($blacklist2); $n++) {
		if (strpos($username, $blacklist2[$n]) !== false) {
			$msg = elgg_echo('registration:invalidchars', array($blacklist2[$n], $blacklist2));
			$msg = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
			throw new RegistrationException($msg);
		}
	}

	$result = true;
	return elgg_trigger_plugin_hook('registeruser:validate:username', 'all',
		array('username' => $username), $result);
}

/**
 * Simple validation of a password.
 *
 * @param string $password Clear text password
 *
 * @return bool
 * @throws RegistrationException on invalid
 */
function validate_password($password) {
	global $CONFIG;

	if (!isset($CONFIG->min_password_length)) {
		$CONFIG->min_password_length = 6;
	}

	if (strlen($password) < $CONFIG->min_password_length) {
		$msg = elgg_echo('registration:passwordtooshort', array($CONFIG->min_password_length));
		throw new RegistrationException($msg);
	}

	$result = true;
	return elgg_trigger_plugin_hook('registeruser:validate:password', 'all',
		array('password' => $password), $result);
}

/**
 * Simple validation of a email.
 *
 * @param string $address Email address
 *
 * @throws RegistrationException on invalid
 * @return bool
 */
function validate_email_address($address) {
	if (!is_email_address($address)) {
		throw new RegistrationException(elgg_echo('registration:notemail'));
	}

	// Got here, so lets try a hook (defaulting to ok)
	$result = true;
	return elgg_trigger_plugin_hook('registeruser:validate:email', 'all',
		array('email' => $address), $result);
}

/**
 * Registers a user, returning false if the username already exists
 *
 * @param string $username              The username of the new user
 * @param string $password              The password
 * @param string $name                  The user's display name
 * @param string $email                 The user's email address
 * @param bool   $allow_multiple_emails Allow the same email address to be
 *                                      registered multiple times?
 *
 * @return int|false The new user's GUID; false on failure
 * @throws RegistrationException
 */
function register_user($username, $password, $name, $email, $allow_multiple_emails = false) {

	// no need to trim password.
	$username = trim($username);
	$name = trim(strip_tags($name));
	$email = trim($email);

	// A little sanity checking
	if (empty($username)
			|| empty($password)
			|| empty($name)
			|| empty($email)) {
		return false;
	}

	// Make sure a user with conflicting details hasn't registered and been disabled
	$access_status = access_get_show_hidden_status();
	access_show_hidden_entities(true);

	if (!validate_email_address($email)) {
		throw new RegistrationException(elgg_echo('registration:emailnotvalid'));
	}

	if (!validate_password($password)) {
		throw new RegistrationException(elgg_echo('registration:passwordnotvalid'));
	}

	if (!validate_username($username)) {
		throw new RegistrationException(elgg_echo('registration:usernamenotvalid'));
	}

	if ($user = get_user_by_username($username)) {
		throw new RegistrationException(elgg_echo('registration:userexists'));
	}

	if ((!$allow_multiple_emails) && (get_user_by_email($email))) {
		throw new RegistrationException(elgg_echo('registration:dupeemail'));
	}

	access_show_hidden_entities($access_status);

	// Create user
	$user = new ElggUser();
	$user->username = $username;
	$user->email = $email;
	$user->name = $name;
	$user->access_id = ACCESS_PUBLIC;
	$user->salt = generate_random_cleartext_password(); // Note salt generated before password!
	$user->password = generate_user_password($user, $password);
	$user->owner_guid = 0; // Users aren't owned by anyone, even if they are admin created.
	$user->container_guid = 0; // Users aren't contained by anyone, even if they are admin created.
	$user->language = get_current_language();
	if ($user->save() === false) {
		return false;
	}

	// Turn on email notifications by default
	set_user_notification_setting($user->getGUID(), 'email', true);

	return $user->getGUID();
}

/**
 * Generates a unique invite code for a user
 *
 * @param string $username The username of the user sending the invitation
 *
 * @return string Invite code
 */
function generate_invite_code($username) {
	$secret = datalist_get('__site_secret__');
	return md5($username . $secret);
}

/**
 * Set the validation status for a user.
 *
 * @param int    $user_guid The user's GUID
 * @param bool   $status    Validated (true) or unvalidated (false)
 * @param string $method    Optional method to say how a user was validated
 * @return bool
 * @since 1.8.0
 */
function elgg_set_user_validation_status($user_guid, $status, $method = '') {
	$result1 = create_metadata($user_guid, 'validated', $status, '', 0, ACCESS_PUBLIC, false);
	$result2 = create_metadata($user_guid, 'validated_method', $method, '', 0, ACCESS_PUBLIC, false);
	if ($result1 && $result2) {
		return true;
	} else {
		return false;
	}
}

/**
 * Gets the validation status of a user.
 *
 * @param int $user_guid The user's GUID
 * @return bool|null Null means status was not set for this user.
 * @since 1.8.0
 */
function elgg_get_user_validation_status($user_guid) {
	$md = elgg_get_metadata(array(
		'guid' => $user_guid,
		'metadata_name' => 'validated'
	));
	if ($md == false) {
		return null;
	}

	if ($md[0]->value) {
		return true;
	}

	return false;
}

/**
 * Page handler for account related pages
 *
 * @param array  $page_elements Page elements
 * @param string $handler The handler string
 *
 * @return bool
 * @access private
 */
function elgg_user_account_page_handler($page_elements, $handler) {

	$base_dir = elgg_get_root_path() . 'pages/account';
	switch ($handler) {
		case 'login':
			require_once("$base_dir/login.php");
			break;
		case 'forgotpassword':
			require_once("$base_dir/forgotten_password.php");
			break;
		case 'resetpassword':
			require_once("$base_dir/reset_password.php");
			break;
		case 'register':
			require_once("$base_dir/register.php");
			break;
		default:
			return false;
	}
	return true;
}

/**
 * Sets the last action time of the given user to right now.
 *
 * @param int $user_guid The user GUID
 *
 * @return void
 */
function set_last_action($user_guid) {
	$user_guid = (int) $user_guid;
	global $CONFIG;
	$time = time();

	$query = "UPDATE {$CONFIG->dbprefix}users_entity
		set prev_last_action = last_action,
		last_action = {$time} where guid = {$user_guid}";

	execute_delayed_write_query($query);
}

/**
 * Sets the last logon time of the given user to right now.
 *
 * @param int $user_guid The user GUID
 *
 * @return void
 */
function set_last_login($user_guid) {
	$user_guid = (int) $user_guid;
	global $CONFIG;
	$time = time();

	$query = "UPDATE {$CONFIG->dbprefix}users_entity
		set prev_last_login = last_login, last_login = {$time} where guid = {$user_guid}";

	execute_delayed_write_query($query);
}

/**
 * Creates a relationship between this site and the user.
 *
 * @param string   $event       create
 * @param string   $object_type user
 * @param ElggUser $object      User object
 *
 * @return void
 * @access private
 */
function user_create_hook_add_site_relationship($event, $object_type, $object) {
	add_entity_relationship($object->getGUID(), 'member_of_site', elgg_get_site_entity()->guid);
}

/**
 * Serves the user's avatar
 *
 * @param string $hook
 * @param string $entity_type
 * @param string $returnvalue
 * @param array  $params
 * @return string
 * @access private
 */
function user_avatar_hook($hook, $entity_type, $returnvalue, $params) {
	$user = $params['entity'];
	$size = $params['size'];

	if (isset($user->icontime)) {
		return "avatar/view/$user->username/$size/$user->icontime";
	} else {
		return "_graphics/icons/user/default{$size}.gif";
	}
}

/**
 * Setup the default user hover menu
 * @access private
 */
function elgg_user_hover_menu($hook, $type, $return, $params) {
	$user = $params['entity'];
	/* @var ElggUser $user */

	if (elgg_is_logged_in()) {
		if (elgg_get_logged_in_user_guid() == $user->guid) {
			$url = "profile/$user->username/edit";
			$item = new ElggMenuItem('profile:edit', elgg_echo('profile:edit'), $url);
			$item->setSection('action');
			$return[] = $item;

			$url = "avatar/edit/$user->username";
			$item = new ElggMenuItem('avatar:edit', elgg_echo('avatar:edit'), $url);
			$item->setSection('action');
			$return[] = $item;
		}
	}

	// prevent admins from banning or deleting themselves
	if (elgg_get_logged_in_user_guid() == $user->guid) {
		return $return;
	}

	if (elgg_is_admin_logged_in()) {
		$actions = array();
		if (!$user->isBanned()) {
			$actions[] = 'ban';
		} else {
			$actions[] = 'unban';
		}
		$actions[] = 'delete';
		$actions[] = 'resetpassword';
		if (!$user->isAdmin()) {
			$actions[] = 'makeadmin';
		} else {
			$actions[] = 'removeadmin';
		}

		foreach ($actions as $action) {
			$url = "action/admin/user/$action?guid={$user->guid}";
			$url = elgg_add_action_tokens_to_url($url);
			$item = new ElggMenuItem($action, elgg_echo($action), $url);
			$item->setSection('admin');
			$item->setLinkClass('elgg-requires-confirmation');

			$return[] = $item;
		}

		$url = "profile/$user->username/edit";
		$item = new ElggMenuItem('profile:edit', elgg_echo('profile:edit'), $url);
		$item->setSection('admin');
		$return[] = $item;

		$url = "settings/user/$user->username";
		$item = new ElggMenuItem('settings:edit', elgg_echo('settings:edit'), $url);
		$item->setSection('admin');
		$return[] = $item;

		$url = "activity/owner/$user->username";
		$item = new ElggMenuItem('activity:owner', elgg_echo('activity:owner'), $url);
		$item->setSection('action');
		$return[] = $item;
	}

	return $return;
}

/**
 * Setup the menu shown with an entity
 *
 * @param string $hook
 * @param string $type
 * @param array $return
 * @param array $params
 * @return array
 *
 * @access private
 */
function elgg_users_setup_entity_menu($hook, $type, $return, $params) {
	if (elgg_in_context('widgets')) {
		return $return;
	}

	$entity = $params['entity'];
	if (!elgg_instanceof($entity, 'user')) {
		return $return;
	}
	/* @var ElggUser $entity */

	if ($entity->isBanned()) {
		$banned = elgg_echo('banned');
		$options = array(
			'name' => 'banned',
			'text' => "<span>$banned</span>",
			'href' => false,
			'priority' => 0,
		);
		$return = array(ElggMenuItem::factory($options));
	} else {
		$return = array();
		if (isset($entity->location)) {
			$location = htmlspecialchars($entity->location, ENT_QUOTES, 'UTF-8', false);
			$options = array(
				'name' => 'location',
				'text' => "<span>$location</span>",
				'href' => false,
				'priority' => 150,
			);
			$return[] = ElggMenuItem::factory($options);
		}
	}

	return $return;
}

/**
 * This function loads a set of default fields into the profile, then triggers a hook letting other plugins to edit
 * add and delete fields.
 *
 * Note: This is a secondary system:init call and is run at a super low priority to guarantee that it is called after all
 * other plugins have initialised.
 * @access private
 */
function elgg_profile_fields_setup() {
	global $CONFIG;

	$profile_defaults = array (
		'description' => 'longtext',
		'briefdescription' => 'text',
		'location' => 'location',
		'interests' => 'tags',
		'skills' => 'tags',
		'contactemail' => 'email',
		'phone' => 'text',
		'mobile' => 'text',
		'website' => 'url',
		'twitter' => 'text',
	);

	$loaded_defaults = array();
	if ($fieldlist = elgg_get_config('profile_custom_fields')) {
		if (!empty($fieldlist)) {
			$fieldlistarray = explode(',', $fieldlist);
			foreach ($fieldlistarray as $listitem) {
				if ($translation = elgg_get_config("admin_defined_profile_{$listitem}")) {
					$type = elgg_get_config("admin_defined_profile_type_{$listitem}");
					$loaded_defaults["admin_defined_profile_{$listitem}"] = $type;
					add_translation(get_current_language(), array("profile:admin_defined_profile_{$listitem}" => $translation));
				}
			}
		}
	}

	if (count($loaded_defaults)) {
		$CONFIG->profile_using_custom = true;
		$profile_defaults = $loaded_defaults;
	}

	$CONFIG->profile_fields = elgg_trigger_plugin_hook('profile:fields', 'profile', null, $profile_defaults);

	// register any tag metadata names
	foreach ($CONFIG->profile_fields as $name => $type) {
		if ($type == 'tags' || $type == 'location' || $type == 'tag') {
			elgg_register_tag_metadata_name($name);
			// register a tag name translation
			add_translation(get_current_language(), array("tag_names:$name" => elgg_echo("profile:$name")));
		}
	}
}

/**
 * Avatar page handler
 *
 * /avatar/edit/<username>
 * /avatar/view/<username>/<size>/<icontime>
 *
 * @param array $page
 * @return bool
 * @access private
 */
function elgg_avatar_page_handler($page) {
	global $CONFIG;

	$user = get_user_by_username($page[1]);
	if ($user) {
		elgg_set_page_owner_guid($user->getGUID());
	}

	if ($page[0] == 'edit') {
		require_once("{$CONFIG->path}pages/avatar/edit.php");
		return true;
	} else {
		set_input('size', $page[2]);
		require_once("{$CONFIG->path}pages/avatar/view.php");
		return true;
	}
	return false;
}

/**
 * Profile page handler
 *
 * @param array $page
 * @return bool
 * @access private
 */
function elgg_profile_page_handler($page) {
	global $CONFIG;

	$user = get_user_by_username($page[0]);
	elgg_set_page_owner_guid($user->guid);

	if ($page[1] == 'edit') {
		require_once("{$CONFIG->path}pages/profile/edit.php");
		return true;
	}
	return false;
}

/**
 * Sets up user-related menu items
 *
 * @return void
 * @access private
 */
function users_pagesetup() {

	$owner = elgg_get_page_owner_entity();
	$viewer = elgg_get_logged_in_user_entity();

	if ($owner) {		
		elgg_register_menu_item('page', array(
			'name' => 'edit_avatar',
			'href' => "avatar/edit/{$owner->username}",
			'text' => elgg_echo('avatar:edit'),
			'section' => '1_profile',
			'contexts' => array('settings'),
		));

		elgg_register_menu_item('page', array(
			'name' => 'edit_profile',
			'href' => "profile/{$owner->username}/edit",
			'text' => elgg_echo('profile:edit'),
			'section' => '1_profile',
			'contexts' => array('settings'),
		));
	}

	// topbar
	if ($viewer) {
		elgg_register_menu_item('topbar', array(
			'name' => 'profile',
			'href' => $viewer->getURL(),
			'text' => elgg_view('output/img', array(
				'src' => $viewer->getIconURL('topbar'),
				'alt' => $viewer->name,
				'title' => elgg_echo('profile'),
				'class' => 'elgg-border-plain elgg-transition',
			)),
			'priority' => 100,
			'link_class' => 'elgg-topbar-avatar',
		));

		elgg_register_menu_item('topbar', array(
			'name' => 'usersettings',
			'href' => "settings/user/{$viewer->username}",
			'text' => elgg_view_icon('settings') . elgg_echo('settings'),
			'priority' => 500,
			'section' => 'alt',
		));

		elgg_register_menu_item('topbar', array(
			'name' => 'logout',
			'href' => "action/logout",
			'text' => elgg_echo('logout'),
			'is_action' => true,
			'priority' => 1000,
			'section' => 'alt',
		));
	}
}

/**
 * Users initialisation function, which establishes the page handler
 *
 * @return void
 * @access private
 */
function users_init() {

	elgg_register_page_handler('register', 'elgg_user_account_page_handler');
	elgg_register_page_handler('forgotpassword', 'elgg_user_account_page_handler');
	elgg_register_page_handler('resetpassword', 'elgg_user_account_page_handler');
	elgg_register_page_handler('login', 'elgg_user_account_page_handler');
	elgg_register_page_handler('avatar', 'elgg_avatar_page_handler');
	elgg_register_page_handler('profile', 'elgg_profile_page_handler');

	elgg_register_plugin_hook_handler('register', 'menu:user_hover', 'elgg_user_hover_menu');

	elgg_register_action('register', '', 'public');
	elgg_register_action('useradd', '', 'admin');
	elgg_register_action('avatar/upload');
	elgg_register_action('avatar/crop');
	elgg_register_action('avatar/remove');
	elgg_register_action('profile/edit');

	elgg_register_plugin_hook_handler('entity:icon:url', 'user', 'user_avatar_hook');

	elgg_register_action('user/passwordreset', '', 'public');
	elgg_register_action('user/requestnewpassword', '', 'public');

	// Register the user type
	elgg_register_entity_type('user', '');

	elgg_register_plugin_hook_handler('register', 'menu:entity', 'elgg_users_setup_entity_menu', 501);

	elgg_register_event_handler('create', 'user', 'user_create_hook_add_site_relationship');
}

/**
 * Runs unit tests for ElggUser
 *
 * @param string $hook   unit_test
 * @param string $type   system
 * @param mixed  $value  Array of tests
 * @param mixed  $params Params
 *
 * @return array
 * @access private
 */
function users_test($hook, $type, $value, $params) {
	global $CONFIG;
	$value[] = "{$CONFIG->path}engine/tests/ElggUserTest.php";
	return $value;
}

elgg_register_event_handler('init', 'system', 'users_init', 0);
elgg_register_event_handler('init', 'system', 'elgg_profile_fields_setup', 10000); // Ensure this runs after other plugins
elgg_register_event_handler('pagesetup', 'system', 'users_pagesetup', 0);
elgg_register_plugin_hook_handler('unit_test', 'system', 'users_test');
