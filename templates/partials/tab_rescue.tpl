    <!-- Tab 8: Rescue Mode -->
    <div id="hz-tab-rescue" class="hz-tab-content">
        <h4 style="margin-top: 0; margin-bottom: 8px; font-weight: 700; color: var(--hz-text-primary); letter-spacing: -0.01em;">Server Rescue Environment</h4>
        <p style="color: var(--hz-text-muted); font-size: 0.9rem; margin-bottom: 24px;">Boot your virtual server into a temporary RAM disk system for troubleshooting.</p>
        
        {if $rescueEnabled}
            <div class="hz-alert hz-alert-warning" style="margin-bottom: 24px;">
                <i class="fa fa-life-ring"></i>
                <div>
                    <strong>Rescue Mode is Enabled:</strong><br>
                    The server has booted into the rescue system.
                </div>
            </div>

            <p style="color: var(--hz-text-secondary); font-size: 0.95rem; line-height: 1.6; margin-bottom: 24px;">
                Your server is currently running in the RAM-disk rescue environment. You can connect using a console or SSH to perform file system repairs, partition edits, or manual configuration adjustments.
            </p>

            <form method="post" action="clientarea.php?action=productdetails">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="disable_rescue" />
                <button type="submit" class="hz-submit-btn" style="background-color: var(--hz-danger);">
                    <i class="fa fa-power-off"></i> Disable Rescue Mode & Reboot
                </button>
            </form>
        {else}
            <div class="hz-alert hz-alert-success" style="margin-bottom: 24px; background-color: var(--hz-primary-bg); color: var(--hz-primary); border-color: rgba(79, 70, 229, 0.2); border-left: 4px solid var(--hz-primary);">
                <i class="fa fa-info-circle"></i>
                <div>
                    <strong>About Rescue System:</strong><br>
                    Rescue System is a Debian-based RAM disk environment. It allows console/SSH access to fix problems even if your main OS fails to load.
                </div>
            </div>

            <p style="color: var(--hz-text-muted); font-size: 0.9rem; line-height: 1.6; margin-bottom: 24px;">
                Enabling rescue mode configures the server's next boot to load the rescue system, and automatically reboots the instance immediately.
            </p>

            <form method="post" action="clientarea.php?action=productdetails">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="enable_rescue" />

                <div class="hz-form-group">
                    <label class="hz-form-label" for="rescue_type">Select Rescue Environment Type</label>
                    <select name="rescue_type" id="rescue_type" class="hz-form-select" {if $isPending}disabled{/if}>
                        <option value="linux64">Linux 64-bit (Debian) - Default</option>
                        <option value="freebsd64">FreeBSD 64-bit</option>
                    </select>
                </div>

                <button type="submit" class="hz-submit-btn" {if $isPending}disabled{/if}>
                    <i class="fa fa-life-ring"></i> Enable Rescue & Reboot
                </button>
            </form>
        {/if}
    </div>
