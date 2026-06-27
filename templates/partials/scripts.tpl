<!-- Client area JavaScript initialization and external loader -->
<script>
window.hzCloudConfig = {
    webRoot: '{$WEB_ROOT}',
    serviceId: '{$serviceid}',
    originalPassword: "{$password|escape:'javascript'}"
};
</script>
<script src="{$WEB_ROOT}/modules/servers/hz_cloud/assets/js/client.js?v={$smarty.now}"></script>
