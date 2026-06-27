<?php
/**
 * Hetzner Cloud API Wrapper Class
 *
 * Provides a clean interface to connect to the Hetzner Cloud REST API v1.
 * Integrates with WHMCS module logging.
 */

class HetznerAPI
{
    private $apiToken;
    private $baseUrl = 'https://api.hetzner.cloud/v1';

    public function __construct($apiToken)
    {
        $this->apiToken = trim($apiToken);
    }

    /**
     * Send a request to the Hetzner Cloud API.
     *
     * @param string $endpoint The endpoint path (e.g., '/servers')
     * @param string $method The HTTP method (GET, POST, DELETE, etc.)
     * @param array|null $data Payload for POST/PUT/PATCH requests
     * @return array The decoded JSON response
     * @throws Exception on curl error or non-2xx API response
     */
    public function request($endpoint, $method = 'GET', $data = null)
    {
        if (empty($this->apiToken)) {
            throw new Exception("Cloud API Token is not configured. Please check your server configuration.");
        }

        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data !== null) {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            $headers[] = 'Content-Length: ' . strlen($jsonData);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Log the API call within WHMCS for auditing and debugging
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'hz_cloud',
                $method . ' ' . $endpoint,
                $data !== null ? json_encode($data, JSON_PRETTY_PRINT) : '',
                $response,
                null
            );
        }

        if ($curlError) {
            throw new Exception("Cloud API Connection Error: " . $curlError);
        }

        $decodedResponse = json_decode($response, true);

        // Allow 204 No Content (successful DELETE)
        if ($httpCode === 204) {
            return [];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMessage = isset($decodedResponse['error']['message'])
                ? $decodedResponse['error']['message']
                : "HTTP error code {$httpCode}";
            throw new Exception("Cloud API Error: " . $errorMessage);
        }

        return $decodedResponse;
    }

    // =========================================================
    // Pricing (account currency)
    // =========================================================

    /**
     * Returns the pricing info including the account's billing currency.
     * Response: { "pricing": { "currency": "EUR", "vat_rate": "...", ... } }
     */
    public function getPricing()
    {
        $response = $this->request('/pricing');
        return isset($response['pricing']) ? $response['pricing'] : [];
    }

    // =========================================================
    // Locations
    // =========================================================
    public function getLocations()
    {
        $response = $this->request('/locations');
        return isset($response['locations']) ? $response['locations'] : [];
    }

    // =========================================================
    // Server Types
    // =========================================================
    /**
     * Retrieve server types from the Hetzner API.
     *
     * Filters to x86 architecture only (no ARM/CAX) and excludes deprecated types.
     * Paginates through all pages using next_page from meta.pagination.
     *
     * @param string|null $architecture  'x86' (default) or null for all architectures.
     */
    public function getServerTypes($architecture = 'x86')
    {
        $allTypes = [];
        $page     = 1;
        $perPage  = 25;

        do {
            $endpoint  = "/server_types?per_page={$perPage}&page={$page}";
            if ($architecture !== null) {
                $endpoint .= '&architecture=' . urlencode($architecture);
            }
            $response = $this->request($endpoint);
            $types    = isset($response['server_types']) ? $response['server_types'] : [];

            foreach ($types as $t) {
                // Skip deprecated server types
                if (!empty($t['deprecated'])) {
                    continue;
                }
                // Double-check architecture in the response body as well
                if ($architecture !== null && isset($t['architecture']) && strtolower($t['architecture']) !== strtolower($architecture)) {
                    continue;
                }
                $allTypes[] = $t;
            }

            $nextPage = isset($response['meta']['pagination']['next_page'])
                ? (int)$response['meta']['pagination']['next_page']
                : null;
            $page = $nextPage ?: null;
        } while ($page !== null);

        return $allTypes;
    }

    // =========================================================
    // Images (System OS Images + Snapshots)
    // =========================================================
    public function getImages()
    {
        $allImages = [];
        $seen      = [];
        $page      = 1;
        $perPage   = 50;

        do {
            $response = $this->request("/images?type=system&status=available&architecture=x86&sort=name:asc&per_page={$perPage}&page={$page}");
            $images   = isset($response['images']) ? $response['images'] : [];

            foreach ($images as $img) {
                if (!isset($seen[$img['name']])) {
                    $seen[$img['name']] = true;
                    $allImages[] = $img;
                }
            }

            // Hetzner paginates via meta.pagination.next_page (null when done),
            // not last_page — using last_page caused an infinite loop on the final page.
            $nextPage = isset($response['meta']['pagination']['next_page'])
                ? (int)$response['meta']['pagination']['next_page']
                : null;

            $page = $nextPage ?: null;
        } while ($page !== null);

        return $allImages;
    }

    public function getSnapshots($serverId = null)
    {
        $endpoint = '/images?type=snapshot';
        if ($serverId) {
            $endpoint .= '&bound_to=' . $serverId;
        }
        $response = $this->request($endpoint);
        return isset($response['images']) ? $response['images'] : [];
    }

    public function getBackupImages($serverId)
    {
        $response = $this->request('/images?type=backup&bound_to=' . $serverId);
        return isset($response['images']) ? $response['images'] : [];
    }

    public function deleteImage($imageId)
    {
        return $this->request("/images/{$imageId}", 'DELETE');
    }

    // =========================================================
    // Server Management
    // =========================================================
    public function getServers()
    {
        $response = $this->request('/servers');
        return isset($response['servers']) ? $response['servers'] : [];
    }

    public function getServer($id)
    {
        $response = $this->request("/servers/{$id}");
        return isset($response['server']) ? $response['server'] : null;
    }

    public function createServer($name, $serverType, $image, $location, $backups = false, $firewallId = null, $sshKeys = [], $labels = [])
    {
        $payload = [
            'name'              => $name,
            'server_type'       => $serverType,
            'image'             => $image,
            'location'          => $location,
            'backups_enabled'   => (bool)$backups,
            'start_after_create'=> true,
        ];

        if (!empty($firewallId)) {
            $payload['firewalls'] = [
                ['firewall' => (int)$firewallId]
            ];
        }

        if (!empty($sshKeys)) {
            $payload['ssh_keys'] = $sshKeys;
        }

        if (!empty($labels)) {
            $payload['labels'] = $labels;
        }

        return $this->request('/servers', 'POST', $payload);
    }

    public function deleteServer($id)
    {
        return $this->request("/servers/{$id}", 'DELETE');
    }

    // =========================================================
    // Server Actions
    // =========================================================
    public function powerOn($id)
    {
        return $this->request("/servers/{$id}/actions/poweron", 'POST');
    }

    public function powerOff($id)
    {
        return $this->request("/servers/{$id}/actions/poweroff", 'POST');
    }

    public function shutdown($id)
    {
        return $this->request("/servers/{$id}/actions/shutdown", 'POST');
    }

    public function reboot($id)
    {
        return $this->request("/servers/{$id}/actions/reboot", 'POST');
    }

    public function resetPassword($id)
    {
        return $this->request("/servers/{$id}/actions/reset_password", 'POST');
    }

    public function rebuild($id, $image)
    {
        $payload = ['image' => $image];
        return $this->request("/servers/{$id}/actions/rebuild", 'POST', $payload);
    }

    public function changeType($id, $serverType, $upgradeDisk = false)
    {
        $payload = [
            'server_type' => $serverType,
            'upgrade_disk' => (bool)$upgradeDisk
        ];
        return $this->request("/servers/{$id}/actions/change_type", 'POST', $payload);
    }

    public function getServerActions($id, $limit = 10)
    {
        $response = $this->request("/servers/{$id}/actions?sort=id:desc&per_page={$limit}");
        return isset($response['actions']) ? $response['actions'] : [];
    }

    // =========================================================
    // Snapshot Actions
    // =========================================================
    public function createSnapshot($serverId, $description = '')
    {
        $payload = ['type' => 'snapshot'];
        if (!empty($description)) {
            $payload['description'] = $description;
        }
        return $this->request("/servers/{$serverId}/actions/create_image", 'POST', $payload);
    }

    public function rebuildFromImage($serverId, $imageId)
    {
        $payload = ['image' => (int)$imageId];
        return $this->request("/servers/{$serverId}/actions/rebuild", 'POST', $payload);
    }

    // =========================================================
    // Backup Management
    // =========================================================
    public function enableBackups($serverId)
    {
        return $this->request("/servers/{$serverId}/actions/enable_backup", 'POST');
    }

    public function disableBackups($serverId)
    {
        return $this->request("/servers/{$serverId}/actions/disable_backup", 'POST');
    }

    // =========================================================
    // SSH Key Management
    // =========================================================
    public function getSshKeys()
    {
        $response = $this->request('/ssh_keys');
        return isset($response['ssh_keys']) ? $response['ssh_keys'] : [];
    }

    public function createSshKey($name, $publicKey)
    {
        $payload = [
            'name'       => $name,
            'public_key' => trim($publicKey),
        ];
        $response = $this->request('/ssh_keys', 'POST', $payload);
        return isset($response['ssh_key']) ? $response['ssh_key'] : null;
    }

    public function deleteSshKey($id)
    {
        return $this->request("/ssh_keys/{$id}", 'DELETE');
    }

    // =========================================================
    // Firewall Management
    // =========================================================
    public function getFirewalls()
    {
        $response = $this->request('/firewalls');
        return isset($response['firewalls']) ? $response['firewalls'] : [];
    }

    public function getFirewall($firewallId)
    {
        $response = $this->request("/firewalls/{$firewallId}");
        return isset($response['firewall']) ? $response['firewall'] : null;
    }

    public function applyFirewallToServer($firewallId, $serverId)
    {
        $payload = [
            'apply_to' => [
                [
                    'type'   => 'server',
                    'server' => ['id' => (int)$serverId]
                ]
            ]
        ];
        return $this->request("/firewalls/{$firewallId}/actions/apply_to_resources", 'POST', $payload);
    }

    public function removeFirewallFromServer($firewallId, $serverId)
    {
        $payload = [
            'remove_from' => [
                [
                    'type'   => 'server',
                    'server' => ['id' => (int)$serverId]
                ]
            ]
        ];
        return $this->request("/firewalls/{$firewallId}/actions/remove_from_resources", 'POST', $payload);
    }

    // =========================================================
    // Labels Management
    // =========================================================
    public function updateServerLabels($serverId, array $labels)
    {
        $response = $this->request("/servers/{$serverId}", 'PUT', ['labels' => $labels]);
        return isset($response['server']) ? $response['server'] : null;
    }

    // =========================================================
    // Rescue Mode
    // =========================================================
    public function enableRescue($id, $type = 'linux64', $sshKeys = [])
    {
        $payload = ['type' => $type];
        if (!empty($sshKeys)) {
            $payload['ssh_keys'] = $sshKeys;
        }
        return $this->request("/servers/{$id}/actions/enable_rescue", 'POST', $payload);
    }

    public function disableRescue($id)
    {
        return $this->request("/servers/{$id}/actions/disable_rescue", 'POST');
    }

    // =========================================================
    // Hostname & DNS Management
    // =========================================================
    public function renameServer($id, $newName)
    {
        $payload = ['name' => trim($newName)];
        // Use PUT as Hetzner Cloud API uses PUT to update server properties
        return $this->request("/servers/{$id}", 'PUT', $payload);
    }

    public function changeDnsPtr($id, $ip, $dnsPtr)
    {
        $payload = [
            'ip'      => trim($ip),
            'dns_ptr' => !empty($dnsPtr) ? trim($dnsPtr) : null
        ];
        return $this->request("/servers/{$id}/actions/change_dns_ptr", 'POST', $payload);
    }

    // =========================================================
    // ISO Management (Mount/Unmount)
    // =========================================================
    public function getISOs()
    {
        $allISOs = [];
        $page    = 1;
        $perPage = 50;

        do {
            $response = $this->request("/isos?type=public&per_page={$perPage}&page={$page}");
            $isos     = isset($response['isos']) ? $response['isos'] : [];
            $allISOs  = array_merge($allISOs, $isos);

            $nextPage = isset($response['meta']['pagination']['next_page'])
                ? (int)$response['meta']['pagination']['next_page']
                : null;

            $page = $nextPage ?: null;
        } while ($page !== null);

        return $allISOs;
    }

    public function attachISO($serverId, $isoIdOrName)
    {
        $payload = is_numeric($isoIdOrName)
            ? ['iso' => (int)$isoIdOrName]
            : ['iso' => $isoIdOrName];
        return $this->request("/servers/{$serverId}/actions/attach_iso", 'POST', $payload);
    }

    public function detachISO($serverId)
    {
        return $this->request("/servers/{$serverId}/actions/detach_iso", 'POST');
    }

    // =========================================================
    // Metrics
    // =========================================================
    public function getMetrics($serverId, $types = 'cpu,disk,network', $start = null, $end = null)
    {
        if (!$end) $end = gmdate('Y-m-d\TH:i:s\Z');
        if (!$start) $start = gmdate('Y-m-d\TH:i:s\Z', time() - 86400);
        $endpoint = "/servers/{$serverId}/metrics?type=" . urlencode($types) . "&start=" . urlencode($start) . "&end=" . urlencode($end);
        return $this->request($endpoint, 'GET');
    }
}
