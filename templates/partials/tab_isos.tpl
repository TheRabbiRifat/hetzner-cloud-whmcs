    <div id="hz-tab-isos" class="hz-tab-content">
        <h4 style="margin-top: 0; margin-bottom: 8px; font-weight: 700; color: var(--hz-text-primary); letter-spacing: -0.01em;">ISO Image Mounter (Virtual CD-ROM)</h4>
        <p style="color: var(--hz-text-muted); font-size: 0.9rem; margin-bottom: 24px;">Mount installers, live operating systems, or rescue tools as a virtual CD-ROM drive.</p>

        {if $isoAttached}
            <div class="hz-alert hz-alert-warning" style="margin-bottom: 24px; border-left: 4px solid var(--hz-warning); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; width: 100%; box-sizing: border-box;">
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

        <div style="background: var(--hz-bg-card); border: 1px solid var(--hz-border); padding: 24px; border-radius: var(--hz-radius); box-shadow: var(--hz-shadow);">
            <h5 style="margin-top: 0; margin-bottom: 15px; font-weight: 600; color: var(--hz-text-secondary); display: flex; align-items: center; gap: 8px; font-size: 1rem;"><i class="fa fa-plus-circle" style="color: var(--hz-primary)"></i> Mount {if $isoAttached}a Different{else}an{/if} ISO Image</h5>
            
            <form method="post" action="clientarea.php?action=productdetails">
                <input type="hidden" name="id" value="{$serviceid}" />
                <input type="hidden" name="customAction" value="attach_iso" />

                <div class="hz-form-group">
                    <label class="hz-form-label" for="iso_name">Select Installation ISO</label>
                    <select name="iso_name" id="iso_name" class="hz-form-select" required {if $isPending}disabled{/if}>
                        <option value="">-- Choose ISO --</option>
                        {foreach from=$isoList item=iso}
                            <option value="{$iso.name}">{$iso.description}{if $iso.deprecated} (Deprecated){/if}</option>
                        {/foreach}
                    </select>
                </div>

                <button type="submit" class="hz-submit-btn" {if $isPending}disabled{/if}>
                    <i class="fa fa-hdd-o"></i> Mount ISO
                </button>
            </form>
        </div>
    </div>
