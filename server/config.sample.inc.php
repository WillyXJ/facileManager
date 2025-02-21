<?php

/**
 * Contains configuration details for facileManager
 *
 * @package facileManager
 *
 */

/** Database credentials */
$__FM_CONFIG['db']['host'] = 'localhost';
$__FM_CONFIG['db']['user'] = 'fM';
$__FM_CONFIG['db']['pass'] = 'secret-passphrase';
$__FM_CONFIG['db']['name'] = 'facileManager';

/** Database SSL connection settings (optional) */
// $__FM_CONFIG['db']['key'] = '/path/to/ssl.key';
// $__FM_CONFIG['db']['cert'] = '/path/to/ssl.cer';
// $__FM_CONFIG['db']['ca'] = '/path/to/ca.pem';
// $__FM_CONFIG['db']['capath'] = '/path/to/trusted/cas';
// $__FM_CONFIG['db']['cipher'] = null;

/** Disable authenication (Use only when locked out!) */
// define('FM_NO_AUTH', true);

/** Skip checks for .htaccess file presence (Use when contents are in vhost) */
// define('FM_NO_HTACCESS', true);

/** Skip webserver rewrite test (Use when behind a proxy) */
// define('FM_NO_REWRITE_TEST', true);

require_once(ABSPATH . 'fm-modules/facileManager/functions.php');
