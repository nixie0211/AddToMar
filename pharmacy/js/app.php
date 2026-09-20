<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
?>

function updateTopbarHead(name){
  document.querySelectorAll('[data-topbar-head]').forEach(function(head){
    head.hidden = head.dataset.topbarHead !== name;
  });
  document.querySelectorAll('[data-topbar-actions]').forEach(function(actions){
    actions.hidden = actions.dataset.topbarActions !== name;
  });
}

function showView(name, el, options){
  options = options || {};
  const previous = document.querySelector('.view.active')?.id?.replace('view-', '') || '';
  if (previous === 'settings' && name !== 'settings' && typeof window.discardPharmacySettingsEdits === 'function') {
    window.discardPharmacySettingsEdits();
  }
  document.querySelectorAll('.nav-item[data-view]').forEach(n=>n.classList.remove('active'));
  if(el){ el.classList.add('active'); }
  else{
    const match = document.querySelector('.nav-item[data-view="'+name+'"]');
    if(match) match.classList.add('active');
  }
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  const viewEl = document.getElementById('view-'+name);
  if(viewEl) viewEl.classList.add('active');
  updateTopbarHead(name);
  document.querySelector('.content').scrollTo({top:0, behavior:'instant'});
  window.scrollTo({top:0, behavior:'instant'});
  if(name==='dashboard') initCharts();
  if(name==='sales') initSalesCharts();
  if(name==='analytics') initAnalyticsCharts();
  if(name==='settings' && typeof window.initSettingsBusinessMap === 'function') initSettingsBusinessMap();
  if(!options.skipUrl){
    syncViewUrl(name, options.replaceUrl === true);
  }
  if(name === 'orders'){
    markPharmacyOrdersSeen();
  }
}

function buildViewUrl(name){
  const url = new URL(window.location.href);
  url.searchParams.set('view', name);
  if(name === 'orders'){
    if(!url.searchParams.get('status')){
      url.searchParams.set('status', 'pending');
    }
  } else {
    url.searchParams.delete('status');
    url.searchParams.delete('order_id');
  }
  return url.pathname + url.search;
}

function syncViewUrl(name, replace){
  const nextUrl = buildViewUrl(name);
  const currentUrl = window.location.pathname + window.location.search;
  if(currentUrl === nextUrl) return;
  const state = {view: name};
  if(replace){
    history.replaceState(state, '', nextUrl);
  } else {
    history.pushState(state, '', nextUrl);
  }
}

function setPharmacyOrdersBadge(count){
  const badge = document.getElementById('nav-orders-badge');
  if(!badge) return;
  count = Math.max(0, Number(count) || 0);
  if(count < 1){
    badge.hidden = true;
    badge.textContent = '0';
    badge.setAttribute('aria-label', '0 new orders');
    return;
  }
  badge.hidden = false;
  badge.textContent = count > 99 ? '99+' : String(count);
  badge.setAttribute('aria-label', count + ' new orders');
}

function markPharmacyOrdersSeen(){
  setPharmacyOrdersBadge(0);
  const url = document.querySelector('.sidebar')?.getAttribute('data-orders-seen-url') || '';
  if(!url) return;
  fetch(url, { method:'POST', credentials:'same-origin' }).catch(function(){});
}

document.addEventListener('DOMContentLoaded', function(){
  if(typeof lucide !== 'undefined') lucide.createIcons();
  if(typeof initCharts === 'function') initCharts();
  const params = new URLSearchParams(window.location.search);
  const view = params.get('view');
  if(view && document.getElementById('view-' + view)){
    showView(view, null, {skipUrl: true});
  } else {
    updateTopbarHead(document.querySelector('.view.active')?.id?.replace('view-', '') || 'dashboard');
  }
});

window.addEventListener('popstate', function(){
  const params = new URLSearchParams(window.location.search);
  const view = params.get('view') || 'dashboard';
  if(document.getElementById('view-' + view)){
    showView(view, null, {skipUrl: true});
  }
});

document.addEventListener('click', function(e){
  const btn = e.target.closest('.tab-btn');
  if(!btn || btn.closest('#view-orders')) return;
  btn.parentElement.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
});

document.addEventListener('livesync:applied', function(){
  const active = document.querySelector('.view.active')?.id?.replace('view-', '') || '';
  document.querySelectorAll('.nav-item[data-view]').forEach(function(item){
    item.classList.toggle('active', item.getAttribute('data-view') === active);
  });
  if (active) updateTopbarHead(active);
  if (typeof lucide !== 'undefined') lucide.createIcons();
  if (typeof window.resetPharmacyDashboardCharts === 'function') window.resetPharmacyDashboardCharts();
  if (typeof window.resetPharmacySalesCharts === 'function') window.resetPharmacySalesCharts();
  if (typeof window.resetPharmacyAnalyticsCharts === 'function') window.resetPharmacyAnalyticsCharts();
  if (active === 'dashboard' && typeof initCharts === 'function') initCharts();
  if (active === 'sales' && typeof initSalesCharts === 'function') initSalesCharts();
  if (active === 'analytics' && typeof initAnalyticsCharts === 'function') initAnalyticsCharts();
});
