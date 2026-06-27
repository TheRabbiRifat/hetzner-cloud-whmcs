    <!-- Tab 2: Operations Panel -->
    <div id="hz-tab-operations" class="hz-tab-content">
        <h4 style="margin-top: 0; margin-bottom: 8px; font-weight: 700; color: var(--hz-text-primary); letter-spacing: -0.01em;">Power Management Actions</h4>
        <p style="color: var(--hz-text-muted); font-size: 0.9rem; margin-bottom: 24px;">Control the power status and access mechanisms of your cloud instance.</p>

        <div class="hz-actions-grid">
            
            <!-- Power On -->
            <form method="post" action="clientarea.php?action=productdetails" class="hz-action-btn-form">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="poweron" />
                <button type="submit" class="hz-action-btn boot" title="Start virtual server" {if $status eq 'running' || $isPending}disabled{/if}>
                    <i class="fa fa-play-circle"></i>
                    <span>Power On</span>
                </button>
            </form>

            <!-- Reboot -->
            <form method="post" action="clientarea.php?action=productdetails" class="hz-action-btn-form">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="reboot" />
                <button type="submit" class="hz-action-btn reboot" title="Restart operating system" {if $status neq 'running' || $isPending}disabled{/if}>
                    <i class="fa fa-sync"></i>
                    <span>Reboot</span>
                </button>
            </form>

            <!-- Graceful Shutdown -->
            <form method="post" action="clientarea.php?action=productdetails" class="hz-action-btn-form">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="shutdown" />
                <button type="submit" class="hz-action-btn shutdown-grace" title="Gracefully shut down OS" {if $status eq 'off' || $isPending}disabled{/if}>
                    <i class="fa fa-power-off"></i>
                    <span>Shutdown (OS)</span>
                </button>
            </form>

            <!-- Power Off -->
            <form method="post" action="clientarea.php?action=productdetails" class="hz-action-btn-form">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="poweroff" />
                <button type="submit" class="hz-action-btn shutdown" title="WARNING: Forcefully powering off can cause data corruption. Cut power immediately." {if $status eq 'off' || $isPending}disabled{/if}>
                    <i class="fa fa-bolt"></i>
                    <span>Power Off (Force)</span>
                </button>
            </form>

            <!-- Reset Password -->
            {if $allowPasswordReset}
            <form method="post" action="clientarea.php?action=productdetails" class="hz-action-btn-form">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="resetpassword" />
                <button type="submit" class="hz-action-btn reset" title="Generate new root credentials" {if $isPending}disabled{/if}>
                    <i class="fa fa-key"></i>
                    <span>Reset Password</span>
                </button>
            </form>
            {/if}

            <!-- Open VNC -->
            {if $allowVnc}
            <button class="hz-action-btn vnc" onclick="hzCloud.openConsolePopup()" title="HTML5 remote console access" {if $isPending}disabled{/if}>
                <i class="fa fa-terminal"></i>
                <span>VNC Console</span>
            </button>
            {/if}

        </div>
    </div>
