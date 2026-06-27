<?php
/**
 * Hetzner Cloud VPS Provisioning Module Hooks
 *
 * Hooks allow you to tie into events that occur within the WHMCS application.
 *
 * @see https://developers.whmcs.com/hooks/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Client edit hook.
 *
 * Runs when a client profile is edited.
 */
function hook_hz_cloud_clientedit(array $params)
{
    try {
        // Run any custom integrations when client profile changes
    } catch (\Exception $e) {
        // Log errors if necessary
    }
}
add_hook('ClientEdit', 1, 'hook_hz_cloud_clientedit');


