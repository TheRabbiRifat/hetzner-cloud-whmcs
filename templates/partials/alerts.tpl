    <!-- Live operations message notices -->
    {if $actionMessage}
        <div class="hz-alert hz-alert-success">
            <i class="fa fa-check-circle"></i>
            <div>{$actionMessage}</div>
        </div>
    {/if}

    {if $actionError}
        <div class="hz-alert hz-alert-danger">
            <i class="fa fa-exclamation-circle"></i>
            <div><strong>Error:</strong> {$actionError}</div>
        </div>
    {/if}

    {if $newPassword}
        <div class="hz-alert hz-alert-warning" style="border-left: 4px solid var(--hz-warning);">
            <i class="fa fa-key"></i>
            <div>
                <strong>Important:</strong> A new root password has been generated for your server:<br>
                <span style="font-family: monospace; font-size: 1.1em; font-weight: bold; background: rgba(0,0,0,0.05); padding: 4px 10px; border-radius: 6px; display: inline-block; margin-top: 8px; color: var(--hz-text-primary); border: 1px solid var(--hz-border);">{$newPassword}</span><br>
                <span style="display: inline-block; margin-top: 6px; font-size: 0.85rem;">Please copy this password now, as it will not be displayed again.</span>
            </div>
        </div>
    {/if}

    {if $isPending}
        <div class="hz-alert hz-alert-warning">
            <div class="hz-spinner dark" style="margin-right: 8px;"></div>
            <div>An operation is currently in progress on this server. This dashboard will update automatically...</div>
        </div>
    {/if}

    {if $isoAttached}
        <div class="hz-alert hz-alert-warning" style="border-left: 4px solid var(--hz-warning); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; width: 100%; box-sizing: border-box;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-info-circle" style="color: var(--hz-warning); font-size: 1.25rem;"></i>
                <div><strong>Mounted ISO:</strong> <code>{$mountedIso.description}</code></div>
            </div>
            <form method="post" action="clientarea.php?action=productdetails" style="margin: 0;">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="detach_iso" />
                <button type="submit" class="hz-submit-btn" style="background-color: var(--hz-danger); box-shadow: none; padding: 6px 14px; font-size: 0.8rem; margin: 0; min-height: auto; width: auto;" {if $isPending}disabled{/if}>
                    <i class="fa fa-eject"></i> Eject ISO
                </button>
            </form>
        </div>
    {/if}

    {if $rescueEnabled}
        <div class="hz-alert hz-alert-danger" style="border-left: 4px solid var(--hz-danger); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; width: 100%; box-sizing: border-box;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-life-ring" style="color: var(--hz-danger); font-size: 1.25rem;"></i>
                <div><strong>Rescue Mode is Active:</strong> The server has booted into the rescue environment.</div>
            </div>
            <form method="post" action="clientarea.php?action=productdetails" style="margin: 0;">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="disable_rescue" />
                <button type="submit" class="hz-submit-btn" style="background-color: var(--hz-danger); box-shadow: none; padding: 6px 14px; font-size: 0.8rem; margin: 0; min-height: auto; width: auto;" {if $isPending}disabled{/if}>
                    <i class="fa fa-power-off"></i> Disable Rescue
                </button>
            </form>
        </div>
    {/if}
