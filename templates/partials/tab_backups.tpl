    <div id="hz-tab-backups_tab" class="hz-tab-content">
        <h4 style="margin-top: 0; margin-bottom: 8px; font-weight: 700; color: var(--hz-text-primary); letter-spacing: -0.01em;">Server Backups</h4>
        <p style="color: var(--hz-text-muted); font-size: 0.9rem; margin-bottom: 24px;">Manage automatic daily backups and view available backup images.</p>

        <!-- Automatic Backup Control -->
        <div style="background: var(--hz-bg-card); border: 1px solid var(--hz-border); padding: 24px; border-radius: var(--hz-radius); margin-bottom: 24px; box-shadow: var(--hz-shadow);">
            <h5 style="margin-top: 0; margin-bottom: 12px; font-weight: 600; color: var(--hz-text-secondary); display: flex; align-items: center; gap: 8px; font-size: 1rem;"><i class="fa fa-cog" style="color: var(--hz-primary)"></i> Automatic Daily Backups</h5>
            <p style="color: var(--hz-text-muted); font-size: 0.85rem; line-height: 1.5; margin-bottom: 20px;">
                Automatic backups are taken daily and stored securely.
            </p>
            <div style="display: flex; align-items: center; justify-content: space-between; max-width: 450px; background: var(--hz-bg-app); padding: 12px 20px; border-radius: var(--hz-radius); border: 1px solid var(--hz-border);">
                <span style="font-weight: 600; color: var(--hz-text-secondary);">Status: <span style="color: {if $backupsEnabledBool}var(--hz-success){else}var(--hz-text-muted){/if}">{$backupsEnabled}</span></span>
                <form method="post" action="clientarea.php?action=productdetails" style="margin:0;">
                    <input type="hidden" name="id" value="{$serviceid}" />
                    {if $backupsEnabledBool}
                        <input type="hidden" name="customAction" value="disable_backups" />
                        <button type="submit" class="hz-submit-btn" style="background-color: var(--hz-danger); box-shadow: none; padding: 8px 16px; font-size: 0.85rem;" {if $isPending}disabled{/if}>Disable</button>
                    {else}
                        <input type="hidden" name="customAction" value="enable_backups" />
                        <button type="submit" class="hz-submit-btn" style="background-color: var(--hz-success); box-shadow: none; padding: 8px 16px; font-size: 0.85rem;" {if $isPending}disabled{/if}>Enable</button>
                    {/if}
                </form>
            </div>
        </div>

        <!-- Backup Images List -->
        <div style="background: var(--hz-bg-card); border: 1px solid var(--hz-border); padding: 24px; border-radius: var(--hz-radius); box-shadow: var(--hz-shadow);">
            <h5 style="margin-top: 0; margin-bottom: 12px; font-weight: 600; color: var(--hz-text-secondary); display: flex; align-items: center; gap: 8px; font-size: 1rem;"><i class="fa fa-hdd-o" style="color: var(--hz-primary)"></i> Available Backup Images</h5>
            
            <div class="hz-table-container">
                <table class="hz-table">
                    <thead>
                        <tr>
                            <th>Image ID</th>
                            <th>Description</th>
                            <th>OS / Type</th>
                            <th>Size</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$backupImages item=img}
                            <tr>
                                <td><strong>#{$img.id}</strong></td>
                                <td>{$img.description}</td>
                                <td><span class="hz-badge success" style="background-color: var(--hz-primary-bg); color: var(--hz-primary); text-transform: none;">{$img.os_name}</span></td>
                                <td>{if $img.image_size}{$img.image_size|number_format:2} GB{else}N/A{/if}</td>
                                <td>{$img.created|date_format:"%Y-%m-%d %H:%M:%S"}</td>
                            </tr>
                        {foreachelse}
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--hz-text-light); padding: 30px; font-weight: 500;">No backup images found. Automatic daily backups typically run overnight.</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
