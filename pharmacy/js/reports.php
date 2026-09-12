<?php
declare(strict_types=1);
header('Content-Type: application/javascript; charset=UTF-8');
?>
document.addEventListener('click', function(event){
  const button = event.target.closest('[data-report][data-report-format]');
  if(!button) return;
  const report = button.dataset.report;
  const format = button.dataset.reportFormat;
  if(!report || !format) return;
  window.open('api/download-report.php?report=' + encodeURIComponent(report) + '&format=' + encodeURIComponent(format), format === 'pdf' ? '_blank' : '_self');
});
