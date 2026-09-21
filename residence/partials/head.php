<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AddToMar — Resident Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Roboto+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= residence_asset('css/style.css') ?>?v=product-preview-click-fix-3">
<link rel="stylesheet" href="<?= htmlspecialchars(function_exists('app_url') ? app_url('css/rx-crop-preview.css') : '../css/rx-crop-preview.css', ENT_QUOTES, 'UTF-8') ?>?v=rx-crop-contain-1">
<style>
.page[data-page="orders"].is-order-detail{overflow:auto!important;}
#orders-detail-view svg{width:16px!important;height:16px!important;max-width:16px!important;max-height:16px!important;display:block!important;flex:none!important;}
#orders-detail-view .od-receipt-mark svg{width:72px!important;height:56px!important;max-width:72px!important;max-height:56px!important;display:block!important;}
#orders-detail-view .od-receipt-note svg{width:16px!important;height:16px!important;max-width:16px!important;max-height:16px!important;}
#orders-detail-view .od-back{display:inline-flex!important;align-items:center!important;gap:6px!important;margin:0!important;padding:0 0 20px!important;border:0!important;background:transparent!important;color:var(--ink-soft)!important;font:600 13px Inter,sans-serif!important;line-height:1!important;cursor:pointer!important;width:auto!important;height:auto!important;box-shadow:none!important;}
#orders-detail-view .od-back svg{width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;}
#orders-detail-view .od-pickup-proof-thumb{width:52px!important;height:60px!important;max-width:52px!important;max-height:60px!important;flex:0 0 52px!important;overflow:hidden!important;border-radius:8px!important;}
#orders-detail-view .od-pickup-proof-thumb img{width:100%!important;height:100%!important;max-width:none!important;max-height:none!important;object-fit:cover!important;display:block!important;}
#orders-detail-view .od-node,#orders-detail-view .od-icon,#orders-detail-view .od-check,#orders-detail-view .od-badge{overflow:hidden;}
@media (max-width:980px){#orders-detail-view .od-layout{grid-template-columns:1fr!important;}}
</style>
