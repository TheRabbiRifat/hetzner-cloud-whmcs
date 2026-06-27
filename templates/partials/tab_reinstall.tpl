    <div id="hz-tab-reinstall" class="hz-tab-content">
        <h4 style="margin-top: 0; margin-bottom: 8px; font-weight: 700; color: var(--hz-text-primary); letter-spacing: -0.01em;">Reinstall Operating System</h4>
        <p style="color: var(--hz-text-muted); font-size: 0.9rem; margin-bottom: 20px;">Deploy a clean operating system template on your VPS.</p>
        
        <div class="hz-alert hz-alert-danger" style="margin-bottom: 24px;">
            <i class="fa fa-warning"></i>
            <div>
                <strong>CRITICAL WARNING:</strong> Reinstalling your operating system will wipe all data currently stored on your server disk. This operation is <strong>permanent and irreversible</strong>.
            </div>
        </div>

        <form method="post" action="clientarea.php?action=productdetails">
            <input type="hidden" name="id" value="{$serviceid}" />
            <input type="hidden" name="customAction" value="rebuild" />

            <div class="hz-form-group">
                <label class="hz-form-label" for="rebuild_image">Select New Operating System</label>
                <select name="rebuild_image" id="rebuild_image" class="hz-form-select" {if $isPending}disabled{/if}>
                    <option value="">-- Select OS Image --</option>
                    {foreach from=$osImages item=os}
                        <option value="{$os.name}">{$os.description}</option>
                    {/foreach}
                </select>
            </div>

            <div class="hz-form-group" style="margin-top: 15px; margin-bottom: 25px;">
                <label style="font-weight: 500; cursor: pointer; display: flex; align-items: flex-start; gap: 10px; color: var(--hz-text-secondary); font-size: 0.9rem; line-height: 1.4;">
                    <input type="checkbox" id="confirm_wipe" style="margin-top: 3px;" {if $isPending}disabled{/if}>
                    <span>I confirm that I understand this will destroy all data currently stored on this virtual server and install a fresh copy of the selected OS.</span>
                </label>
            </div>

            <button type="submit" id="rebuild_submit_btn" class="hz-submit-btn" disabled>
                <i class="fa fa-warning"></i> Start OS Reinstallation
            </button>
        </form>
    </div>
