<?php

/**
 * Contains configuration details for facileManager
 *
 * @package facileManager
 *
 */

/** Database credentials */
$__FM_CONFIG['db']['host'] = 'localhost';
$__FM_CONFIG['db']['user'] = 'root';
$__FM_CONFIG['db']['pass'] = '';
$__FM_CONFIG['db']['name'] = 'facileManager';

/** Database SSL connection settings (optional) */
// $__FM_CONFIG['db']['key'] = '/path/to/ssl.key';
// $__FM_CONFIG['db']['cert'] = '/path/to/ssl.cer';
// $__FM_CONFIG['db']['ca'] = '/path/to/ca.pem';
// $__FM_CONFIG['db']['capath'] = '/path/to/trusted/cas';
// $__FM_CONFIG['db']['cipher'] = null;

require_once(ABSPATH . 'fm-modules/facileManager/functions.php');
