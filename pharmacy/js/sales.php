<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
?>

let salesChartsInitialized = false;
let salesLineChart = null;
window.resetPharmacySalesCharts = function(){
  if (salesLineChart && typeof salesLineChart.destroy === 'function') {
    salesLineChart.destroy();
  }
  salesLineChart = null;
  salesChartsInitialized = false;
};
function drawSalesLineFallback(canvas, labels, values){
  const width=Math.max(canvas.clientWidth||520,320), height=230, ratio=window.devicePixelRatio||1;
  canvas.width=width*ratio; canvas.height=height*ratio; canvas.style.width=width+'px'; canvas.style.height=height+'px';
  const ctx=canvas.getContext('2d'); ctx.scale(ratio,ratio); ctx.clearRect(0,0,width,height);
  const left=34,right=18,top=16,bottom=30, chartW=width-left-right,chartH=height-top-bottom,max=Math.max(1,...values.map(Number));
  ctx.strokeStyle='#edf2f3'; for(let i=0;i<=4;i++){const y=top+chartH-chartH*i/4;ctx.beginPath();ctx.moveTo(left,y);ctx.lineTo(width-right,y);ctx.stroke();}
  ctx.strokeStyle='#0f7a72';ctx.lineWidth=2.5;ctx.beginPath(); values.forEach((v,i)=>{const x=left+(chartW*i/Math.max(values.length-1,1)),y=top+chartH-chartH*(Number(v)||0)/max;i?ctx.lineTo(x,y):ctx.moveTo(x,y);});ctx.stroke();
  ctx.fillStyle='#8a9aa4';ctx.font='11px Inter, sans-serif';ctx.textAlign='center'; labels.forEach((label,i)=>{if(i%2===0||labels.length<8)ctx.fillText(label,left+(chartW*i/Math.max(labels.length-1,1)),height-8);});
}
function drawSalesCategoryFallback(canvas, categories){
  const width=Math.max(canvas.clientWidth||420,280),height=230,ratio=window.devicePixelRatio||1; canvas.width=width*ratio;canvas.height=height*ratio;canvas.style.width=width+'px';canvas.style.height=height+'px';
  const ctx=canvas.getContext('2d');ctx.scale(ratio,ratio);ctx.clearRect(0,0,width,height);const rows=categories.slice(0,6);if(!rows.length){ctx.fillStyle='#8a9aa4';ctx.font='13px Inter, sans-serif';ctx.textAlign='center';ctx.fillText('No sales yet',width/2,height/2);return;}const max=Math.max(1,...rows.map(r=>Number(r.total)||0));
  rows.forEach((row,i)=>{const y=18+i*32,v=Number(row.total)||0;ctx.fillStyle='#4a626d';ctx.font='12px Inter, sans-serif';ctx.textAlign='left';ctx.fillText(String(row.label||'Uncategorized').slice(0,24),4,y+10);ctx.fillStyle='#e4f1ef';ctx.fillRect(4,y+16,width-50,8);ctx.fillStyle=['#0f7a72','#2aab9a','#d97706','#16a34a','#c73e3e','#7a9190'][i];ctx.fillRect(4,y+16,(width-50)*v/max,8);ctx.fillStyle='#4a626d';ctx.textAlign='right';ctx.fillText(v+' units',width-4,y+24);});
}
function initSalesCharts(){
  if(salesChartsInitialized) return;
  salesChartsInitialized = true;

  const data = window.PHARMACY_CHART_DATA || {};
  const sales = data.sales || {labels:[], revenue:[]};
  const categories = Array.isArray(data.categories) ? data.categories : [];

  const lineCanvas=document.getElementById('chartSalesLine'), categoryCanvas=document.getElementById('chartSalesCategory');
  if(!lineCanvas || !categoryCanvas) return;
  if(typeof Chart === 'undefined'){
    drawSalesLineFallback(lineCanvas, sales.labels || [], sales.revenue || []);
    drawSalesCategoryFallback(categoryCanvas, categories);
    return;
  }

  salesLineChart = new Chart(lineCanvas, {
    type:'line',
    data:{
      labels: sales.labels || [],
      datasets:[{label:'Revenue', data: sales.revenue || [], borderColor:'#0f7a72', backgroundColor:'rgba(15,122,114,0.08)', fill:true, tension:0.4, pointRadius:3, pointBackgroundColor:'#0f7a72', borderWidth:2.5}]
    },
    options:{responsive:true, plugins:{legend:{display:false}}, scales:{y:{grid:{color:'#F1F5F9'}, ticks:{font:{size:11}}}, x:{grid:{display:false}, ticks:{font:{size:11}}}}}
  });

  new Chart(categoryCanvas, {
    type:'pie',
    data:{
      labels: categories.length ? categories.map(function(row){ return row.label || 'Uncategorized'; }) : ['No sales yet'],
      datasets:[{
        data: categories.length ? categories.map(function(row){ return Number(row.total) || 0; }) : [1],
        backgroundColor: categories.length ? ['#0f7a72','#2aab9a','#d97706','#16a34a','#c73e3e','#7a9190'] : ['#d3e6e2'],
        borderWidth:0
      }]
    },
    options:{responsive:true, plugins:{legend:{position:'bottom', labels:{boxWidth:9, boxHeight:9, usePointStyle:true, pointStyle:'circle', font:{size:11, weight:600}, padding:12}}}}
  });
}

function formatSalesMoney(value){
  return new Intl.NumberFormat('en-PH', {style:'currency', currency:'PHP', maximumFractionDigits:value >= 1000 ? 0 : 2}).format(Number(value) || 0);
}

document.addEventListener('click', function(event){
  const button = event.target.closest('[data-sales-period]');
  if(!button) return;
  const period = button.dataset.salesPeriod;
  button.parentElement.querySelectorAll('[data-sales-period]').forEach(function(item){ item.classList.toggle('active', item === button); });
  fetch('api/sales-data.php?period=' + encodeURIComponent(period), {credentials:'same-origin'})
    .then(function(response){ if(!response.ok) throw new Error('Unable to load sales data'); return response.json(); })
    .then(function(payload){
      const stats = payload.stats || {};
      document.querySelectorAll('[data-sales-stat]').forEach(function(el){
        const key = el.dataset.salesStat;
        el.textContent = key === 'orders' ? (Number(stats[key]) || 0).toLocaleString() + ' orders' : formatSalesMoney(stats[key]);
      });
      const label = document.querySelector('[data-sales-orders-label]');
      if(label) label.textContent = payload.label || 'Sales';
      if(salesLineChart){
        salesLineChart.data.labels = (payload.trend || {}).labels || [];
        salesLineChart.data.datasets[0].data = (payload.trend || {}).revenue || [];
        salesLineChart.update();
      }else if(typeof Chart === 'undefined'){
        const lineCanvas=document.getElementById('chartSalesLine');
        if(lineCanvas) drawSalesLineFallback(lineCanvas, (payload.trend||{}).labels||[], (payload.trend||{}).revenue||[]);
      }
    })
    .catch(function(){ window.alert('Sales data could not be loaded. Please try again.'); });
});
