<?php
/**
 * Hetzner Cloud VPS Provisioning Module
 *
 * Internally named: hz_cloud
 * Display name: Hetzner Cloud VPS
 *
 * @see https://developers.whmcs.com/provisioning-modules/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// Include the Hetzner API wrapper
require_once __DIR__ . '/lib/HetznerAPI.php';

/**
 * Define module related meta data.
 */
function hz_cloud_MetaData()
{
    return array(
        'DisplayName'         => 'Hetzner Cloud VPS',
        'APIVersion'          => '1.1',
        'RequiresServer'      => true,
        'AutoGenerateUsername'=> false,
        'DefaultSSLMode'      => 'none',
    );
}

/**
 * Helper function to retrieve the decrypted API token for the server associated with a product.
 */
function hz_cloud_GetServerApiTokenForProduct($productId)
{
    try {
        $product = Capsule::table('tblproducts')->where('id', $productId)->first();
        if (!$product) {
            return null;
        }

        $serverId = 0;
        if ($product->servergroup) {
            $serverRelation = Capsule::table('tblservergroupsrel')
                ->where('groupid', $product->servergroup)
                ->first();
            if ($serverRelation) {
                $serverId = $serverRelation->serverid;
            }
        } else if ($product->defaultserver) {
            $serverId = $product->defaultserver;
        }

        // Fallback to the first active server of type hz_cloud
        if (!$serverId) {
            $serverId = Capsule::table('tblservers')
                ->where('type', 'hz_cloud')
                ->where('active', 1)
                ->value('id');
        }

        if ($serverId) {
            $server = Capsule::table('tblservers')->where('id', $serverId)->first();
            if ($server && !empty($server->password)) {
                return decrypt($server->password);
            }
        }
    } catch (\Exception $e) {
        // Fallback if DB query fails
    }
    return null;
}

/**
 * Define product configuration options.
 */
