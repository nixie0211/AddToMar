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

function toggleNotifDropdown(e){
  e.stopPropagation();
  const dd = document.getElementById('notif-dropdown');
  const wasOpen = dd.classList.contains('open');
  dd.classList.toggle('open');
  if(wasOpen){
    resetNotifPanel();
  }
}

function getActiveNotifFilterMode(){
  const activeTab = document.querySelector('#notif-dropdown .notif-tab.active');
  return activeTab && activeTab.textContent.trim().toLowerCase() === 'unread' ? 'unread' : 'all';
}

function resetNotifPanel(){
  const recent = document.getElementById('notif-panel-recent');
  const all = document.getElementById('notif-panel-all');
  const link = document.getElementById('notif-see-previous');
  if(recent) recent.hidden = false;
  if(all) all.hidden = true;
  if(link) link.textContent = 'See previous notifications';
  clearNotifTypeFilter();
  applyNotifFilter(getActiveNotifFilterMode());
}

function clearNotifTypeFilter(){
  document.querySelectorAll('#notif-panel-all .notif-item').forEach(function(item){
    delete item.dataset.typeFilter;
  });
}

function toggleNotifHistory(e){
  if(e){
    e.preventDefault();
    e.stopPropagation();
  }
  const recent = document.getElementById('notif-panel-recent');
  const all = document.getElementById('notif-panel-all');
  const link = document.getElementById('notif-see-previous');
  if(!recent || !all) return;
  const showAll = all.hidden;
  recent.hidden = showAll;
  all.hidden = !showAll;
  if(link){
    link.textContent = showAll ? 'Show recent notifications' : 'See previous notifications';
  }
  if(showAll){
    clearNotifTypeFilter();
  }
  applyNotifFilter(getActiveNotifFilterMode());
  const scroll = document.querySelector('#notif-dropdown .notif-scroll');
  if(scroll) scroll.scrollTop = 0;
}

function showNotifTypeInPanel(e, type){
  if(e){
    e.preventDefault();
    e.stopPropagation();
  }
  const recent = document.getElementById('notif-panel-recent');
  const all = document.getElementById('notif-panel-all');
  const link = document.getElementById('notif-see-previous');
  if(!recent || !all) return;
  recent.hidden = true;
  all.hidden = false;
  if(link) link.textContent = 'Show recent notifications';
  clearNotifTypeFilter();
  all.querySelectorAll('.notif-item').forEach(function(item){
    if(item.dataset.type !== type){
      item.dataset.typeFilter = 'hidden';
    }
  });
  applyNotifFilter(getActiveNotifFilterMode());
  const scroll = document.querySelector('#notif-dropdown .notif-scroll');
  if(scroll) scroll.scrollTop = 0;
}

document.addEventListener('click', function(e){
  const dd = document.getElementById('notif-dropdown');
  if(dd && dd.classList.contains('open') && !e.target.closest('.notif-wrap')){
    dd.classList.remove('open');
    resetNotifPanel();
  }
});

const ALLOWED_NOTIF_TYPES = ['low-stock', 'expiring', 'new-order'];

function isAllowedNotifType(type){
  return ALLOWED_NOTIF_TYPES.includes(type);
}

function applyNotifFilter(mode){
  document.querySelectorAll('#notif-dropdown .notif-item').forEach(function(item){
    const panel = item.closest('#notif-panel-recent, #notif-panel-all');
    if(panel && panel.hidden){
      return;
    }
    const type = item.dataset.type || '';
    if(!isAllowedNotifType(type)){
      item.style.display = 'none';
      return;
    }
    if(item.dataset.typeFilter === 'hidden'){
      item.style.display = 'none';
      return;
    }
    item.style.display = (mode === 'unread' && item.dataset.unread !== '1') ? 'none' : 'flex';
  });

  document.querySelectorAll('#view-notifications .list-row[data-type]').forEach(function(row){
    const type = row.dataset.type || '';
    row.style.display = isAllowedNotifType(type) ? '' : 'none';
  });
}

function filterNotifTab(mode, el){
  el.parentElement.querySelectorAll('.notif-tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  applyNotifFilter(mode);
}

document.addEventListener('DOMContentLoaded', function(){
  if(typeof lucide !== 'undefined') lucide.createIcons();
  if(typeof initCharts === 'function') initCharts();
  applyNotifFilter('all');
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
  applyNotifFilter(getActiveNotifFilterMode());
});
