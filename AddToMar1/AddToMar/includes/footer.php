    <footer class="app-footer text-center py-3">
        <small>&copy; <?php echo date('Y'); ?> AddToMar Pharmacy. All rights reserved. | Supporting SDG 3: Good Health &amp; Well-Being</small>
    </footer>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (needed for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- App JS -->
    <?php $js_v = file_exists(__DIR__ . '/../assets/js/main.js') ? filemtime(__DIR__ . '/../assets/js/main.js') : time(); ?>
    <script src="<?php echo BASE_URL; ?>assets/js/main.js?v=<?php echo $js_v; ?>"></script>
    <?php if (isset($extra_scripts)) echo $extra_scripts; ?>
</body>
</html>
