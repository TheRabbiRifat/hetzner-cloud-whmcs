<!-- Cloud VPS Premium Dashboard -->
<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/hz_cloud/assets/css/style.css?v={$smarty.now}">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="hz-container">
    {include file="./partials/header.tpl"}
    {include file="./partials/alerts.tpl"}
    {include file="./partials/tabs_nav.tpl"}
    {include file="./partials/tab_dashboard.tpl"}
    {include file="./partials/tab_operations.tpl"}
    {if $allowCharts}
        {include file="./partials/tab_metrics.tpl"}
    {/if}
    {if $allowReinstall}
        {include file="./partials/tab_reinstall.tpl"}
    {/if}
    {if $showPtr || !$hideHostname}
        {include file="./partials/tab_network.tpl"}
    {/if}
    {if $backupsPurchased}
        {include file="./partials/tab_backups.tpl"}
    {/if}
    {if $allowIso}
        {include file="./partials/tab_isos.tpl"}
    {/if}
    {include file="./partials/tab_rescue.tpl"}
</div>

{include file="./partials/modal.tpl"}
{include file="./partials/toast.tpl"}
{include file="./partials/scripts.tpl"}
