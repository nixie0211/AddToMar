<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
?>

let chartsInitialized = false;
window.resetPharmacyDashboardCharts = function(){
  chartsInitialized = false;
};
function drawDashboardFallback(canvas, daily, best){
  const width = Math.max(canvas.clientWidth || 520, 320);
  const height = 230;
  const ratio = window.devicePixelRatio || 1;
  canvas.width = width * ratio;
  canvas.height = height * ratio;
  canvas.style.width = width + 'px';
  canvas.style.height = height + 'px';
  const ctx = canvas.getContext('2d');
  ctx.scale(ratio, ratio);
  ctx.clearRect(0, 0, width, height);
  ctx.font = '11px Inter, sans-serif';
  ctx.fillStyle = '#8a9aa4';
  const values = (daily.orders || []).map(Number);
  const max = Math.max(1, ...values);
  const left = 34, right = 18, top = 16, bottom = 30;
  const chartW = width - left - right, chartH = height - top - bottom;
  ctx.strokeStyle = '#edf2f3';
  for(let i = 0; i <= 4; i++){
    const y = top + chartH - (chartH * i / 4);
    ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(width - right, y); ctx.stroke();
  }
  const step = chartW / Math.max(values.length, 1);
  values.forEach((value, index) => {
    const barH = chartH * value / max;
    const x = left + index * step + step * .25;
    ctx.fillStyle = '#0f7a72';
    ctx.fillRect(x, top + chartH - barH, step * .5, barH);
    if(index % 2 === 0 || values.length < 8){
      ctx.fillStyle = '#8a9aa4';
      ctx.textAlign = 'center';
      ctx.fillText((daily.labels || [])[index] || '', x + step * .25, height - 8);
    }
  });
  const revenue = (daily.revenue || []).map(Number);
  const maxRevenue = Math.max(1, ...revenue);
  ctx.strokeStyle = '#2aab9a'; ctx.lineWidth = 2.5; ctx.beginPath();
  revenue.forEach((value, index) => {
    const x = left + index * step + step * .5;
    const y = top + chartH - chartH * value / maxRevenue;
    index ? ctx.lineTo(x, y) : ctx.moveTo(x, y);
  });
  ctx.stroke();
}

function drawBestSellingFallback(canvas, best){
  const width = Math.max(canvas.clientWidth || 420, 280), height = 230;
  const ratio = window.devicePixelRatio || 1;
  canvas.width = width * ratio; canvas.height = height * ratio;
  canvas.style.width = width + 'px'; canvas.style.height = height + 'px';
  const ctx = canvas.getContext('2d'); ctx.scale(ratio, ratio);
  ctx.clearRect(0, 0, width, height);
  const rows = (best || []).slice(0, 5);
  if(!rows.length){ ctx.fillStyle='#8a9aa4'; ctx.font='13px Inter, sans-serif'; ctx.textAlign='center'; ctx.fillText('No sales yet', width / 2, height / 2); return; }
  const max = Math.max(1, ...rows.map(row => Number(row.total) || 0));
  rows.forEach((row, index) => {
    const y = 18 + index * 39, value = Number(row.total) || 0;
    ctx.fillStyle = '#0f7a72'; ctx.font = '12px Inter, sans-serif'; ctx.textAlign = 'left';
    ctx.fillText(String(row.label || 'Other').slice(0, 28), 4, y + 10);
    ctx.fillStyle = '#e4f1ef'; ctx.fillRect(4, y + 16, width - 48, 9);
    ctx.fillStyle = ['#0f7a72','#2aab9a','#d97706','#16a34a','#8bbdb5'][index];
    ctx.fillRect(4, y + 16, (width - 48) * value / max, 9);
    ctx.fillStyle = '#4a626d'; ctx.textAlign = 'right'; ctx.fillText(value + ' units', width - 4, y + 24);
  });
}

function initCharts(){
  if(chartsInitialized) return;
  chartsInitialized = true;

  const dailyCanvas = document.getElementById('chartDaily');
  const bestCanvas = document.getElementById('chartBestSelling');
  if(!dailyCanvas || !bestCanvas) return;

  const data = window.PHARMACY_CHART_DATA || {};
  const daily = data.daily || {labels:[], orders:[], revenue:[]};
  const best = Array.isArray(data.bestSelling) ? data.bestSelling : [];

  if(typeof Chart === 'undefined'){
    drawDashboardFallback(dailyCanvas, daily, best);
    drawBestSellingFallback(bestCanvas, best);
    return;
  }

  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.color = '#4a626d';

  new Chart(dailyCanvas, {
    type:'bar',
    data:{
      labels: daily.labels || [],
      datasets:[
        {label:'Orders', data: daily.orders || [], backgroundColor:'#0f7a72', borderRadius:6, barPercentage:0.55, yAxisID:'y'},
        {label:'Revenue (₱k)', type:'line', data: daily.revenue || [], borderColor:'#2aab9a', backgroundColor:'#2aab9a', tension:0.4, pointRadius:2, pointHoverRadius:5, borderWidth:2.5, yAxisID:'y1'}
      ]
    },
    options:{
      responsive:true,
      interaction:{mode:'index', intersect:false},
      plugins:{legend:{display:false}, tooltip:{callbacks:{label:function(context){
        const value = Number(context.raw) || 0;
        return context.dataset.label === 'Revenue (₱k)' ? 'Revenue: ₱' + value.toFixed(1) + 'k' : 'Orders: ' + value;
      }}}},
      scales:{
        y:{grid:{color:'#F1F5F9'}, ticks:{font:{size:11}}},
        y1:{position:'right', grid:{display:false}, ticks:{font:{size:11}}},
        x:{grid:{display:false}, ticks:{font:{size:11}}}
      }
    }
  });

  new Chart(bestCanvas, {
    type:'doughnut',
    data:{
      labels: best.length ? best.map(function(row){ return row.label || 'Other'; }) : ['No sales yet'],
      datasets:[{
        data: best.length ? best.map(function(row){ return Number(row.total) || 0; }) : [1],
        backgroundColor: best.length ? ['#0f7a72','#2aab9a','#d97706','#16a34a','#d3e6e2'] : ['#d3e6e2'],
        borderWidth:0
      }]
    },
    options:{
      responsive:true, cutout:'68%',
      plugins:{legend:{position:'bottom', labels:{boxWidth:9, boxHeight:9, usePointStyle:true, pointStyle:'circle', font:{size:11.5, weight:600}, padding:14}}, tooltip:{callbacks:{label:function(context){ return ' ' + context.label + ': ' + (Number(context.raw) || 0) + ' units'; }}}}
    }
  });
}
