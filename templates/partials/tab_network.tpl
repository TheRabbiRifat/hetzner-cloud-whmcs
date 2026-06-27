    <div id="hz-tab-network_settings" class="hz-tab-content">
        <h4 style="margin-top: 0; margin-bottom: 8px; font-weight: 700; color: var(--hz-text-primary); letter-spacing: -0.01em;">Network & Hostname Configuration</h4>
        <p style="color: var(--hz-text-muted); font-size: 0.9rem; margin-bottom: 24px;">Manage your VM hostname and reverse DNS (rDNS) PTR records.</p>

        <!-- Hostname Configuration Form -->
        {if !$hideHostname}
        <div style="background: var(--hz-bg-card); border: 1px solid var(--hz-border); padding: 24px; border-radius: var(--hz-radius); margin-bottom: 24px; box-shadow: var(--hz-shadow);">
            <h5 style="margin-top: 0; margin-bottom: 12px; font-weight: 600; color: var(--hz-text-secondary); display: flex; align-items: center; gap: 8px; font-size: 1rem;"><i class="fa fa-tag" style="color: var(--hz-primary)"></i> Change Server Hostname</h5>
            <p style="color: var(--hz-text-muted); font-size: 0.85rem; line-height: 1.5; margin-bottom: 20px;">Update the server hostname in the cloud portal (RFC 1123 format: letters, numbers, dots, and hyphens).</p>
            
            <form method="post" action="clientarea.php?action=productdetails">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="update_hostname" />

                <div class="hz-form-group">
                    <label class="hz-form-label" for="new_hostname">New Hostname</label>
                    <input type="text" name="new_hostname" id="new_hostname" class="hz-form-input" value="{$vpsName}" required {if $isPending}disabled{/if} />
                </div>

                <button type="submit" class="hz-submit-btn" {if $isPending}disabled{/if}>
                    Save Hostname
                </button>
            </form>
        </div>
        {/if}

        <!-- Reverse DNS (rDNS) PTR Configuration Form -->
        {if $showPtr}
        <div style="background: var(--hz-bg-card); border: 1px solid var(--hz-border); padding: 24px; border-radius: var(--hz-radius); margin-bottom: 24px; box-shadow: var(--hz-shadow);">
            <h5 style="margin-top: 0; margin-bottom: 12px; font-weight: 600; color: var(--hz-text-secondary); display: flex; align-items: center; gap: 8px; font-size: 1rem;"><i class="fa fa-exchange" style="color: var(--hz-primary)"></i> Change Reverse DNS (PTR) - IPv4</h5>
            <p style="color: var(--hz-text-muted); font-size: 0.85rem; line-height: 1.5; margin-bottom: 20px;">Set the PTR record for your primary IPv4 address. Leave blank to reset to default.</p>
            
            <form method="post" action="clientarea.php?action=productdetails">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="update_rdns" />
                <input type="hidden" name="ip_address" value="{$ipv4}" />

                <div class="hz-form-group">
                    <label class="hz-form-label">Primary IPv4 Address</label>
                    <span style="font-family: monospace; font-size: 1rem; background: var(--hz-bg-app); padding: 8px 16px; border-radius: var(--hz-radius); display: inline-block; color: var(--hz-text-secondary); border: 1px solid var(--hz-border); font-weight: 600;">{$ipv4}</span>
                </div>

                <div class="hz-form-group">
                    <label class="hz-form-label" for="rdns_value">Reverse DNS (PTR Value)</label>
                    <input type="text" name="rdns_value" id="rdns_value" class="hz-form-input" value="{$rdnsValue}" placeholder="e.g. mail.yourdomain.com" {if $isPending}disabled{/if} />
                </div>

                <button type="submit" class="hz-submit-btn" {if $isPending}disabled{/if}>
                    Save Reverse DNS
                </button>
            </form>
        </div>

        <!-- IPv6 Reverse DNS (rDNS) PTR Configuration Form -->
        <div style="background: var(--hz-bg-card); border: 1px solid var(--hz-border); padding: 24px; border-radius: var(--hz-radius); box-shadow: var(--hz-shadow);">
            <h5 style="margin-top: 0; margin-bottom: 12px; font-weight: 600; color: var(--hz-text-secondary); display: flex; align-items: center; gap: 8px; font-size: 1rem;"><i class="fa fa-exchange" style="color: var(--hz-primary)"></i> Change Reverse DNS (PTR) - IPv6</h5>
            <p style="color: var(--hz-text-muted); font-size: 0.85rem; line-height: 1.5; margin-bottom: 20px;">Set the PTR record for the first IPv6 address inside your subnet (e.g. your_subnet::1). Leave blank to reset to default.</p>
            
            <form method="post" action="clientarea.php?action=productdetails">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="update_rdns_ipv6" />
                <input type="hidden" name="ipv6_address" value="{$ipv6}" />

                <div class="hz-form-group">
                    <label class="hz-form-label">IPv6 Address Subnet Block</label>
                    <span style="font-family: monospace; font-size: 1rem; background: var(--hz-bg-app); padding: 8px 16px; border-radius: var(--hz-radius); display: inline-block; color: var(--hz-text-secondary); border: 1px solid var(--hz-border); font-weight: 600; width: 100%; max-width: 450px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{$ipv6}</span>
                </div>

                <div class="hz-form-group">
                    <label class="hz-form-label" for="rdns_ipv6_value">Reverse DNS (PTR Value)</label>
                    <input type="text" name="rdns_ipv6_value" id="rdns_ipv6_value" class="hz-form-input" value="{$rdnsIpv6}" placeholder="e.g. mail6.yourdomain.com" {if $isPending}disabled{/if} />
                </div>

                <button type="submit" class="hz-submit-btn" {if $isPending}disabled{/if}>
                    Save IPv6 Reverse DNS
                </button>
            </form>
        </div>
        {/if}
    </div>
