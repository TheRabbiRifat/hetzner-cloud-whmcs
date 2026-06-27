# Hetzner Cloud VPS WHMCS Provisioning Module (`hz_cloud`)

Automate the deployment and management of Hetzner Cloud virtual servers directly inside your WHMCS billing portal. This module features a fully whitelabeled, modern glassmorphism client control panel, support for dynamic config options, and interactive client actions like OS reinstallation, rDNS configuration, VNC console, firewalls, and ISO mounts.

---

## Features

### Client Area Dashboard (9-Tab Premium Panel)
1. **Dashboard Tab**: Displays system status, public IPv4 and IPv6 addresses, server specs (vCPU, RAM, SSD), OS image, automatic backup status, and a toggle to reveal/copy the root password.
2. **Operations Tab**: Let clients trigger Power On, Reboot, Graceful Shutdown, Force Power Off, Reset Password, and launch a secure HTML5 VNC Console.
3. **Usage Statistics Tab**: Real-time interactive CPU, Network (Traffic In/Out), and Disk Read/Write utilization charts powered by Chart.js and AJAX.
4. **Reinstall OS Tab**: Client-triggered operating system rebuild with verification prompting to prevent accidental data loss.
5. **Network & Hostname Tab**: Hostname renaming and reverse DNS (rDNS) PTR record management for both IPv4 and IPv6 blocks.
6. **Firewalls Tab**: View attached firewalls, remove them, load available account firewalls dynamically, and apply them.
7. **ISO Images Tab**: Mount public ISO distributions (e.g., Windows, Rocky Linux, Debian, etc.) and eject mounted ISO media.
8. **Rescue Mode Tab**: Boot into Hetzner's official Rescue System (Linux64 or FreeBSD64) with temporary root credentials and easily disable/reboot when finished.
9. **Activity Log Tab**: Real-time progress bars showing the status of recent operations performed on the virtual machine.

### Administrator Features
- **VNC Console**: Clean HTML5 `noVNC` popup screen operating directly through client-side WebSocket sessions (no extra server configuration or daemon dependencies required on your host).
- **Whitelabeled Client Interface**: No Hetzner logos, links, or internal names are displayed to clients. All backend communication is handled server-side.
- **Auto-created Fields**: Automatically creates and updates tracking fields like `Server ID` inside WHMCS.
- **Server Tagging/Labeling**: Automatically tags VMs with `whmcs_client_id`, `whmcs_service_id`, and `provisioned_by` at creation time for easy identification in your Hetzner Cloud console.
- **Flexible Overrides**: Supports overriding locations and server plans dynamically via standard WHMCS Configurable Options.

---

## Directory Structure

Ensure the module is installed in the correct path under your WHMCS directory:

```text
WHMCS_ROOT/
  └── modules/
        └── servers/
              └── hz_cloud/
                    ├── hz_cloud.php        # Core module entry point
                    ├── console.php         # Standalone HTML5 secure noVNC console
                    ├── hooks.php           # Secondary sidebar and navbar hook integrations
                    ├── whmcs.json          # WHMCS module configuration details
                    ├── logo.png            # Admin page module brand icon
                    ├── lib/
                    │    └── HetznerAPI.php # Hetzner Cloud REST API wrapper library
                    └── templates/
                         ├── error.tpl      # Standardized user-friendly error templates
                         ├── overview.tpl   # 9-tab dashboard template
                         └── css/
                              └── style.css # Premium design stylesheets (Glassmorphism design)
```

---

## Installation

1. Upload the `hz_cloud` folder to your WHMCS server inside the `/modules/servers/` directory.
2. Confirm permissions are set correctly so that WHMCS can read the PHP scripts and templates.

---

## Configuration

### Step 1: Add Hetzner Server in WHMCS
1. Log in to your WHMCS Admin Area.
2. Navigate to **Setup → Products/Services → Servers** (or **System Settings → Servers** on newer WHMCS versions).
3. Click **Add New Server**.
4. Configure the server details:
   - **Name**: `Hetzner Cloud` (or any display name)
   - **Hostname**: `api.hetzner.cloud`
   - **IP Address**: `1.1.1.1` (or any dummy IP, not used by module)
   - **Module**: Select **Hetzner Cloud VPS** (`hz_cloud`) from the dropdown menu.
   - **Password**: Enter your Hetzner Cloud **API Read/Write Token** in the **Password** field.
5. Click **Save Changes**.
6. Create a **Server Group** (e.g., "Hetzner Cloud"), assign the server to it, and save.

---

### Step 2: Configure WHMCS Custom Fields
WHMCS supports custom field friendly display names using the pipe (`|`) format. The first part (before the pipe) is the code key used internally by the module, while the second part is shown to customers.

Go to your Product's **Custom Fields** tab and define:

