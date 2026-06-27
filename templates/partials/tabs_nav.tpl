    <!-- Tabs Menu -->
    <div class="hz-tabs">
        <div class="hz-tab-link active" data-tab="dashboard" onclick="hzCloud.switchTab(this, 'dashboard')">
            Dashboard
        </div>
        <div class="hz-tab-link" data-tab="operations" onclick="hzCloud.switchTab(this, 'operations')">
            Operations
        </div>
        {if $allowCharts}
        <div class="hz-tab-link" data-tab="metrics" onclick="hzCloud.switchTab(this, 'metrics'); hzCloud.loadMetrics();">
            Usage Statistics
        </div>
        {/if}
        {if $allowReinstall}
        <div class="hz-tab-link" data-tab="reinstall" onclick="hzCloud.switchTab(this, 'reinstall')">
            Reinstall OS
        </div>
        {/if}
        {if $showPtr || !$hideHostname}
        <div class="hz-tab-link" data-tab="network_settings" onclick="hzCloud.switchTab(this, 'network_settings')">
            Network & Hostname
        </div>
        {/if}
        {if $backupsPurchased}
        <div class="hz-tab-link" data-tab="backups_tab" onclick="hzCloud.switchTab(this, 'backups_tab')">
            Backups
        </div>
        {/if}
        {if $allowIso}
        <div class="hz-tab-link" data-tab="isos" onclick="hzCloud.switchTab(this, 'isos')">
            ISO Images
        </div>
        {/if}
        <div class="hz-tab-link" data-tab="rescue" onclick="hzCloud.switchTab(this, 'rescue')">
            Rescue Mode
        </div>
    </div>
