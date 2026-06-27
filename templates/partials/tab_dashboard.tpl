    <!-- Tab 1: Dashboard Details -->
    <div id="hz-tab-dashboard" class="hz-tab-content active">
        <div class="hz-grid">
            
            <!-- Primary IP Address -->
            <div class="hz-card">
                <div class="hz-card-title">Primary IP Address</div>
                <div class="hz-card-value">
                    <span id="ip-ipv4">{$ipv4}</span>
                    <button class="hz-copy-btn" onclick="hzCloud.copyToClipboard('ip-ipv4')" title="Copy IP"><i class="fa fa-copy"></i></button>
                </div>
                <div class="hz-card-sub">IPv4 Address</div>
            </div>

            <!-- IPv6 Network -->
            <div class="hz-card">
                <div class="hz-card-title">IPv6 Network</div>
                <div class="hz-card-value" style="font-size: 0.95rem; line-height: 1.25; display: flex; align-items: center; justify-content: space-between; gap: 8px; overflow: hidden;">
                    <span id="ip-ipv6" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex-grow: 1; min-width: 0;" title="{$ipv6}">{$ipv6}</span>
                    <button class="hz-copy-btn" onclick="hzCloud.copyToClipboard('ip-ipv6')" title="Copy IPv6" style="flex-shrink: 0; margin-left: 0;"><i class="fa fa-copy"></i></button>
                </div>
                <div class="hz-card-sub">IPv6 Subnet Block</div>
            </div>

            <!-- Instance Specs -->
            <div class="hz-card">
                <div class="hz-card-title">Instance Specs</div>
                <div class="hz-card-value">{$cores} vCPU / {$memory} GB</div>
                <div class="hz-card-sub">{$disk} GB SSD Storage</div>
            </div>

            <!-- Operating System -->
            <div class="hz-card">
                <div class="hz-card-title">Operating System</div>
                <div class="hz-card-value" style="font-size: 1.05rem;">{$imageDescription}</div>
                <div class="hz-card-sub">Active OS Template</div>
            </div>

            <!-- Default Credentials -->
            <div class="hz-card">
                <div class="hz-card-title">Default Root Credentials</div>
                <div class="hz-card-value" style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                    <span id="vps-password-text" style="filter: blur(5px); cursor: pointer; user-select: none;" onclick="hzCloud.togglePasswordVisibility()">••••••••••••</span>
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <button class="hz-copy-btn" onclick="hzCloud.togglePasswordVisibility()" title="Reveal/Hide Password" style="margin-left: 0;"><i id="vps-password-eye" class="fa fa-eye"></i></button>
                        <button class="hz-copy-btn" onclick="hzCloud.copyPassword()" title="Copy Password" style="margin-left: 0;"><i class="fa fa-copy"></i></button>
                    </div>
                </div>
                <div class="hz-card-sub">SSH Username: <strong>root</strong></div>
            </div>

        </div>
        
        <!-- Quick Stats footer -->
        <div class="hz-footer-stats">
            <span>Server Datacenter Location: <strong>{$country}</strong></span>
            <span>Status: <strong>{$status|upper}</strong></span>
        </div>
    </div>