1. **OS Image** (Used for selecting the OS during ordering):
   - **Field Name**: `OS Image` or `OS Image|Operating System` (e.g., first part `OS Image` is used in code, `Operating System` is shown to the customer).
   - **Field Type**: `Text` or `Dropdown`
   - **Description**: `Select default operating system image.`
   - **Show on Order Form**: Checked
   - **Required Field**: Checked
   - **Dropdown Options**: Provide standard Hetzner slugs. You can copy the dynamic list from the **Available OS Images** info box in the **Module Settings** tab (see below).
   - *If left empty or missing, provisioning defaults to `ubuntu-24.04`.*

2. **Server ID** (Required for VM mapping):
   - **Field Name**: `Server ID` or `Server ID|VPS ID` (internal code name `Server ID` is used in the database/code, and `VPS ID` is shown to the customer). You can use any display name after the pipe character (`|`).
   - **Field Type**: `Text`
   - **Admin Only**: Checked (Clients should not see or edit this)
   - *Note: If this field is missing when provisioning starts, the module will attempt to automatically create `Server ID|VPS ID` for you.*

---

### Step 3: Configure WHMCS Configurable Options (Optional)
Create a new **Configurable Option Group** and link it to the Hetzner Cloud product to offer upgrades and customization.

1. **Backups (Daily Backups addon)**:
   - **Option Name**: `Backups` (case-insensitive)
   - **Option Type**: `Yes/No` or `Dropdown`
   - **Option Values**: To enable backups, the selected value must match `yes`, `Yes`, `Enable`, `enable`, or `1`. Selecting these will enable automatic daily backups (+20% base server price billed on Hetzner).
   - *Example values: `No|No Backups`, `Yes|Daily Backups`*

2. **Location Override**:
   - **Option Name**: `Location`
   - **Option Type**: `Dropdown`
   - **Option Values**: Use official Hetzner datacenter slugs.
     - `nbg1|Nuremberg (DE)`
     - `fsn1|Falkenstein (DE)`
     - `hel1|Helsinki (FI)`
     - `ash|Ashburn, VA (US)`
     - `hil|Hillsboro, OR (US)`
     - `sin|Singapore (SG)`

3. **Server Type Override (Hardware plans)**:
   - **Option Name**: `Server Type`
   - **Option Type**: `Dropdown`
   - **Option Values**: Use official Hetzner server type slugs.
     - `cx22|CX22 (2 vCPU / 4 GB RAM / 40 GB SSD)`
     - `cpx11|CPX11 (2 vCPU / 2 GB RAM / 40 GB SSD)`
     - `cpx21|CPX21 (3 vCPU / 4 GB RAM / 80 GB SSD)`
     - `cpx31|CPX31 (4 vCPU / 8 GB RAM / 160 GB SSD)`
     - `cpx41|CPX41 (8 vCPU / 16 GB RAM / 240 GB SSD)`
     - `cx32|CX32 (4 vCPU / 8 GB RAM / 80 GB SSD)`
     - `cx42|CX42 (8 vCPU / 16 GB RAM / 160 GB SSD)`
     - `cax11|CAX11 (2 vCPU / 4 GB RAM / 40 GB SSD - ARM)`
     - `cax21|CAX21 (4 vCPU / 8 GB RAM / 80 GB SSD - ARM)`

---

### Step 4: Configure the Product
1. Go to **Setup → Products/Services → Products/Services**.
2. Click **Create a New Product**.
3. Under the **Module Settings** tab:
   - **Module Name**: Select **Hetzner Cloud VPS** (`hz_cloud`).
   - **Server Group**: Select the Server Group you created in Step 1.
   - **Location**: Select the default deployment datacenter location (used if not overridden by configurable options).
   - **Server Type**: Select the default hardware configuration plan (used if not overridden by configurable options). Below this dropdown you will see an **Available OS Images** helper — a read-only textarea with all available OS slugs fetched from the API (deduplicated, paginated). Click it to select-all, then copy-paste into your **OS Image** custom field's dropdown options.
4. Save the product configuration.

---

## Troubleshooting

- **Check API Status**:
  Ensure your server settings in WHMCS point to `hz_cloud` and that the password field contains a valid Hetzner Cloud API Read/Write token. If you get connection errors, verify that your WHMCS host can reach `https://api.hetzner.cloud/v1/`.
  
- **Review Module Logs**:
  If provisioning or a user action fails, check **Utilities → Logs → Module Log** (in newer WHMCS: **Configuration → System Logs → Module Log**). Look for entries labeled `hz_cloud` to inspect the raw request and response data sent to and received from the Hetzner API.

- **Check Image Slugs**:
  If reinstallation or VM creation fails with an image error, verify that the image name provided in the Custom Field matches a valid Hetzner image slug (e.g. `ubuntu-24.04` and NOT `Ubuntu 24.04 LTS`).

---

## License

This WHMCS server module is released under the [MIT License](https://opensource.org/licenses/MIT).
#   h e t z n e r - c l o u d - w h m c s  
 