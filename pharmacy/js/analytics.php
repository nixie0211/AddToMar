<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
?>

let analyticsChartsInitialized = false;
let analyticsTrendChart = null;
let analyticsStockChart = null;
window.resetPharmacyAnalyticsCharts = function(){
  if (analyticsTrendChart && typeof analyticsTrendChart.destroy === 'function') {
    analyticsTrendChart.destroy();
  }
  if (analyticsStockChart && typeof analyticsStockChart.destroy === 'function') {
    analyticsStockChart.destroy();
  }
  analyticsTrendChart = null;
  analyticsStockChart = null;
  analyticsChartsInitialized = false;
};
function drawAnalyticsFallback(canvas, trend){
  const width=Math.max(canvas.clientWidth||620,320),height=240,ratio=window.devicePixelRatio||1;canvas.width=width*ratio;canvas.height=height*ratio;canvas.style.width=width+'px';canvas.style.height=height+'px';
  const ctx=canvas.getContext('2d');ctx.scale(ratio,ratio);ctx.clearRect(0,0,width,height);const labels=trend.labels||[],a=(trend.stockIn||[]).map(Number),b=(trend.stockOut||[]).map(Number),max=Math.max(1,...a,...b),left=36,right=16,top=16,bottom=30,w=width-left-right,h=height-top-bottom,step=w/Math.max(labels.length,1);
  ctx.strokeStyle='#edf2f3';for(let i=0;i<=4;i++){const y=top+h-h*i/4;ctx.beginPath();ctx.moveTo(left,y);ctx.lineTo(width-right,y);ctx.stroke();}
  labels.forEach((label,i)=>{const x=left+i*step+step*.18;ctx.fillStyle='#2aab9a';ctx.fillRect(x,top+h-h*(a[i]||0)/max,step*.28,h*(a[i]||0)/max);ctx.fillStyle='#0f7a72';ctx.fillRect(x+step*.32,top+h-h*(b[i]||0)/max,step*.28,h*(b[i]||0)/max);if(i%2===0||labels.length<8){ctx.fillStyle='#8a9aa4';ctx.font='11px Inter, sans-serif';ctx.textAlign='center';ctx.fillText(label,x+step*.3,height-8);}});
}
function initAnalyticsCharts(){
  if(analyticsChartsInitialized) return;
  analyticsChartsInitialized = true;

  const trend = (window.PHARMACY_CHART_DATA && window.PHARMACY_CHART_DATA.inventoryTrend) || {labels:[], stockIn:[], stockOut:[]};

  const canvas=document.getElementById('chartInvTrend');
  if(!canvas) return;
  if(typeof Chart === 'undefined'){
    drawAnalyticsFallback(canvas, trend);
    return;
  }

  analyticsTrendChart = new Chart(canvas, {
    type:'bar',
    data:{
      labels: trend.labels || [],
      datasets:[
        {label:'Stock in', data: trend.stockIn || [], backgroundColor:'#2aab9a', borderRadius:6, barPercentage:0.5},
        {label:'Stock out', data: trend.stockOut || [], backgroundColor:'#0f7a72', borderRadius:6, barPercentage:0.5}
      ]
    },
    options:{
      responsive:true,
      plugins:{legend:{position:'bottom', labels:{boxWidth:9, boxHeight:9, usePointStyle:true, pointStyle:'circle', font:{size:11.5, weight:600}, padding:14}}},
      scales:{y:{grid:{color:'#F1F5F9'}, ticks:{font:{size:11}}}, x:{grid:{display:false}, ticks:{font:{size:11}}}}
    }
  });

  const mixNode = document.getElementById('pharmacy-analytics-stock');
  const mixCanvas = document.getElementById('chartStockMix');
  if (mixCanvas && mixNode && mixNode.textContent) {
    let mix = {labels:[], values:[], colors:[]};
    try { mix = JSON.parse(mixNode.textContent) || mix; } catch (e) {}
    analyticsStockChart = new Chart(mixCanvas, {
      type:'doughnut',
      data:{
        labels: mix.labels || [],
        datasets:[{data: mix.values || [], backgroundColor: mix.colors || [], borderWidth:0}]
      },
      options:{
        responsive:true,
        cutout:'62%',
        plugins:{legend:{position:'bottom', labels:{boxWidth:9, boxHeight:9, usePointStyle:true, pointStyle:'circle', font:{size:11, weight:600}, padding:12}}}
      }
    });
  }
}

document.addEventListener('change', function(event){
  const select = event.target.closest('.analytics-period-select');
  if(!select) return;
  const months = select.value;
  fetch('api/analytics-data.php?months=' + encodeURIComponent(months), {credentials:'same-origin'})
    .then(function(response){ if(!response.ok) throw new Error('Unable to load analytics'); return response.json(); })
    .then(function(payload){
      const trend = payload.trend || {};
      if(analyticsTrendChart){
        analyticsTrendChart.data.labels = trend.labels || [];
        analyticsTrendChart.data.datasets[0].data = trend.stockIn || [];
        analyticsTrendChart.data.datasets[1].data = trend.stockOut || [];
        analyticsTrendChart.update();
      }else if(typeof Chart === 'undefined'){
        const canvas=document.getElementById('chartInvTrend');
        if(canvas) drawAnalyticsFallback(canvas, trend);
      }
      const label = document.querySelector('[data-analytics-trend-label]');
      if(label) label.textContent = '— ' + months + ' months';
    })
    .catch(function(){ window.alert('Analytics data could not be loaded. Please try again.'); });
});
