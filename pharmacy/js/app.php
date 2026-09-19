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
  if(!dd) return;
  const wasOpen = dd.classList.contains('open');
  dd.classList.toggle('open');
  if(wasOpen){
    collapseNotifHistory();
  }
}

function closePharmacyNotif(){
  const dd = document.getElementById('notif-dropdown');
  if(!dd) return;
  dd.classList.remove('open');
  collapseNotifHistory();
}

function getActiveNotifFilterMode(){
  const activeTab = document.querySelector('#notif-dropdown .notif-tab.active');
  return activeTab && activeTab.textContent.trim().toLowerCase() === 'unread' ? 'unread' : 'all';
}

function collapseNotifHistory(){
  const dd = document.getElementById('notif-dropdown');
  if(!dd) return;
  dd.classList.remove('is-expanded');
  const scroll = dd.querySelector('.notif-scroll');
  if(scroll) scroll.scrollTop = 0;
  const foot = document.getElementById('notif-foot');
  if(foot && dd.getAttribute('data-has-more') === '1') foot.hidden = false;
  applyNotifFilter(getActiveNotifFilterMode());
}

function expandNotifHistory(e){
  if(e){
    e.preventDefault();
    e.stopPropagation();
  }
  const dd = document.getElementById('notif-dropdown');
  const foot = document.getElementById('notif-foot');
  if(!dd) return;
  dd.classList.add('is-expanded');
  if(foot) foot.hidden = true;
  applyNotifFilter(getActiveNotifFilterMode());
}

function pharmacyNotifMarkReadUrl(){
  const wrap = document.querySelector('[data-live-region="pharmacy-notify"]');
  return wrap ? String(wrap.getAttribute('data-mark-read-url') || '').trim() : '';
}

function pharmacyNotifReadCacheKey(){
  return 'pharmacy_notif_reads';
}

function pharmacyNotifRememberRead(id){
  id = String(id || '').trim();
  if(!id) return;
  try{
    const raw = localStorage.getItem(pharmacyNotifReadCacheKey());
    const ids = raw ? JSON.parse(raw) : [];
    if(!Array.isArray(ids)) return;
    if(ids.indexOf(id) === -1) ids.push(id);
    localStorage.setItem(pharmacyNotifReadCacheKey(), JSON.stringify(ids.slice(-80)));
  }catch(err){}
}

function pharmacyNotifCachedReads(){
  try{
    const raw = localStorage.getItem(pharmacyNotifReadCacheKey());
    const ids = raw ? JSON.parse(raw) : [];
    return Array.isArray(ids) ? ids.map(String) : [];
  }catch(err){
    return [];
  }
}

function paintPharmacyNotificationRead(item){
  if(!item) return false;
  const wasUnread = item.classList.contains('is-unread') || item.getAttribute('data-unread') === '1';
  item.classList.remove('is-unread');
  item.classList.add('is-read');
  item.setAttribute('data-unread', '0');
  const dot = item.querySelector('.notif-dot');
  if(dot) dot.remove();
  return wasUnread;
}

function updatePharmacyNotifBadge(unread){
  const badge = document.getElementById('notif-count-badge');
  if(!badge) return;
  unread = Math.max(0, Number(unread) || 0);
  const btn = badge.closest('.icon-btn');
  if(unread < 1){
    badge.hidden = true;
    badge.textContent = '0';
    if(btn) btn.setAttribute('aria-label', 'Notifications');
    return;
  }
  badge.hidden = false;
  badge.textContent = unread > 9 ? '9+' : String(unread);
  if(btn) btn.setAttribute('aria-label', unread + ' unread notifications');
}

function currentPharmacyUnreadCount(){
  return document.querySelectorAll('#notif-dropdown .notif-item[data-unread="1"]').length;
}

function applyPharmacyNotifReadCache(){
  pharmacyNotifCachedReads().forEach(function(id){
    document.querySelectorAll('.notif-item[data-id="' + id.replace(/"/g, '') + '"]').forEach(function(item){
      paintPharmacyNotificationRead(item);
    });
  });
  updatePharmacyNotifBadge(currentPharmacyUnreadCount());
}

function markPharmacyNotificationRead(item){
  if(!item) return;
  const id = String(item.getAttribute('data-id') || '').trim();
  const changed = paintPharmacyNotificationRead(item);
  if(id){
    document.querySelectorAll('.notif-item[data-id="' + id.replace(/"/g, '') + '"]').forEach(function(match){
      paintPharmacyNotificationRead(match);
    });
    pharmacyNotifRememberRead(id);
  }
  if(changed){
    applyNotifFilter(getActiveNotifFilterMode());
    updatePharmacyNotifBadge(currentPharmacyUnreadCount());
  }
  if(!id || !changed) return;
  const url = pharmacyNotifMarkReadUrl();
  if(!url) return;
  const form = new FormData();
  form.append('notification_id', id);
  fetch(url, { method:'POST', body:form, credentials:'same-origin' })
    .then(function(response){ return response.json(); })
    .then(function(data){
      if(data && typeof data.unread === 'number'){
        updatePharmacyNotifBadge(data.unread);
      }
    })
    .catch(function(){});
}

function openPharmacyNotification(e, view){
  if(e){
    e.preventDefault();
    e.stopPropagation();
  }
  const item = e && e.currentTarget ? e.currentTarget : null;
  markPharmacyNotificationRead(item);
  closePharmacyNotif();
  if(view && typeof showView === 'function'){
    showView(view);
  }
}

document.addEventListener('click', function(e){
  const dd = document.getElementById('notif-dropdown');
  if(dd && dd.classList.contains('open') && !e.target.closest('.notif-wrap')){
    closePharmacyNotif();
  }
});

function applyNotifFilter(mode){
  const dd = document.getElementById('notif-dropdown');
  if(!dd) return;
  const expanded = dd.classList.contains('is-expanded');
  dd.querySelectorAll('.notif-item').forEach(function(item){
    const unread = item.dataset.unread === '1';
    const later = item.classList.contains('is-later');
    const show = (mode !== 'unread' || unread) && (expanded || !later);
    item.hidden = !show;
    item.style.display = show ? 'flex' : 'none';
  });
  dd.querySelectorAll('.notif-section').forEach(function(section){
    const visible = Array.from(section.querySelectorAll('.notif-item')).some(function(item){ return !item.hidden; });
    section.hidden = !visible;
  });
}

function filterNotifTab(mode, el, event){
  if(event){
    event.preventDefault();
    event.stopPropagation();
  }
  el.parentElement.querySelectorAll('.notif-tab').forEach(function(tab){ tab.classList.remove('active'); });
  el.classList.add('active');
  applyNotifFilter(mode);
}

document.addEventListener('DOMContentLoaded', function(){
  if(typeof lucide !== 'undefined') lucide.createIcons();
  if(typeof initCharts === 'function') initCharts();
  applyPharmacyNotifReadCache();
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
  applyPharmacyNotifReadCache();
  applyNotifFilter(getActiveNotifFilterMode());
});
