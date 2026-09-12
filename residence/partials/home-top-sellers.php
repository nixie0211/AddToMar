<?php
$section = [
    'id' => 'top-sellers',
    'title' => 'Current Top Sellers',
    'items' => $residenceTopSellers ?? [],
    'badge' => 'top',
];
include RESIDENCE_ROOT . '/partials/home-spotlight-section.php';