function hz_cloud_ConfigOptions()
{
    $locations   = [];
    $serverTypes = [];

    $productId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    $apiToken  = null;
    if ($productId) {
        $apiToken = hz_cloud_GetServerApiTokenForProduct($productId);
    }

    $fetched = false;
    $fetchedImagesList = [];

    if ($apiToken) {
        try {
            $api = new HetznerAPI($apiToken);

            // /pricing gives us the account billing currency (single source of truth).
            // Prices themselves come from /server_types prices[] per location.
            $currency = 'EUR';
            $ipv4Price = 1.70; // Default fallback in case we can't fetch it
            try {
                $pricing  = $api->getPricing();
                $currency = isset($pricing['currency']) ? strtoupper($pricing['currency']) : 'EUR';

                if (!empty($pricing['primary_ips'])) {
                    foreach ($pricing['primary_ips'] as $ipPrice) {
                        if (isset($ipPrice['type']) && $ipPrice['type'] === 'ipv4') {
                            if (isset($ipPrice['price_monthly']['net'])) {
                                $ipv4Price = (float)$ipPrice['price_monthly']['net'];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Non-fatal — currency stays EUR, ipv4Price stays 1.70
            }

            // Build location dropdown: "City, CC — Datacenter Description (CODE)"
            // /locations returns: name, description, city, country, network_zone
            $locs = $api->getLocations();
            $locCountryMap = [];
            foreach ($locs as $l) {
                $countryCode = strtoupper($l['country'] ?? '');
                $countryMap = [
                    'DE' => 'Germany',
                    'FI' => 'Finland',
                    'US' => 'USA',
                    'SG' => 'singapore',
                ];
                $countryName = $countryMap[$countryCode] ?? $countryCode;
                $locCountryMap[$l['name']] = $countryName;

                $countryFullNameMap = [
                    'DE' => 'Germany',
                    'FI' => 'Finland',
                    'US' => 'United States',
                    'SG' => 'Singapore',
                ];
                $countryFullName = $countryFullNameMap[$countryCode] ?? $countryCode;
                // Prepend city name if available for more specific location display
                $city = $l['city'] ?? '';
                $locations[$l['name']] = $city ? $city . ', ' . $countryFullName : $countryFullName;
            }

            // /server_types: architecture=x86 filtered + deprecated excluded (done in API wrapper).
            // Each type has prices[] per location — use first entry's price_monthly.net for display.
            $types = $api->getServerTypes('x86');
            foreach ($types as $t) {
                // Redundant guard — wrapper already filters, but be explicit
                if (isset($t['architecture']) && strtolower($t['architecture']) !== 'x86') {
                    continue;
                }
                if (!empty($t['deprecated'])) {
                    continue;
                }

                // Price: group prices by location
                $priceStr = '';
                if (!empty($t['prices'])) {
                    $locationPrices = [];
                    foreach ($t['prices'] as $p) {
                        $locName = $p['location'] ?? '';
                        $priceMonthly = $p['price_monthly']['net'] ?? null;
                        if ($locName && $priceMonthly !== null) {
                            $locCode = strtoupper($locName);
                            $countryDesignation = $locCountryMap[$locName] ?? '';
                            if ($countryDesignation) {
                                $locCode .= '(' . $countryDesignation . ')';
                            }
                            $locationPrices[] = $locCode . ': ' . number_format((float)$priceMonthly, 2) . ' ' . $currency;
                        }
                    }
                    if (!empty($locationPrices)) {
                        $ipv4Suffix = ' (+' . number_format($ipv4Price, 2) . ' ' . $currency . ' IPv4)';
                        $priceStr = ' | ' . implode(', ', $locationPrices) . $ipv4Suffix;
                    }
                }

                // Storage type label: local SSD vs network
                $storage = isset($t['storage_type']) && $t['storage_type'] === 'network' ? 'NVMe' : 'SSD';
                // CPU type label from cpu_type field (shared / dedicated / dedicated_ccu)
                $cpuLabel = '';
                if (isset($t['cpu_type'])) {
                    $cpuLabel = str_replace('_', ' ', $t['cpu_type']);
                    $cpuLabel = ' (' . ucwords($cpuLabel) . ')';
                }

                $displayName = !empty($t['description']) ? $t['description'] : strtoupper($t['name']);
                $serverTypes[$t['name']] = $displayName . ' - ' . $t['cores'] . ' vCPU / ' . $t['memory'] . ' GB RAM / ' . $t['disk'] . ' GB ' . $storage
                    . $cpuLabel
                    . $priceStr;
            }

            if (!empty($locations) && !empty($serverTypes)) {
                $fetched = true;
                // Prepend indicator to show that the options were successfully fetched from the Hetzner Cloud API
                $serverTypes = ['api-fetched' => '--- Plans Fetched from Hetzner Cloud API ---'] + $serverTypes;
            }

            try {
                $fetchedImagesList = $api->getImages();
            } catch (\Exception $e) {
                // Ignore API exceptions for images
            }
        } catch (\Exception $e) {
            // Ignore API exceptions, fallback will be used
        }
    }

    // Static Fallback options if API token is missing or call fails
    if (!$fetched) {
        $locations = [
            'nbg1' => 'Nuremberg, Germany',
            'fsn1' => 'Falkenstein, Germany',
            'hel1' => 'Helsinki, Finland',
            'ash'  => 'Ashburn, United States',
            'hil'  => 'Hillsboro, United States',
            'sin'  => 'Singapore',
        ];

        $serverTypes = [
            'cx22'  => 'CX22 - 2 vCPU / 4 GB RAM / 40 GB SSD (Shared) | NBG1(Germany)/FSN1(Germany)/HEL1(Finland): 5.30 EUR, ASH(USA)/HIL(USA): 6.25 EUR, SIN(singapore): 7.40 EUR (+1.70 EUR IPv4)',
            'cpx11' => 'CPX11 - 2 vCPU / 2 GB RAM / 40 GB SSD (Shared) | NBG1(Germany)/FSN1(Germany)/HEL1(Finland): 3.85 EUR, ASH(USA)/HIL(USA): 4.55 EUR, SIN(singapore): 5.40 EUR (+1.70 EUR IPv4)',
            'cpx21' => 'CPX21 - 3 vCPU / 4 GB RAM / 80 GB SSD (Shared) | NBG1(Germany)/FSN1(Germany)/HEL1(Finland): 5.80 EUR, ASH(USA)/HIL(USA): 6.85 EUR, SIN(singapore): 8.10 EUR (+1.70 EUR IPv4)',
            'cpx31' => 'CPX31 - 4 vCPU / 8 GB RAM / 160 GB SSD (Shared) | NBG1(Germany)/FSN1(Germany)/HEL1(Finland): 10.40 EUR, ASH(USA)/HIL(USA): 12.25 EUR, SIN(singapore): 14.55 EUR (+1.70 EUR IPv4)',
            'cpx41' => 'CPX41 - 8 vCPU / 16 GB RAM / 240 GB SSD (Shared) | NBG1(Germany)/FSN1(Germany)/HEL1(Finland): 20.30 EUR, ASH(USA)/HIL(USA): 23.95 EUR, SIN(singapore): 28.40 EUR (+1.70 EUR IPv4)',
            'cpx51' => 'CPX51 - 16 vCPU / 32 GB RAM / 360 GB SSD (Shared) | NBG1(Germany)/FSN1(Germany)/HEL1(Finland): 39.50 EUR, ASH(USA)/HIL(USA): 46.60 EUR, SIN(singapore): 55.30 EUR (+1.70 EUR IPv4)',
            'cx32'  => 'CX32 - 4 vCPU / 8 GB RAM / 80 GB SSD (Shared) | NBG1(Germany)/FSN1(Germany)/HEL1(Finland): 7.10 EUR, ASH(USA)/HIL(USA): 8.35 EUR, SIN(singapore): 9.90 EUR (+1.70 EUR IPv4)',
            'cx42'  => 'CX42 - 8 vCPU / 16 GB RAM / 160 GB SSD (Shared) | NBG1(Germany)/FSN1(Germany)/HEL1(Finland): 14.10 EUR, ASH(USA)/HIL(USA): 16.65 EUR, SIN(singapore): 19.70 EUR (+1.70 EUR IPv4)',
            'cx52'  => 'CX52 - 16 vCPU / 32 GB RAM / 320 GB SSD (Shared) | NBG1(Germany)/FSN1(Germany)/HEL1(Finland): 28.10 EUR, ASH(USA)/HIL(USA): 33.15 EUR, SIN(singapore): 39.30 EUR (+1.70 EUR IPv4)',
        ];
    }

    // Format OS image options list for admin helper
    $formattedImages = [];
    if (!empty($fetchedImagesList)) {
        foreach ($fetchedImagesList as $img) {
            if ($img['status'] === 'available') {
                $formattedImages[] = $img['name'] . '|' . $img['description'];
            }
        }
    } else {
        $formattedImages = [
            'ubuntu-24.04|Ubuntu 24.04',
            'ubuntu-22.04|Ubuntu 22.04',
            'debian-12|Debian 12',
            'debian-11|Debian 11',
            'rocky-9|Rocky Linux 9',
            'alma-9|AlmaLinux 9',
            'centos-stream-9|CentOS Stream 9',
        ];
    }
    $osListStr = htmlspecialchars(implode(',', $formattedImages));

    // Build per-field copy widgets for the Custom Fields tab helper
    // Each shows the field definition needed in WHMCS → Product → Custom Fields

    // Field 1: OS Image (required, shown on order form)
    $cfOsImageOptions = $osListStr; // pipe-separated value|label pairs

    // Field 2: Server ID (admin-only, auto-created by module but define here for safety)
    $cfServerIdDef = 'Server ID|VPS ID';

    // Field 3: Backups configurable option hint (not a custom field — goes in Configurable Options)
    $cfBackupsHint = 'Backups';

    // Field 4: Location override (Configurable Option, not custom field)
    $cfLocationOptions = implode(',', array_map(
        fn($k, $v) => $k . '|' . $v,
        array_keys($locations),
        array_values($locations)
    ));

    // Field 5: Server Type override (Configurable Option, not custom field)
    $cfServerTypeOptions = implode(',', array_map(
        fn($k, $v) => $k . '|' . $v,
        array_keys($serverTypes),
        array_values($serverTypes)
    ));

    $txStyle  = 'width:100%;font-family:monospace;font-size:11px;background:#f9f9f9;border:1px solid #ccc;padding:4px;border-radius:3px;resize:vertical;cursor:pointer;';
    $secStyle = 'margin-top:12px;padding:10px 12px;background:#fff;border:1px solid #ddd;border-radius:4px;';
    $lblStyle = 'font-size:12px;font-weight:700;color:#333;margin-bottom:4px;display:block;';
    $subStyle = 'font-size:11px;color:#666;margin-bottom:5px;display:block;';

    $customFieldsHtml = <<<HTML
<br>
<div style="margin-top:14px;padding:12px 14px;background:#f0f4ff;border:1px solid #c7d2fe;border-radius:5px;">
    <b style="font-size:13px;color:#1e3a8a;">&#x1F4CB; Custom Fields &amp; Configurable Options Setup</b>
    <p style="font-size:11px;color:#374151;margin:5px 0 0;">Click any textarea below to select all, then copy-paste into the corresponding WHMCS field.</p>
</div>

<div style="{$secStyle}">
    <span style="{$lblStyle}">&#x1F4C4; Custom Field 1 &mdash; <code>OS Image</code> &nbsp;<em style="font-weight:400;color:#555;">(Product &rsaquo; Custom Fields tab)</em></span>
    <span style="{$subStyle}">Field Name: <code>OS Image|Operating System</code> &nbsp;&bull;&nbsp; Type: Dropdown &nbsp;&bull;&nbsp; Show on Order: &#x2714; &nbsp;&bull;&nbsp; Required: &#x2714;</span>
    <span style="{$subStyle}">Paste into the <b>Options</b> field:</span>
    <textarea style="{$txStyle}height:55px;" onclick="this.select()" readonly>{$cfOsImageOptions}</textarea>
</div>

<div style="{$secStyle}">
    <span style="{$lblStyle}">&#x1F511; Custom Field 2 &mdash; <code>Server ID</code> &nbsp;<em style="font-weight:400;color:#555;">(Product &rsaquo; Custom Fields tab)</em></span>
    <span style="{$subStyle}">Field Name (copy exactly): &nbsp;&bull;&nbsp; Type: Text &nbsp;&bull;&nbsp; Admin Only: &#x2714; &nbsp;&bull;&nbsp; Show on Order: &#x2718;</span>
    <textarea style="{$txStyle}height:28px;" onclick="this.select()" readonly>{$cfServerIdDef}</textarea>
</div>

<div style="{$secStyle}">
    <span style="{$lblStyle}">&#x2699;&#xFE0F; Configurable Option &mdash; <code>Backups</code> &nbsp;<em style="font-weight:400;color:#555;">(Setup &rsaquo; Products &rsaquo; Configurable Options)</em></span>
    <span style="{$subStyle}">Option Name (copy exactly) &nbsp;&bull;&nbsp; Type: Yes/No or Dropdown &nbsp;&bull;&nbsp; Values: <code>No|No Backups</code> and <code>Yes|Enable Daily Backups</code></span>
    <textarea style="{$txStyle}height:28px;" onclick="this.select()" readonly>{$cfBackupsHint}</textarea>
</div>

<div style="{$secStyle}">
    <span style="{$lblStyle}">&#x1F4CD; Configurable Option &mdash; <code>Location</code> override &nbsp;<em style="font-weight:400;color:#555;">(Setup &rsaquo; Products &rsaquo; Configurable Options)</em></span>
    <span style="{$subStyle}">Option Name: <code>Location</code> &nbsp;&bull;&nbsp; Type: Dropdown &nbsp;&bull;&nbsp; Paste into Options:</span>
    <textarea style="{$txStyle}height:55px;" onclick="this.select()" readonly>{$cfLocationOptions}</textarea>
</div>

<div style="{$secStyle}">
    <span style="{$lblStyle}">&#x1F5A5;&#xFE0F; Configurable Option &mdash; <code>Server Type</code> override &nbsp;<em style="font-weight:400;color:#555;">(Setup &rsaquo; Products &rsaquo; Configurable Options)</em></span>
    <span style="{$subStyle}">Option Name: <code>Server Type</code> &nbsp;&bull;&nbsp; Type: Dropdown &nbsp;&bull;&nbsp; Paste into Options:</span>
    <textarea style="{$txStyle}height:70px;" onclick="this.select()" readonly>{$cfServerTypeOptions}</textarea>
</div>
HTML;

    // Only Location and Server Type are module-level settings.
    // OS Image => set via Custom Field "OS Image" on the product.
    // Backups  => set via WHMCS Configurable Options on the product.
    return array(
        'Location' => array(
            'Type'        => 'dropdown',
            'Options'     => $locations,
            'Description' => 'Select default deployment datacenter. Format: City, Country (Code).',
            'SimpleMode'  => true,
        ),
        'Server Type' => array(
            'Type'        => 'dropdown',
            'Options'     => $serverTypes,
            'Description' => 'Select default server hardware type (x86 only). Price shown is monthly net in EUR.',
            'SimpleMode'  => true,
        ),
        'Allow VNC Console' => array(
            'Type'        => 'yesno',
            'Description' => 'Allow client to access VNC console in client area.',
            'Default'     => 'on',
        ),
        'Allow Password Reset' => array(
            'Type'        => 'yesno',
            'Description' => 'Allow client to reset root password in client area.',
            'Default'     => 'on',
        ),
        'Allow ISO Mount' => array(
            'Type'        => 'yesno',
            'Description' => 'Allow client to mount ISO images in client area.',
            'Default'     => 'on',
        ),
        'Allow OS Reinstall' => array(
            'Type'        => 'yesno',
            'Description' => 'Allow client to reinstall OS in client area.',
            'Default'     => 'on',
        ),
        'Allow Snapshot/Backup on Reinstall' => array(
            'Type'        => 'yesno',
            'Description' => 'Allow client to select a backup or snapshot image when reinstalling.',
            'Default'     => 'on',
        ),
        'Show PTR Records' => array(
            'Type'        => 'yesno',
            'Description' => 'Show Reverse DNS (PTR) configuration fields to client.',
            'Default'     => 'on',
        ),
        'Hide Hostname Input' => array(
            'Type'        => 'yesno',
            'Description' => 'Hide the hostname change field from client area (Default: Visible).',
            'Default'     => '',
        ),
        'Setup Instructions' => array(
            'FriendlyName' => ' ',
            'Type'        => 'none',
            'Description' => $customFieldsHtml,
        ),
    );
}

/**
 * Provision a new server.
 */
function hz_cloud_CreateAccount(array $params)
{
    try {
        $apiToken = $params['serverpassword'];
        if (empty($apiToken)) {
            throw new Exception("Cloud API Token is missing from WHMCS server configuration.");
        }

        $api = new HetznerAPI($apiToken);

        // Retrieve settings from Module Settings (configoption1 = Location, configoption2 = Server Type)
        $location   = $params['configoption1'];
        $serverType = $params['configoption2'];

        // Configurable Options can override Location and Server Type
        if (isset($params['configoptions']['Location'])) {
            $location = $params['configoptions']['Location'];
        } elseif (isset($params['configoptions']['location'])) {
            $location = $params['configoptions']['location'];
        }

        if (isset($params['configoptions']['Server Type'])) {
            $serverType = $params['configoptions']['Server Type'];
        } elseif (isset($params['configoptions']['server_type'])) {
            $serverType = $params['configoptions']['server_type'];
        }

        if ($serverType === 'api-fetched') {
            throw new Exception("Please select a valid Server Type plan instead of the placeholder header.");
        }

        // OS Image: Read from Custom Field "OS Image"
        $image = '';
        if (isset($params['customfields'])) {
            foreach ($params['customfields'] as $key => $val) {
                $cleanKey = trim(explode('|', $key)[0]);
                if (strcasecmp($cleanKey, 'OS Image') === 0 || strcasecmp($cleanKey, 'os_image') === 0) {
                    $image = trim($val);
                    break;
                }
            }
        }
        if (empty($image)) {
            // Fallback default if custom field is empty
            $image = 'ubuntu-24.04';
        }

        // Backups: Read from Configurable Options
        $backups = false;
        if (isset($params['configoptions']['Backups'])) {
            $val = $params['configoptions']['Backups'];
            $backups = ($val === 'yes' || $val === 'Yes' || $val === 'Enable' || $val === 'enable' || $val == 1);
        } elseif (isset($params['configoptions']['backups'])) {
            $val = $params['configoptions']['backups'];
            $backups = ($val === 'yes' || $val === 'Yes' || $val === 'Enable' || $val === 'enable' || $val == 1);
        }

        // Determine Hostname
        $hostname = !empty($params['domain']) ? $params['domain'] : 'vps-' . $params['serviceid'] . '.local';
        $hostname = preg_replace('/[^a-zA-Z0-9\.\-]/', '-', $hostname);

        // Prepare internal labels for tracking (client and service ID)
        $labels = [
            'whmcs_client_id'  => (string)$params['userid'],
            'whmcs_service_id' => (string)$params['serviceid'],
            'provisioned_by'   => 'whmcs',
        ];

        // Create server (no firewall ID, no SSH keys)
        $createResult = $api->createServer(
            $hostname,
            $serverType,
            $image,
            $location,
            $backups,
            null,
            [],
            $labels
        );

        $serverId     = $createResult['server']['id'] ?? null;
        $rootPassword = $createResult['root_password'] ?? '';

        if (!$serverId) {
            throw new Exception("API did not return a valid server ID.");
        }

        // Extract IP addresses directly from server creation response
        $ipv4 = $createResult['server']['public_net']['ipv4']['ip'] ?? '';
        $ipv6 = $createResult['server']['public_net']['ipv6']['ip'] ?? '';

        // Fallback polling loop if IP address is not populated in the initial response
        if (empty($ipv4)) {
            for ($i = 0; $i < 10; $i++) {
                $server = $api->getServer($serverId);
                if ($server && isset($server['public_net']['ipv4']['ip'])) {
                    $ipv4 = $server['public_net']['ipv4']['ip'];
                    $ipv6 = $server['public_net']['ipv6']['ip'] ?? '';
                    break;
                }
                sleep(1);
            }
        }

        // Save server details back to WHMCS
        $updateData = [
            'username'    => 'root',
            'dedicatedip' => $ipv4,
            'assignedips' => $ipv6,
        ];

        if (!empty($rootPassword)) {
            $updateData['password'] = encrypt($rootPassword);
        }

        Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->update($updateData);

        // Store Server ID in custom field
        hz_cloud_SetCustomField($params['serviceid'], 'Server ID', $serverId);

    } catch (\Exception $e) {
        logModuleCall('hz_cloud', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }

    return 'success';
}

/**
 * Suspend Server.
 */
function hz_cloud_SuspendAccount(array $params)
{
    try {
        $api      = new HetznerAPI($params['serverpassword']);
        $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
        if (!$serverId) {
            throw new Exception("Server ID not found for this service.");
        }
        $api->powerOff($serverId);
        logModuleCall('hz_cloud', __FUNCTION__, $params, 'Server powered off for suspension');
    } catch (\Exception $e) {
        logModuleCall('hz_cloud', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Unsuspend Server.
 */
function hz_cloud_UnsuspendAccount(array $params)
{
    try {
        $api      = new HetznerAPI($params['serverpassword']);
        $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
        if (!$serverId) {
            throw new Exception("Server ID not found for this service.");
        }
        $api->powerOn($serverId);
        logModuleCall('hz_cloud', __FUNCTION__, $params, 'Server powered on for unsuspension');
    } catch (\Exception $e) {
        logModuleCall('hz_cloud', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Terminate/Delete Server.
 */
function hz_cloud_TerminateAccount(array $params)
{
    try {
        $api      = new HetznerAPI($params['serverpassword']);
        $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
        if (!$serverId) {
            throw new Exception("Server ID not found for this service.");
        }

        $api->deleteServer($serverId);

        hz_cloud_SetCustomField($params['serviceid'], 'Server ID', '');

    } catch (\Exception $e) {
        logModuleCall('hz_cloud', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Upgrade or Downgrade Server Package (Resize).
 */
function hz_cloud_ChangePackage(array $params)
{
    try {
        $api      = new HetznerAPI($params['serverpassword']);
        $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
        if (!$serverId) {
            throw new Exception("Server ID not found for this service.");
        }

        $newServerType = $params['configoption2'];
        if (isset($params['configoptions']['Server Type'])) {
            $newServerType = $params['configoptions']['Server Type'];
        } elseif (isset($params['configoptions']['server_type'])) {
            $newServerType = $params['configoptions']['server_type'];
        }

        if ($newServerType === 'api-fetched') {
            throw new Exception("Please select a valid Server Type plan for the upgrade/downgrade package.");
        }

        $serverObj    = $api->getServer($serverId);
        $currentState = $serverObj['status'] ?? 'off';

        if ($currentState === 'running') {
            $api->powerOff($serverId);
            for ($j = 0; $j < 25; $j++) {
                sleep(1);
                $srv = $api->getServer($serverId);
                if (($srv['status'] ?? '') === 'off') {
                    break;
                }
            }
        }

        try {
            $api->changeType($serverId, $newServerType, true);
        } catch (\Exception $e) {
            // changeType failed — check whether the server is actually off yet.
            // If not, wait a little longer to handle race conditions, then retry
            // once with the same upgrade_disk=true flag.
            // Do NOT fall back to upgrade_disk=false: silently skipping a disk
            // upgrade would leave the server with mismatched storage.
            $srv = $api->getServer($serverId);
            if (($srv['status'] ?? '') !== 'off') {
                for ($k = 0; $k < 15; $k++) {
                    sleep(2);
                    $srv = $api->getServer($serverId);
                    if (($srv['status'] ?? '') === 'off') {
                        break;
                    }
                }
            }
            // Re-throw if still not off after waiting
            if (($srv['status'] ?? '') !== 'off') {
                throw new Exception("Server did not reach 'off' state before resize. Original error: " . $e->getMessage());
            }
            $api->changeType($serverId, $newServerType, true);
        }

        if ($currentState === 'running') {
            $api->powerOn($serverId);
        }

    } catch (\Exception $e) {
        logModuleCall('hz_cloud', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Admin Area output shown on the service management page in the admin panel.
 */
function hz_cloud_AdminArea(array $params)
{
    $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
    if (!$serverId) {
        return '<p><strong>Hetzner Server ID:</strong> Not provisioned yet.</p>';
    }

    $projectId = !empty($params['serverusername']) ? trim($params['serverusername']) : '-';
    $output  = '<p><strong>Server ID:</strong> ' . htmlspecialchars($serverId, ENT_QUOTES, 'UTF-8') . '</p>';
    $output .= '<p><a href="https://console.hetzner.com/projects/' . urlencode($projectId) . '/servers/' . (int)$serverId . '/overview" target="_blank" rel="noopener noreferrer" class="btn btn-default btn-sm"><i class="fa fa-external-link"></i> Open Console</a></p>';

    try {
        $api    = new HetznerAPI($params['serverpassword']);
        $server = $api->getServer($serverId);
        if ($server) {
            $status = htmlspecialchars($server['status'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
            $ipv4   = htmlspecialchars($server['public_net']['ipv4']['ip'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $type   = htmlspecialchars(strtoupper($server['server_type']['name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
            $dc     = htmlspecialchars($server['datacenter']['description'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $output .= "<p><strong>Status:</strong> {$status} &nbsp;|&nbsp; <strong>IPv4:</strong> {$ipv4} &nbsp;|&nbsp; <strong>Plan:</strong> {$type} &nbsp;|&nbsp; <strong>DC:</strong> {$dc}</p>";
        }

        // Fetch server action logs (last 10)
        $actions = $api->getServerActions($serverId, 10);
        if (!empty($actions)) {
            $output .= '<br><h4><strong>Server Activity Logs (Last 10 Actions)</strong></h4>';
            $output .= '<table class="table table-striped table-condensed table-hover" style="font-size:12px; margin-top: 8px;">';
            $output .= '<thead><tr><th>ID</th><th>Action</th><th>Status</th><th>Started</th><th>Finished</th><th>Details</th></tr></thead>';
            $output .= '<tbody>';
            foreach ($actions as $action) {
                $actionId = (int)$action['id'];
                $command = htmlspecialchars(str_replace('_', ' ', $action['command'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');
                $command = ucwords($command);
                
                $statusVal = $action['status'] ?? 'unknown';
                if ($statusVal === 'success') {
                    $statusHtml = '<span class="label label-success">Success</span>';
                } elseif ($statusVal === 'running') {
                    $statusHtml = '<span class="label label-warning">Running (' . (int)($action['progress'] ?? 0) . '%)</span>';
                } elseif ($statusVal === 'error') {
                    $statusHtml = '<span class="label label-danger">Failed</span>';
                } else {
                    $statusHtml = '<span class="label label-default">' . htmlspecialchars(ucfirst($statusVal), ENT_QUOTES, 'UTF-8') . '</span>';
                }
                
                $started = !empty($action['started']) ? date('Y-m-d H:i:s', strtotime($action['started'])) : 'N/A';
                $finished = !empty($action['finished']) ? date('Y-m-d H:i:s', strtotime($action['finished'])) : 'N/A';
                
                $details = '';
                if (!empty($action['error']['message'])) {
                    $details = '<span class="text-danger">' . htmlspecialchars($action['error']['message'], ENT_QUOTES, 'UTF-8') . '</span>';
                } else {
                    $details = 'Completed successfully';
                }
                
                $output .= "<tr>
                    <td>{$actionId}</td>
                    <td><strong>{$command}</strong></td>
                    <td>{$statusHtml}</td>
                    <td>{$started}</td>
                    <td>{$finished}</td>
                    <td><small>{$details}</small></td>
                </tr>";
            }
            $output .= '</tbody></table>';
        } else {
            $output .= '<p class="text-muted">No activity logs found for this server.</p>';
        }
    } catch (\Exception $e) {
        $output .= '<p class="text-danger"><strong>Error fetching live details:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    return $output;
}

/**
 * Custom admin button array – extra buttons shown on the service admin page.
 */
function hz_cloud_AdminCustomButtonArray()
{
    return [
        'Power On'  => 'PowerOn',
        'Power Off' => 'PowerOff',
        'Reboot'    => 'Reboot',
        'Sync Server' => 'Sync',
    ];
}

/**
 * Custom client area button array – extra buttons shown on the service page in client area.
 */
function hz_cloud_ClientAreaCustomButtonArray()
{
    return [
        'Start'  => 'PowerOn',
        'Stop'   => 'PowerOff',
        'Reboot' => 'Reboot',
    ];
}

/**
 * Handlers for the custom admin buttons defined above.
 */
function hz_cloud_PowerOn(array $params)
{
    try {
        $api      = new HetznerAPI($params['serverpassword']);
        $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
        if (!$serverId) {
            throw new Exception('Server ID not found for this service.');
        }
        $api->powerOn($serverId);
        logModuleCall('hz_cloud', __FUNCTION__, $params, 'Power On sent');
    } catch (\Exception $e) {
        logModuleCall('hz_cloud', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

function hz_cloud_PowerOff(array $params)
{
    try {
        $api      = new HetznerAPI($params['serverpassword']);
        $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
        if (!$serverId) {
            throw new Exception('Server ID not found for this service.');
        }
        $api->powerOff($serverId);
        logModuleCall('hz_cloud', __FUNCTION__, $params, 'Power Off sent');
    } catch (\Exception $e) {
        logModuleCall('hz_cloud', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

function hz_cloud_Reboot(array $params)
{
    try {
        $api      = new HetznerAPI($params['serverpassword']);
        $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
        if (!$serverId) {
            throw new Exception('Server ID not found for this service.');
        }
        $api->reboot($serverId);
        logModuleCall('hz_cloud', __FUNCTION__, $params, 'Reboot sent');
    } catch (\Exception $e) {
        logModuleCall('hz_cloud', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

function hz_cloud_Sync(array $params)
{
    try {
        $apiToken = $params['serverpassword'];
        if (empty($apiToken)) {
            throw new Exception("Cloud API Token is missing from WHMCS server configuration.");
        }

        $api      = new HetznerAPI($apiToken);
        $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
        
        $server = null;
        if ($serverId) {
            try {
                $server = $api->getServer($serverId);
            } catch (\Exception $e) {
                // If fetching by ID fails, fallback to search by label or name
            }
        }
        
        if (!$server) {
            // Locate server by checking all servers and matching label or hostname
            $servers = $api->getServers();
            foreach ($servers as $s) {
                $labels = $s['labels'] ?? [];
                if (isset($labels['whmcs_service_id']) && (string)$labels['whmcs_service_id'] === (string)$params['serviceid']) {
                    $server = $s;
                    $serverId = $s['id'];
                    hz_cloud_SetCustomField($params['serviceid'], 'Server ID', $serverId);
                    break;
                }
            }
        }

        if (!$server) {
            throw new Exception('Server not found in Hetzner Cloud for this service.');
        }

        // Pull IP addresses and update WHMCS record if missing/different
        $ipv4 = $server['public_net']['ipv4']['ip'] ?? '';
        $ipv6 = $server['public_net']['ipv6']['ip'] ?? '';

        $updateData = [];
        $hosting = Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->first();

        if ($hosting) {
            if ($hosting->dedicatedip !== $ipv4) {
                $updateData['dedicatedip'] = $ipv4;
            }
            if ($hosting->assignedips !== $ipv6) {
                $updateData['assignedips'] = $ipv6;
            }

            if (!empty($updateData)) {
                Capsule::table('tblhosting')
                    ->where('id', $params['serviceid'])
                    ->update($updateData);
            }
        }

        // Add labels if they do not exist or are incorrect
        $currentLabels = $server['labels'] ?? [];
        $expectedLabels = [
            'whmcs_client_id'  => (string)$params['userid'],
            'whmcs_service_id' => (string)$params['serviceid'],
            'provisioned_by'   => 'whmcs',
        ];

        $labelsNeedUpdate = false;
        foreach ($expectedLabels as $key => $val) {
            if (!isset($currentLabels[$key]) || (string)$currentLabels[$key] !== (string)$val) {
                $currentLabels[$key] = $val;
                $labelsNeedUpdate = true;
            }
        }

        if ($labelsNeedUpdate) {
            $api->updateServerLabels($serverId, $currentLabels);
        }

        logModuleCall('hz_cloud', __FUNCTION__, $params, 'Sync completed successfully');
    } catch (\Exception $e) {
        logModuleCall('hz_cloud', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Additional fields shown on the admin service details tab.
 */
function hz_cloud_AdminServicesTabFields(array $params)
{
    $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
    if (!$serverId) {
        return [
            'Server ID' => '<em>Not provisioned</em>',
        ];
    }

    $activityLogsHtml = '';
    try {
        $api    = new HetznerAPI($params['serverpassword']);
        $server = $api->getServer($serverId);
        
        $serverInfo = '';
        if ($server) {
            $status = htmlspecialchars($server['status'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
            $ipv4   = htmlspecialchars($server['public_net']['ipv4']['ip'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $type   = htmlspecialchars(strtoupper($server['server_type']['name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
            $dc     = htmlspecialchars($server['datacenter']['description'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            
            $statusLabelClass = 'label-default';
            if ($status === 'running') {
                $statusLabelClass = 'label-success';
            } elseif ($status === 'off') {
                $statusLabelClass = 'label-danger';
            }
            
            $serverInfo = "Status: <span class=\"label {$statusLabelClass}\">" . ucfirst($status) . "</span> &nbsp;|&nbsp; IPv4: <strong>{$ipv4}</strong> &nbsp;|&nbsp; Plan: <strong>{$type}</strong> &nbsp;|&nbsp; Datacenter: <strong>{$dc}</strong><br><br>";
        }

        // Fetch server action logs (last 10)
        $actions = $api->getServerActions($serverId, 10);
        if (!empty($actions)) {
            $logsTable = '<table class="table table-striped table-condensed table-hover" style="font-size:12px; margin-top: 8px; width: 100%; max-width: 100%;">';
            $logsTable .= '<thead><tr><th>ID</th><th>Action</th><th>Status</th><th>Started</th><th>Finished</th><th>Details</th></tr></thead>';
            $logsTable .= '<tbody>';
            foreach ($actions as $action) {
                $actionId = (int)$action['id'];
                $command = htmlspecialchars(str_replace('_', ' ', $action['command'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');
                $command = ucwords($command);
                
                $statusVal = $action['status'] ?? 'unknown';
                if ($statusVal === 'success') {
                    $statusHtml = '<span class="label label-success">Success</span>';
                } elseif ($statusVal === 'running') {
                    $statusHtml = '<span class="label label-warning">Running (' . (int)($action['progress'] ?? 0) . '%)</span>';
                } elseif ($statusVal === 'error') {
                    $statusHtml = '<span class="label label-danger">Failed</span>';
                } else {
                    $statusHtml = '<span class="label label-default">' . htmlspecialchars(ucfirst($statusVal), ENT_QUOTES, 'UTF-8') . '</span>';
                }
                
                $started = !empty($action['started']) ? date('Y-m-d H:i:s', strtotime($action['started'])) : 'N/A';
                $finished = !empty($action['finished']) ? date('Y-m-d H:i:s', strtotime($action['finished'])) : 'N/A';
                
                $details = '';
                if (!empty($action['error']['message'])) {
                    $details = '<span class="text-danger">' . htmlspecialchars($action['error']['message'], ENT_QUOTES, 'UTF-8') . '</span>';
                } else {
                    $details = 'Completed successfully';
                }
                
                $logsTable .= "<tr>
                    <td>{$actionId}</td>
                    <td><strong>{$command}</strong></td>
                    <td>{$statusHtml}</td>
                    <td>{$started}</td>
                    <td>{$finished}</td>
                    <td><small>{$details}</small></td>
                </tr>";
            }
            $logsTable .= '</tbody></table>';
            
            $activityLogsHtml = $serverInfo . $logsTable;
        } else {
            $activityLogsHtml = $serverInfo . '<p class="text-muted">No activity logs found for this server.</p>';
        }
    } catch (\Exception $e) {
        $activityLogsHtml = '<p class="text-danger"><strong>Error fetching live details:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    return [
        'Server ID' => $serverId,
        'Server Activity Logs' => $activityLogsHtml,
    ];
}

/**
 * Login Link – allows admins to jump directly to the Hetzner Console from the WHMCS admin.
 */
function hz_cloud_LoginLink(array $params)
{
    $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
    if (!$serverId) {
        return '';
    }
    $projectId = !empty($params['serverusername']) ? trim($params['serverusername']) : '-';
    $url = 'https://console.hetzner.com/projects/' . urlencode($projectId) . '/servers/' . (int)$serverId . '/overview';
    echo '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">Open Console</a>';
}

/**
 * Test Connection (Admin Server page).
 */
function hz_cloud_TestConnection(array $params)
{
    try {
        $api = new HetznerAPI($params['serverpassword']);
        $api->getLocations();
        return ['success' => true, 'error' => ''];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Client area interactive control panel dashboard.
 */
function hz_cloud_ClientArea(array $params)
{
    $serverId = hz_cloud_GetCustomField($params['serviceid'], 'Server ID');
    if (!$serverId) {
        return [
            'tabOverviewReplacementTemplate' => 'templates/error.tpl',
            'templateVariables' => [
                'usefulErrorHelper' => 'This virtual server is not yet provisioned.',
            ],
        ];
    }

    // Read client area visibility / permission toggles from configoptions
    $allowVnc           = isset($params['configoption3']) ? ($params['configoption3'] === 'on' || $params['configoption3'] === '1' || $params['configoption3'] == 1) : true;
    $allowCharts        = true; // Always visible as per request
    $allowPasswordReset = isset($params['configoption4']) ? ($params['configoption4'] === 'on' || $params['configoption4'] === '1' || $params['configoption4'] == 1) : true;
    $allowIso           = isset($params['configoption5']) ? ($params['configoption5'] === 'on' || $params['configoption5'] === '1' || $params['configoption5'] == 1) : true;
    $allowReinstall     = isset($params['configoption6']) ? ($params['configoption6'] === 'on' || $params['configoption6'] === '1' || $params['configoption6'] == 1) : true;
    $allowSnapReinstall = isset($params['configoption7']) ? ($params['configoption7'] === 'on' || $params['configoption7'] === '1' || $params['configoption7'] == 1) : true;
    $showPtr            = isset($params['configoption8']) ? ($params['configoption8'] === 'on' || $params['configoption8'] === '1' || $params['configoption8'] == 1) : true;
    $hideHostname       = isset($params['configoption9']) ? ($params['configoption9'] === 'on' || $params['configoption9'] === '1' || $params['configoption9'] == 1) : false;

    $apiToken     = $params['serverpassword'];
    $customAction = isset($_REQUEST['customAction']) ? $_REQUEST['customAction'] : '';

    // ----------------------------------------------------------------
    // AJAX: metrics endpoint
    // ----------------------------------------------------------------
    if ($customAction === 'metrics') {
        header('Content-Type: application/json');
        try {
            $api       = new HetznerAPI($apiToken);
            $timeframe = isset($_REQUEST['timeframe']) ? trim($_REQUEST['timeframe']) : '1d';
            
            $end = gmdate('Y-m-d\TH:i:s\Z');
            if ($timeframe === '1h') {
                $start = gmdate('Y-m-d\TH:i:s\Z', time() - 3600);
            } elseif ($timeframe === '7d') {
                $start = gmdate('Y-m-d\TH:i:s\Z', time() - 7 * 86400);
            } elseif ($timeframe === '30d') {
                $start = gmdate('Y-m-d\TH:i:s\Z', time() - 30 * 86400);
            } else {
                // Default 1d (24 hours)
                $start = gmdate('Y-m-d\TH:i:s\Z', time() - 86400);
            }

            $metrics = $api->getMetrics($serverId, 'cpu,disk,network', $start, $end);
            echo json_encode($metrics);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        die();
    }



    try {
        $api           = new HetznerAPI($apiToken);
        $actionMessage = '';
        $actionError   = '';
        $newPassword   = '';

        // Read flash messages from session if they exist
        if (isset($_SESSION['hz_cloud_action_msg'])) {
            $actionMessage = $_SESSION['hz_cloud_action_msg'];
            unset($_SESSION['hz_cloud_action_msg']);
        }
        if (isset($_SESSION['hz_cloud_action_err'])) {
            $actionError = $_SESSION['hz_cloud_action_err'];
            unset($_SESSION['hz_cloud_action_err']);
        }
        if (isset($_SESSION['hz_cloud_new_password'])) {
            $newPassword = $_SESSION['hz_cloud_new_password'];
            unset($_SESSION['hz_cloud_new_password']);
        }

        // ----------------------------------------------------------------
        // Process POST actions
        // ----------------------------------------------------------------
        if ($customAction) {
            header('Content-Type: application/json');
            try {
                switch ($customAction) {

                    // ---- Power Management ----
                    case 'poweron':
                        $api->powerOn($serverId);
                        $actionMessage = 'Boot signal sent. Server is starting up...';
                        break;

                    case 'poweroff':
                        $api->powerOff($serverId);
                        $actionMessage = 'Shutdown signal sent. Server is powering off...';
                        break;

                    case 'shutdown':
                        $api->shutdown($serverId);
                        $actionMessage = 'Graceful shutdown signal sent. Server is shutting down...';
                        break;

                    case 'reboot':
                        $api->reboot($serverId);
                        $actionMessage = 'Reboot signal sent. Server is restarting...';
                        break;

                    // ---- Password Reset ----
                    case 'resetpassword':
                        $result      = $api->resetPassword($serverId);
                        $newPassword = $result['root_password'] ?? ($result['action']['resources'][0]['password'] ?? '');
                        if (!empty($newPassword)) {
                            Capsule::table('tblhosting')
                                ->where('id', $params['serviceid'])
                                ->update(['password' => encrypt($newPassword)]);
                            $actionMessage = 'Root password reset successfully. Please copy the new password shown below.';
                        } else {
                            $actionMessage = 'Password reset request has been sent.';
                        }
                        break;

                    // ---- OS Rebuild ----
                    case 'rebuild':
                        $rebuildImage = isset($_POST['rebuild_image']) ? trim($_POST['rebuild_image']) : '';
                        if (empty($rebuildImage)) {
                            throw new Exception("Please select an operating system image.");
                        }
                        $result      = $api->rebuild($serverId, $rebuildImage);
                        $newPassword = $result['root_password'] ?? '';
                        if (!empty($newPassword)) {
                            Capsule::table('tblhosting')
                                ->where('id', $params['serviceid'])
                                ->update(['password' => encrypt($newPassword)]);
                            $actionMessage = "OS reinstallation started. Your new root password is shown below.";
                        } else {
                            $actionMessage = "OS reinstallation has started. The server is being rebuilt.";
                        }
                        break;

                    // ---- Rescue Mode ----
                    case 'enable_rescue':
                        $rescueType = isset($_POST['rescue_type']) ? trim($_POST['rescue_type']) : 'linux64';
                        $sshKeys    = [];
                        $result      = $api->enableRescue($serverId, $rescueType, $sshKeys);
                        $newPassword = $result['root_password'] ?? '';
                        $api->reboot($serverId);
                        if (!empty($newPassword)) {
                            Capsule::table('tblhosting')
                                ->where('id', $params['serviceid'])
                                ->update(['password' => encrypt($newPassword)]);
                            $actionMessage = "Rescue mode enabled. Server is rebooting. Temporary rescue password is shown below.";
                        } else {
                            $actionMessage = "Rescue mode enabled. Server is rebooting into rescue environment.";
                        }
                        break;

                    case 'disable_rescue':
                        $api->disableRescue($serverId);
                        $api->reboot($serverId);
                        $actionMessage = "Rescue mode disabled. Server is rebooting into your normal OS.";
                        break;

                    // ---- Network / Hostname ----
                    case 'update_hostname':
                        $newHostname = isset($_POST['new_hostname']) ? trim($_POST['new_hostname']) : '';
                        if (empty($newHostname)) {
                            throw new Exception("Please specify a valid hostname.");
                        }
                        $cleanHostname = preg_replace('/[^a-zA-Z0-9\.\-]/', '-', $newHostname);
                        if ($cleanHostname !== $newHostname) {
                            throw new Exception("Invalid hostname characters. Use letters, numbers, dots, and hyphens only.");
                        }
                        $api->renameServer($serverId, $newHostname);
                        Capsule::table('tblhosting')
                            ->where('id', $params['serviceid'])
                            ->update(['domain' => $newHostname]);
                        $actionMessage = "Server hostname changed to '{$newHostname}'.";
                        break;

                    case 'update_rdns':
                        $rdnsValue = isset($_POST['rdns_value']) ? trim($_POST['rdns_value']) : '';
                        $ipAddress = isset($_POST['ip_address']) ? trim($_POST['ip_address']) : '';
                        if (empty($ipAddress)) {
                            throw new Exception("Unable to identify server primary IP address.");
                        }
                        $api->changeDnsPtr($serverId, $ipAddress, $rdnsValue);
                        $actionMessage = "Reverse DNS (PTR) record updated successfully.";
                        break;

                    case 'update_rdns_ipv6':
                        $rdnsValue = isset($_POST['rdns_ipv6_value']) ? trim($_POST['rdns_ipv6_value']) : '';
                        $ipv6Addr  = isset($_POST['ipv6_address']) ? trim($_POST['ipv6_address']) : '';
                        if (empty($ipv6Addr)) {
                            throw new Exception("Unable to identify server IPv6 address.");
                        }
                        // Strip subnet mask if present
                        $ipv6Addr = explode('/', $ipv6Addr)[0];
                        // Ensure suffix is added if required (Hetzner expects the specific IP from the block, e.g. ::1)
                        if (substr($ipv6Addr, -2) === '::') {
                            $ipv6Addr .= '1';
                        }
                        $api->changeDnsPtr($serverId, $ipv6Addr, $rdnsValue);
                        $actionMessage = "IPv6 Reverse DNS (PTR) record updated successfully.";
                        break;

                    // ---- Backup Management ----
                    case 'enable_backups':
                        $api->enableBackups($serverId);
                        $actionMessage = "Automated daily backups have been enabled on your server.";
                        break;

                    case 'disable_backups':
                        $api->disableBackups($serverId);
                        $actionMessage = "Automated daily backups have been disabled on your server.";
                        break;



                    // ---- ISO Management ----
                    case 'attach_iso':
                        $isoName = isset($_POST['iso_name']) ? trim($_POST['iso_name']) : '';
                        if (empty($isoName)) {
                            throw new Exception("Please select an ISO to mount.");
                        }
                        $api->attachISO($serverId, $isoName);
                        $actionMessage = "ISO '{$isoName}' has been attached to your server.";
                        break;

                    case 'detach_iso':
                        $api->detachISO($serverId);
                        $actionMessage = "ISO image has been detached from your server.";
                        break;
                }

                echo json_encode([
                    'success' => true,
                    'message' => $actionMessage ?: 'Action completed successfully.',
                    'newPassword' => $newPassword
                ]);
            } catch (\Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            die();
        }

        // ----------------------------------------------------------------
        // Fetch current server details
        // ----------------------------------------------------------------
        $server = $api->getServer($serverId);
        if (!$server) {
            throw new Exception("Server details could not be loaded.");
        }

        // Recent Actions history (last 10)
        $recentActions = [];
        try {
            $actions       = $api->getServerActions($serverId, 10);
            $recentActions = $actions;
        } catch (\Exception $ex) { }

        // OS Images for rebuild
        $osImages = [];
        try {
            $imagesList = $api->getImages();
            foreach ($imagesList as $img) {
                if ($img['status'] === 'available') {
                    $osImages[] = ['name' => $img['name'], 'description' => $img['description']];
                }
            }
        } catch (\Exception $ex) {
            $osImages = [
                ['name' => 'ubuntu-24.04', 'description' => 'Ubuntu 24.04 LTS'],
                ['name' => 'ubuntu-22.04', 'description' => 'Ubuntu 22.04 LTS'],
                ['name' => 'debian-12',    'description' => 'Debian 12'],
                ['name' => 'debian-11',    'description' => 'Debian 11'],
                ['name' => 'rocky-9',      'description' => 'Rocky Linux 9'],
                ['name' => 'alma-9',       'description' => 'AlmaLinux 9'],
            ];
        }

        // Include snapshots and backups on reinstall if allowed
        if ($allowSnapReinstall) {
            try {
                $snapshotsList = $api->getSnapshots($serverId);
                foreach ($snapshotsList as $snap) {
                    $osImages[] = [
                        'name'        => (string)$snap['id'],
                        'description' => 'Snapshot: ' . ($snap['description'] ?: 'Snapshot #' . $snap['id']) . ' (' . number_format((float)($snap['image_size'] ?? 0), 2) . ' GB)'
                    ];
                }
            } catch (\Exception $ex) {}

            try {
                $backupsList = $api->getBackupImages($serverId);
                foreach ($backupsList as $back) {
                    $osImages[] = [
                        'name'        => (string)$back['id'],
                        'description' => 'Backup: ' . ($back['description'] ?: 'Backup #' . $back['id']) . ' (' . number_format((float)($back['image_size'] ?? 0), 2) . ' GB)'
                    ];
                }
            } catch (\Exception $ex) {}
        }

        // Available ISOs
        $isoList = [];
        try {
            $isos = $api->getISOs();
            foreach ($isos as $iso) {
                $isoList[] = [
                    'name'        => $iso['name'],
                    'description' => $iso['description'] ?? $iso['name'],
                    'deprecated'  => $iso['deprecated'] ?? false,
                ];
            }
            usort($isoList, function ($a, $b) {
                return strcasecmp($a['description'], $b['description']);
            });
        } catch (\Exception $ex) { }

        // Parse server details
        $vpsType  = $server['server_type']['name'] ?? 'Custom';
        $cores    = $server['server_type']['cores'] ?? 0;
        $memory   = $server['server_type']['memory'] ?? 0;
        $disk     = $server['server_type']['disk'] ?? 0;

        $status   = $server['status'] ?? 'unknown';
        $ipv4     = $server['public_net']['ipv4']['ip'] ?? 'None';
        $ipv6     = $server['public_net']['ipv6']['ip'] ?? 'None';
        $rdnsIpv4 = $server['public_net']['ipv4']['dns_ptr'] ?? '';
        $rdnsIpv6 = '';
        if (!empty($server['public_net']['ipv6']['dns_ptr']) && is_array($server['public_net']['ipv6']['dns_ptr'])) {
            $rdnsIpv6 = $server['public_net']['ipv6']['dns_ptr'][0]['dns_ptr'] ?? '';
        }

        $datacenter        = $server['datacenter']['description'] ?? 'Datacenter';
        $location          = $server['datacenter']['location']['city'] ?? ($server['datacenter']['location']['name'] ?? 'Unknown');
        $countryCode       = strtoupper($server['datacenter']['location']['country'] ?? '');
        $countryMap = [
            'DE' => 'Germany',
            'FI' => 'Finland',
            'US' => 'United States',
            'SG' => 'Singapore',
        ];
        $countryName       = $countryMap[$countryCode] ?? $countryCode;
        $country           = ($location && $location !== $countryName) ? $location . ', ' . $countryName : $countryName;
        $imageDescription  = $server['image']['description'] ?? ($server['image']['name'] ?? 'Unknown Image');
        $backupsEnabled    = ($server['backups_enabled'] ?? false) ? 'Enabled' : 'Disabled';
        $backupsEnabledBool= (bool)($server['backups_enabled'] ?? false);
        $rescueEnabled     = (bool)($server['rescue_enabled'] ?? false);

        // Mounted ISO info
        $mountedIso    = null;
        $isoAttached   = false;
        if (!empty($server['iso'])) {
            $mountedIso  = $server['iso'];
            $isoAttached = true;
        }

        // Backups purchased check & retrieval
        $backupsPurchased = false;
        if (isset($params['configoptions']['Backups'])) {
            $val = $params['configoptions']['Backups'];
            $backupsPurchased = ($val === 'yes' || $val === 'Yes' || $val === 'Enable' || $val === 'enable' || $val == 1);
        } elseif (isset($params['configoptions']['backups'])) {
            $val = $params['configoptions']['backups'];
            $backupsPurchased = ($val === 'yes' || $val === 'Yes' || $val === 'Enable' || $val === 'enable' || $val == 1);
        }

        $backupImages = [];
        if ($backupsPurchased) {
            try {
                $backupImages = $api->getBackupImages($serverId);
            } catch (\Exception $ex) {
                // Ignore API exceptions for backups
            }
        }

        // Check for pending actions
        $isPending = false;
        foreach ($recentActions as $act) {
            if ($act['status'] === 'running') {
                $isPending = true;
                break;
            }
        }

        return [
            'tabOverviewReplacementTemplate' => 'templates/overview.tpl',
            'templateVariables' => [
                'serverId'          => $serverId,
                'vpsName'           => $server['name'],
                'vpsType'           => strtoupper($vpsType),
                'cores'             => $cores,
                'memory'            => $memory,
                'disk'              => $disk,
                'status'            => $status,
                'ipv4'              => $ipv4,
                'ipv6'              => $ipv6,
                'rdnsValue'         => $rdnsIpv4,
                'rdnsIpv6'          => $rdnsIpv6,
                'datacenter'        => $datacenter,
                'location'          => $location,
                'country'           => $country,
                'imageDescription'  => $imageDescription,
                'backupsEnabled'    => $backupsEnabled,
                'backupsEnabledBool'=> $backupsEnabledBool,
                'recentActions'     => $recentActions,
                'osImages'          => $osImages,
                'isoList'           => $isoList,
                'isoAttached'       => $isoAttached,
                'mountedIso'        => $mountedIso,
                'actionMessage'     => $actionMessage,
                'actionError'       => $actionError,
                'newPassword'       => $newPassword,
                'isPending'         => $isPending,
                'rescueEnabled'     => $rescueEnabled,
                'backupsPurchased'  => $backupsPurchased,
                'backupImages'      => $backupImages,
                'whmcsClientId'     => $params['userid'],
                'whmcsServiceId'    => $params['serviceid'],
                
                // New admin settings visibility variables
                'allowVnc'           => $allowVnc,
                'allowCharts'        => $allowCharts,
                'allowPasswordReset' => $allowPasswordReset,
                'allowIso'           => $allowIso,
                'allowReinstall'     => $allowReinstall,
                'showPtr'            => $showPtr,
                'hideHostname'       => $hideHostname,
            ],
        ];

    } catch (\Exception $e) {
        return [
            'tabOverviewReplacementTemplate' => 'templates/error.tpl',
            'templateVariables' => [
                'usefulErrorHelper' => $e->getMessage(),
            ],
        ];
    }
}

/**
 * Hook to return the Usage Metric Provider instance.
 *
 * @param array $params
 * @return \WHMCS\Module\Server\HzCloud\MetricProvider
 */
function hz_cloud_MetricProvider(array $params)
{
    require_once __DIR__ . '/lib/MetricProvider.php';
    return new \WHMCS\Module\Server\HzCloud\MetricProvider($params);
}

// =========================================================
// Custom Field Database Helper Functions
// =========================================================

function hz_cloud_GetCustomField($serviceId, $fieldName)
{
    try {
        $packageId = Capsule::table('tblhosting')->where('id', $serviceId)->value('packageid');
        if (!$packageId) {
            return null;
        }

        $customField = Capsule::table('tblcustomfields')
            ->where('relid', $packageId)
            ->where('type', 'product')
            ->where(function($q) use ($fieldName) {
                $q->where('fieldname', $fieldName)
                  ->orWhere('fieldname', 'like', $fieldName . '|%');
            })
            ->first();

        if ($customField) {
            return Capsule::table('tblcustomfieldsvalues')
                ->where('relid', $serviceId)
                ->where('fieldid', $customField->id)
                ->value('value');
        }
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('hz_cloud', 'GetCustomField', ['serviceId' => $serviceId, 'fieldName' => $fieldName], $e->getMessage(), $e->getTraceAsString());
        }
    }
    return null;
}

function hz_cloud_SetCustomField($serviceId, $fieldName, $value)
{
    try {
        $packageId = Capsule::table('tblhosting')->where('id', $serviceId)->value('packageid');
        if (!$packageId) {
            return;
        }

        $customField = Capsule::table('tblcustomfields')
            ->where('relid', $packageId)
            ->where('type', 'product')
            ->where(function($q) use ($fieldName) {
                $q->where('fieldname', $fieldName)
                  ->orWhere('fieldname', 'like', $fieldName . '|%');
            })
            ->first();

        if (!$customField) {
            // Create custom field automatically if missing using Server ID|VPS ID format
            $insertName = ($fieldName === 'Server ID') ? 'Server ID|VPS ID' : $fieldName;

            $fieldId = Capsule::table('tblcustomfields')->insertGetId([
                'type'        => 'product',
                'relid'       => $packageId,
                'fieldname'   => $insertName,
                'fieldtype'   => 'text',
                'description' => 'Cloud Server Reference ID',
                'showorder'   => '',
                'showinvoice' => '',
                'sortorder'   => 0,
                'adminonly'   => 'on',
            ]);
        } else {
            $fieldId = $customField->id;
        }

        $exists = Capsule::table('tblcustomfieldsvalues')
            ->where('relid', $serviceId)
            ->where('fieldid', $fieldId)
            ->exists();

        if ($exists) {
            Capsule::table('tblcustomfieldsvalues')
                ->where('relid', $serviceId)
                ->where('fieldid', $fieldId)
                ->update(['value' => $value]);
        } else {
            Capsule::table('tblcustomfieldsvalues')->insert([
                'relid'   => $serviceId,
                'fieldid' => $fieldId,
                'value'   => $value,
            ]);
        }
    } catch (\Exception $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall('hz_cloud', 'SetCustomField', ['serviceId' => $serviceId, 'fieldName' => $fieldName, 'value' => $value], $e->getMessage(), $e->getTraceAsString());
        }
    }
}
