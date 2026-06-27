<?php
/**
 * Standalone Secure VNC Console Viewer for Cloud VPS
 *
 * Located at: /modules/servers/hz_cloud/console.php
 */

define("CLIENTAREA", true);

// Boot WHMCS environment
$initPath = __DIR__ . '/../../../init.php';
if (!file_exists($initPath)) {
    die("Error: WHMCS environment boot script (init.php) not found.");
}
require_once $initPath;

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

// Include the Hetzner API wrapper
require_once __DIR__ . '/lib/HetznerAPI.php';

$ca = new ClientArea();
$ca->initPage();

// 1. Enforce Client Login
if (!$ca->isLoggedIn()) {
    die("Access Denied: Please log in to your WHMCS client area first.");
}
$userId = $ca->getUserID();

// 2. Validate Service ID
$serviceId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if (!$serviceId) {
    die("Invalid Request: Service ID parameter is required.");
}

try {
    // 3. Verify ownership of the service
    $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->where('userid', $userId)
        ->first();

    if (!$service) {
        die("Access Denied: You do not have permission to access this virtual server console.");
    }

    // 4. Retrieve Server API Token
    $serverId = $service->server;
    if (!$serverId) {
        // Fallback to first active server of type hz_cloud
        $serverId = Capsule::table('tblservers')
            ->where('type', 'hz_cloud')
            ->where('active', 1)
            ->value('id');
    }

    $server = Capsule::table('tblservers')->where('id', $serverId)->first();
    if (!$server || empty($server->password)) {
        die("Error: Server connection details are missing in WHMCS server settings.");
    }
    $apiToken = decrypt($server->password);

    // 5. Retrieve Hetzner Server ID reference from custom fields
    $customField = Capsule::table('tblcustomfields')
        ->where('relid', $service->packageid)
        ->where(function($query) {
            $query->where('fieldname', 'Server ID')
                  ->orWhere('fieldname', 'like', 'Server ID|%');
        })
        ->where('type', 'product')
        ->first();

    if (!$customField) {
        die("Error: Hetzner server connection reference field ('Server ID') is missing.");
    }

    $hetznerServerId = Capsule::table('tblcustomfieldsvalues')
        ->where('relid', $serviceId)
        ->where('fieldid', $customField->id)
        ->value('value');

    if (empty($hetznerServerId)) {
        die("Error: This virtual server has not been provisioned on Hetzner Cloud yet.");
    }

    // 6. Request VNC WebSocket Credentials from Hetzner API
    $api = new HetznerAPI($apiToken);
    $result = $api->request("/servers/{$hetznerServerId}/actions/request_console", 'POST');

    // Hetzner returns: { "action": {...}, "wss_url": "wss://...", "password": "..." }
    // Both wss_url and password are top-level in the response (not nested under action).
    $wssUrl  = $result['wss_url']  ?? '';
    $password = $result['password'] ?? '';

    if (empty($wssUrl)) {
        throw new Exception("Unable to retrieve remote console WebSocket path from Hetzner API.");
    }

    // 7. Render VNC Interface HTML page
    $safeHost = htmlspecialchars($service->domain, ENT_QUOTES, 'UTF-8');
    $jsonWss = json_encode($wssUrl);
    $jsonPass = json_encode($password);

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Console - {$safeHost}</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: #111111;
            color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        #top-bar {
            background-color: #1e1e1e;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #2d2d2d;
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
            z-index: 10;
        }
        #title {
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.02em;
        }
        #status-area {
            font-size: 0.85rem;
            color: #a0aec0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #718096;
            transition: background-color 0.3s;
        }
        .status-dot.connected {
            background-color: #48bb78;
            box-shadow: 0 0 8px #48bb78;
        }
        .status-dot.connecting {
            background-color: #ecc94b;
            box-shadow: 0 0 8px #ecc94b;
        }
        .status-dot.disconnected {
            background-color: #f56565;
            box-shadow: 0 0 8px #f56565;
        }
        #controls {
            display: flex;
            gap: 10px;
        }
        .btn {
            background-color: #2d3748;
            border: 1px solid #4a5568;
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn:hover {
            background-color: #4a5568;
            border-color: #718096;
        }
        .btn-danger {
            background-color: #9b2c2c;
            border-color: #c53030;
        }
        .btn-danger:hover {
            background-color: #c53030;
            border-color: #e53e3e;
        }
        #vnc-container {
            flex-grow: 1;
            width: 100%;
            height: calc(100% - 46px);
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #050505;
        }
        #vnc-screen {
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: #000000;
        }
    </style>
</head>
<body>
    <div id="top-bar">
        <div id="title">Cloud VPS Console &bull; {$safeHost}</div>
        <div id="status-area">
            <span id="status-dot" class="status-dot connecting"></span>
            <span id="status-text">Connecting...</span>
        </div>
        <div id="controls">
            <button class="btn" id="reconnect-btn" style="display: none; background-color: #3182ce; border-color: #2b6cb0;">Reconnect</button>
            <button class="btn" id="cad-btn">Send Ctrl+Alt+Del</button>
            <button class="btn btn-danger" onclick="window.close()">Close Console</button>
        </div>
    </div>
    
    <div id="vnc-container">
        <div id="vnc-screen"></div>
    </div>

    <script type="module">
        import RFB from 'https://cdn.jsdelivr.net/npm/@novnc/novnc@1.7.0/+esm';

        const screen = document.getElementById('vnc-screen');
        const statusDot = document.getElementById('status-dot');
        const statusText = document.getElementById('status-text');
        const cadBtn = document.getElementById('cad-btn');
        const reconnectBtn = document.getElementById('reconnect-btn');

        const wssUrl = {$jsonWss};
        const password = {$jsonPass};

        let rfb;
        let reconnectTimeout = null;

        function connect() {
            // Cancel any pending auto-reconnect
            if (reconnectTimeout) {
                clearTimeout(reconnectTimeout);
                reconnectTimeout = null;
            }
            
            // Clean up existing instance if any
            if (rfb) {
                try {
                    rfb.disconnect();
                } catch(e) {}
                rfb = null;
            }
            
            screen.innerHTML = '';
            statusDot.className = "status-dot connecting";
            statusText.innerText = "Connecting...";
            reconnectBtn.style.display = 'none';

            try {
                rfb = new RFB(screen, wssUrl, {
                    credentials: { password: password }
                });

                rfb.scaleViewport = true;
                rfb.resizeSession = true;

                rfb.addEventListener("connect", () => {
                    statusDot.className = "status-dot connected";
                    statusText.innerText = "Connected";
                    reconnectBtn.style.display = 'none';
                });

                rfb.addEventListener("disconnect", (e) => {
                    statusDot.className = "status-dot disconnected";
                    statusText.innerText = "Disconnected";
                    reconnectBtn.style.display = 'inline-block';
                    
                    // Auto-reconnect after 3 seconds
                    reconnectTimeout = setTimeout(() => {
                        connect();
                    }, 3000);
                });

            } catch (exc) {
                statusDot.className = "status-dot disconnected";
                statusText.innerText = "Connection Failed";
                reconnectBtn.style.display = 'inline-block';
            }
        }

        // Event listeners
        cadBtn.addEventListener("click", () => {
            if (rfb) {
                rfb.sendCtrlAltDel();
            }
        });

        reconnectBtn.addEventListener("click", () => {
            connect();
        });

        // Initial connection
        connect();
    </script>
</body>
</html>
HTML;

} catch (\Exception $e) {
    die("VNC Console Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
