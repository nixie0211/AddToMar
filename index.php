<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pharmacy-accounts.php';
require_once __DIR__ . '/includes/live-sync.php';

caps_bootstrap();

$registeredPharmacies = pharmacy_accounts_list_approved();
$homePharmacyCity = static function (array $pharmacy): array {
    $location = strtolower(
        trim((string) (($pharmacy['address'] ?? '') . ' ' . ($pharmacy['pharmacy_name'] ?? '')))
    );
    if (str_contains($location, 'san nicolas')) {
        return ['key' => 'san-nicolas', 'label' => 'San Nicolas'];
    }
    if (str_contains($location, 'batac')) {
        return ['key' => 'batac', 'label' => 'Batac'];
    }
    return ['key' => 'laoag', 'label' => 'Laoag'];
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AddToMar — Online Medicine Ordering</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
  <style>
    @view-transition {
      navigation: auto;
    }

    :root {
      --mint: #f3fbf9;
      --mint-deep: #e7f4f1;
      --white: #ffffff;
      --section-bg: #f9fdfc;
      --navy: #0b2230;
      --text: #132f3f;
      --body-text: #4a626d;
      --border: #d3e6e2;
      --teal: #0f7a72;
      --teal-light: #2aab9a;
      --teal-dark: #0a6059;
      --teal-soft: #d6efeb;
      --green: var(--teal);
      --green-light: var(--teal-light);
      --green-soft: var(--teal-soft);
      --accent: #c73e3e;
      --accent-dark: #a83232;
      --accent-soft: #fde8e8;
      --header-height: 88px;
    }

    ::view-transition-old(root),
    ::view-transition-new(root){
      animation: none;
    }
    ::view-transition-group(landing-hero){
      animation-duration: 0.65s;
      animation-timing-function: cubic-bezier(.22, 1, .36, 1);
    }

    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
      background: #dcefe9 url('fpb.png') center / cover fixed no-repeat;
      color: var(--text);
      min-height: 100vh;
      font-size: 1rem;
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background: rgba(220, 239, 233, 0.18) url('fpb.png') center / cover no-repeat;
    }

    h1, h2, h3, h4 {
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
      color: var(--navy);
      letter-spacing: -0.025em;
    }

    .page {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      padding-top: var(--header-height);
    }

    .hero-wrap {
      min-height: 88vh;
      display: flex;
      flex-direction: column;
      background: transparent;
      position: relative;
      overflow: visible;
      padding-bottom: 0;
      view-transition-name: landing-hero;
    }

    .hero-bg {
      position: absolute;
      inset: 0;
      pointer-events: none;
      z-index: 0;
    }

    .hero-bg span {
      position: absolute;
      border-radius: 50%;
      opacity: 0.35;
      animation: drift 18s ease-in-out infinite;
    }

    .hero-bg span:nth-child(1) {
      width: 280px;
      height: 280px;
      background: radial-gradient(circle, var(--green-soft) 0%, transparent 70%);
      top: -60px;
      right: 10%;
      animation-duration: 22s;
    }

    .hero-bg span:nth-child(2) {
      width: 200px;
      height: 200px;
      background: radial-gradient(circle, var(--teal-soft) 0%, transparent 70%);
      bottom: 10%;
      left: 5%;
      animation-duration: 16s;
      animation-delay: -4s;
    }

    .hero-bg span:nth-child(3) {
      width: 140px;
      height: 140px;
      background: radial-gradient(circle, var(--mint-deep) 0%, transparent 70%);
      top: 40%;
      left: 35%;
      animation-duration: 20s;
      animation-delay: -8s;
    }

    .hero-wrap .hero {
      position: relative;
      z-index: 1;
    }

  /* Header */
    header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      padding: 1rem 3.5rem;
      background: rgba(243, 251, 249, 0.62);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-bottom: 1px solid var(--border);
    }

    .page section[id] {
      scroll-margin-top: var(--header-height);
    }

    .nav {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      gap: 1rem;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      text-decoration: none;
    }

    .logo img {
      height: 52px;
      width: auto;
      display: block;
    }

    .logo-text {
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
      font-size: 1.25rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: #0a6059;
      line-height: 1;
      white-space: nowrap;
    }

    .nav-links {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 2.25rem;
      list-style: none;
    }

    .nav-links a {
      text-decoration: none;
      color: var(--body-text);
      font-size: 0.9rem;
      font-weight: 500;
      transition: color 0.2s;
    }

    .nav-links a:hover {
      color: var(--teal);
    }

    .nav-actions {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 0.75rem;
    }

    .login-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      background: var(--navy);
      border: none;
      cursor: pointer;
      color: #fff;
      padding: 0.5rem 1rem;
      border-radius: 999px;
      font-family: inherit;
      font-size: 0.875rem;
      font-weight: 600;
      text-decoration: none;
      transition: background 0.2s, transform 0.15s;
    }

    .login-btn:hover {
      background: var(--accent);
      transform: translateY(-1px);
    }

    .login-btn svg {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
    }

    .menu-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 1.4rem;
      cursor: pointer;
      color: var(--text);
      line-height: 1;
    }

  /* Hero */
    .hero {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      max-width: 1400px;
      margin: 0 auto;
      width: 100%;
      padding: 0.5rem 2.5rem 1.75rem;
      min-height: 0;
    }

    .hero-body {
      flex: 1;
      display: grid;
      grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
      align-items: center;
      gap: 0.75rem;
      min-height: 0;
    }

    .hero-content {
      max-width: 460px;
      padding-left: 0.25rem;
    }

    .hero-content h1 {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: clamp(2.25rem, 4vw, 3.35rem);
      font-weight: 700;
      line-height: 1.12;
      letter-spacing: -0.02em;
      color: var(--navy);
      margin-bottom: 1rem;
    }

    .hero-content h1 .highlight {
      position: relative;
      display: inline-block;
    }

    .hero-content h1 .highlight::after {
      content: '';
      position: absolute;
      left: -0.05em;
      bottom: 0.05em;
      width: calc(100% + 0.1em);
      height: 0.22em;
      background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 220 14' preserveAspectRatio='none'%3E%3Cpath d='M0 10 Q30 3 60 10 T120 9 T180 7 T220 10' fill='none' stroke='%232aab9a' stroke-width='2.5' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat center bottom;
      background-size: 100% 100%;
      pointer-events: none;
      animation: waveLine 2.5s ease-in-out infinite;
    }

    .hero-content p {
      font-size: 1rem;
      line-height: 1.7;
      color: var(--body-text);
      margin-bottom: 0.85rem;
      max-width: 400px;
    }

    .location-selector {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1.35rem;
      animation: fadeInUp 0.9s ease 0.22s forwards;
      opacity: 0;
      max-width: 100%;
    }

    .location-selector svg {
      width: 18px;
      height: 18px;
      flex-shrink: 0;
      color: var(--teal);
    }

    .location-areas {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--teal);
      line-height: 1.4;
      letter-spacing: -0.01em;
    }

    .hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.65rem;
      animation: fadeInUp 0.9s ease 0.3s forwards;
      opacity: 0;
    }

    .btn-hero {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.75rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      padding: 0.8rem 1.4rem;
      border-radius: 999px;
      transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }

    .btn-hero-primary {
      background: var(--teal);
      color: #fff;
      border: 2px solid var(--teal);
    }

    .btn-hero-primary:hover {
      transform: translateY(-2px);
      background: var(--teal-dark);
      border-color: var(--teal-dark);
      box-shadow: 0 10px 28px rgba(15, 122, 114, 0.28);
    }

    .btn-hero-secondary {
      background: transparent;
      color: var(--teal);
      border: 2px solid var(--teal);
    }

    .btn-hero-secondary:hover {
      transform: translateY(-2px);
      background: var(--teal-soft);
      box-shadow: 0 8px 20px rgba(15, 122, 114, 0.12);
    }

    .hero-image {
      display: flex;
      justify-content: flex-end;
      align-items: center;
    }

    .hero-image img {
      width: 100%;
      max-width: 1050px;
      height: auto;
      display: block;
      object-fit: contain;
      animation: float 5s ease-in-out infinite;
    }

  /* Features bar — part of hero section */
    .features-bar {
      width: 100%;
      max-width: 1100px;
      margin: 1.25rem auto 0;
      padding: 0;
      flex-shrink: 0;
    }

    .features-bar-inner {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      background: var(--white);
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(15, 45, 61, 0.07);
      border: 1px solid var(--border);
      overflow: hidden;
    }

    .feature-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1.15rem 1.1rem;
      border-right: 1px solid var(--border);
    }

    .feature-item:last-child {
      border-right: none;
    }

    .feature-icon {
      flex-shrink: 0;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--green);
    }

    .feature-icon svg {
      width: 26px;
      height: 26px;
    }

    .feature-text h3 {
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 0.15rem;
      line-height: 1.25;
    }

    .feature-text p {
      font-size: 0.75rem;
      line-height: 1.4;
      color: var(--body-text);
      margin: 0;
    }

  /* Community section */
    .community {
      background: transparent;
      padding: 2.75rem 3.5rem 4rem;
    }

    .community-inner {
      max-width: 1100px;
      margin: 0 auto;
      text-align: center;
    }

    .community-header {
      margin-bottom: 1.75rem;
    }

    .community-header h2 {
      font-size: clamp(1.75rem, 3vw, 2.25rem);
      font-weight: 800;
      color: var(--navy);
      letter-spacing: -0.03em;
      margin-bottom: 0.75rem;
    }

    .community-header p {
      font-size: 1.05rem;
      line-height: 1.75;
      color: var(--body-text);
      margin: 0 auto;
    }

    .community-highlight {
      color: var(--accent);
      font-weight: 700;
    }

    .community-chips {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
      gap: 0.85rem;
      margin-bottom: 2.5rem;
    }

    .community-chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.7rem 1.35rem;
      background: var(--white);
      border: 1.5px solid var(--teal);
      border-radius: 999px;
      color: var(--teal);
      text-decoration: none;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }

    .community-chip:hover {
      background: var(--teal);
      color: #fff;
      transform: translateY(-1px);
    }

    .community-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.25rem;
      text-align: left;
    }

    .community-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1.65rem 1.5rem;
    }

    .community-icon {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--green);
      margin-bottom: 0.85rem;
    }

    .community-icon svg {
      width: 28px;
      height: 28px;
    }

    .community-card h3 {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 0.45rem;
      line-height: 1.35;
    }

    .community-card p {
      font-size: 0.85rem;
      line-height: 1.6;
      color: var(--body-text);
      margin: 0;
    }

  /* Animations */
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-16px); }
    }

    @keyframes drift {
      0%, 100% { transform: translate(0, 0) scale(1); }
      33% { transform: translate(30px, -20px) scale(1.05); }
      66% { transform: translate(-20px, 15px) scale(0.95); }
    }

    @keyframes waveLine {
      0%, 100% { transform: scaleX(1); opacity: 1; }
      50% { transform: scaleX(1.08); opacity: 0.75; }
    }

    @keyframes logoFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-4px); }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(28px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes marquee {
      0% { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }

    .hero-content h1 {
      animation: fadeInUp 0.9s ease forwards;
    }

    .hero-content p {
      animation: fadeInUp 0.9s ease 0.15s forwards;
      opacity: 0;
    }

    .hero-image {
      animation: fadeInUp 1s ease 0.2s forwards;
      opacity: 0;
    }

    .reveal {
      opacity: 0;
      transform: translateY(32px);
      transition: opacity 0.7s ease, transform 0.7s ease;
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    @media (prefers-reduced-motion: reduce) {
      .hero-content h1,
      .hero-content p,
      .location-selector,
      .hero-actions,
      .hero-image,
      .hero-image img,
      .partner-logo img,
      .hero-bg span {
        animation: none;
        opacity: 1;
        transform: none;
      }

      .reveal {
        opacity: 1;
        transform: none;
        transition: none;
      }

      .residents-float {
        animation: none;
      }

      .partners-track {
        animation: none;
      }
    }

  /* Delivery section */
    .delivery {
      background: transparent;
      padding: 5rem 3.5rem;
    }

    .delivery-inner {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
      gap: 3.5rem;
      align-items: start;
    }

    .delivery-heading {
      font-size: clamp(2rem, 3.5vw, 2.75rem);
      font-weight: 800;
      line-height: 1.2;
      color: var(--navy);
      letter-spacing: -0.03em;
      margin-bottom: 2.5rem;
    }

    .brand-logo {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .brand-logo img {
      max-width: 180px;
      width: 180px;
      height: auto;
      display: block;
    }

    .brand-logo span {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 1.35rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: #0a6059;
      white-space: nowrap;
    }

    .delivery-text p {
      font-size: 1rem;
      line-height: 1.75;
      color: var(--body-text);
      margin-bottom: 1.25rem;
    }

    .delivery-text p:last-of-type {
      margin-bottom: 2rem;
    }

    .delivery-link {
      color: var(--accent);
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: underline;
      text-underline-offset: 3px;
      transition: color 0.2s;
    }

    .delivery-link:hover {
      color: var(--accent-dark);
    }

  /* Partners section */
    .partners {
      background: transparent;
      padding: 4.5rem 3.5rem 5rem;
    }

    .partners-inner {
      max-width: 1200px;
      margin: 0 auto;
    }

    .partners h2 {
      font-size: clamp(1.75rem, 3vw, 2.25rem);
      font-weight: 800;
      color: var(--navy);
      letter-spacing: -0.03em;
      margin-bottom: 0.75rem;
    }

    .partners-tagline {
      font-size: 1.05rem;
      line-height: 1.65;
      color: var(--body-text);
      max-width: 560px;
      margin: 0 0 2rem;
    }

    .partners-search {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 0.9rem 1.15rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 4px 18px rgba(11, 34, 48, 0.05);
    }

    .partners-search svg {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
      color: var(--teal);
    }

    .partners-search input {
      flex: 1;
      border: none;
      background: transparent;
      font-family: inherit;
      font-size: 0.95rem;
      color: var(--navy);
      outline: none;
      min-width: 0;
    }

    .partners-search input::placeholder {
      color: var(--body-text);
      opacity: 0.75;
    }

    .partners-search-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      border: none;
      border-radius: 8px;
      background: var(--teal-soft);
      color: var(--teal);
      cursor: pointer;
      flex-shrink: 0;
      transition: background 0.15s ease, color 0.15s ease;
    }

    .partners-search-btn:hover {
      background: var(--teal);
      color: #fff;
    }

    .partners-search-btn svg {
      width: 18px;
      height: 18px;
      color: currentColor;
    }

    .partners-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 1.5rem;
      border-bottom: 1px solid var(--border);
      margin-bottom: 1.75rem;
    }

    .partners-tab {
      background: none;
      border: none;
      padding: 0 0 0.85rem;
      font-family: inherit;
      font-size: 0.92rem;
      font-weight: 600;
      color: var(--body-text);
      cursor: pointer;
      position: relative;
      transition: color 0.15s ease;
    }

    .partners-tab:hover {
      color: var(--navy);
    }

    .partners-tab.active {
      color: var(--navy);
    }

    .partners-tab.active::after {
      content: '';
      position: absolute;
      left: 0;
      right: 0;
      bottom: -1px;
      height: 2px;
      background: var(--teal);
      border-radius: 2px 2px 0 0;
    }

    .partners-browse-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 1.15rem;
    }

    .partners-carousel {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 2rem;
    }

    .partners-carousel-viewport {
      flex: 1;
      overflow: hidden;
      min-width: 0;
    }

    .partners-browse-grid {
      display: flex;
      gap: 1rem;
      transition: transform 0.45s ease;
      will-change: transform;
    }

    .partners-carousel-arrow {
      flex-shrink: 0;
      width: 44px;
      height: 44px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1.5px solid var(--teal);
      border-radius: 50%;
      background: var(--white);
      color: var(--teal);
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(11, 34, 48, 0.08);
      transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease, opacity 0.15s ease;
    }

    .partners-carousel-arrow:hover {
      background: var(--teal);
      color: #fff;
      transform: translateY(-1px);
    }

    .partners-carousel-arrow:disabled {
      opacity: 0.35;
      cursor: not-allowed;
      transform: none;
    }

    .partners-carousel-arrow:disabled:hover {
      background: var(--white);
      color: var(--teal);
    }

    .partners-carousel-arrow svg {
      width: 20px;
      height: 20px;
    }

    .pharmacy-browse-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 1.25rem 1.15rem 1rem;
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
      flex: 0 0 calc(25% - 0.75rem);
      min-width: 220px;
      transition: box-shadow 0.15s ease, transform 0.15s ease;
    }

    .pharmacy-browse-card:hover {
      box-shadow: 0 8px 24px rgba(11, 34, 48, 0.08);
      transform: translateY(-2px);
    }

    .pharmacy-browse-card.is-hidden {
      display: none;
    }

    .pharmacy-browse-card:not(.registered-pharmacy-card) {
      display: none !important;
    }

    .partners-carousel.is-empty {
      display: none;
    }

    .partners-empty {
      margin: 18px 0 26px;
      color: var(--body-text);
      text-align: center;
      font-size: 0.95rem;
    }

    @media (prefers-reduced-motion: reduce) {
      .partners-browse-grid {
        transition: none;
      }
    }

    .pharmacy-browse-logo {
      height: 56px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--section-bg);
      border-radius: 8px;
      padding: 0.5rem;
    }

    .pharmacy-browse-logo img {
      max-width: 100%;
      max-height: 44px;
      width: auto;
      height: auto;
      object-fit: contain;
    }

    .pharmacy-browse-logo--placeholder span {
      font-size: 0.95rem;
      font-weight: 800;
      color: var(--teal);
      letter-spacing: 0.05em;
    }

    .pharmacy-browse-card h3 {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--navy);
      line-height: 1.35;
      text-align: center;
      min-height: 2.4em;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .pharmacy-browse-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
      margin-top: auto;
      padding-top: 0.35rem;
      border-top: 1px solid var(--border);
    }

    .pharmacy-browse-location {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--body-text);
    }

    .pharmacy-browse-location svg {
      width: 13px;
      height: 13px;
      flex-shrink: 0;
      color: var(--accent);
    }

    .pharmacy-browse-link {
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--teal);
      text-decoration: none;
      white-space: nowrap;
      transition: color 0.15s ease;
    }

    .pharmacy-browse-link:hover {
      color: var(--accent);
    }

    .partners-view-all {
      display: flex;
      justify-content: center;
    }

    .partners-view-all a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.75rem 1.5rem;
      border: 1.5px solid var(--teal);
      border-radius: 999px;
      color: var(--teal);
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      text-decoration: none;
      transition: background 0.15s ease, color 0.15s ease;
    }

    .partners-view-all a:hover {
      background: var(--teal);
      color: #fff;
    }

    .partners-marquee {
      overflow: hidden;
      mask-image: linear-gradient(to right, transparent, #000 8%, #000 92%, transparent);
    }

    .partners-track {
      display: flex;
      width: max-content;
      animation: marquee 28s linear infinite;
    }

    .partners-marquee:hover .partners-track {
      animation-play-state: paused;
    }

    .partners-slide {
      display: flex;
      gap: 1.25rem;
      padding-right: 1.25rem;
    }

    .partners-grid {
      display: flex;
      gap: 1.25rem;
    }

    .partner-card {
      background: var(--white);
      border-radius: 10px;
      padding: 1.75rem 1.25rem 1.5rem;
      min-height: 160px;
      width: 260px;
      flex: 0 0 260px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      text-align: center;
      transition: box-shadow 0.15s ease;
      border: 1px solid var(--border);
    }

    .partner-card:hover {
      box-shadow: 0 8px 24px rgba(10, 37, 51, 0.08);
    }

    .partner-logo {
      width: 100%;
      max-width: 180px;
      height: 72px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .partner-logo img {
      max-width: 100%;
      max-height: 72px;
      width: auto;
      height: auto;
      object-fit: contain;
      animation: logoFloat 4s ease-in-out infinite;
    }

    .partner-name {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--navy);
      line-height: 1.4;
    }

    .partner-card.featured {
      background: var(--accent-soft);
      border-color: rgba(199, 62, 62, 0.18);
    }

  /* Residents & pharmacies sections */
    .audience {
      padding: 4.5rem 3.5rem;
    }

    .audience--residents {
      background: transparent;
      overflow: hidden;
    }

    .audience-layout {
      max-width: 1100px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      gap: 3rem;
      align-items: center;
    }

    .audience-content {
      text-align: left;
    }

    .audience-layout .audience-tagline {
      margin: 0 0 2rem;
    }

    .audience-visual {
      position: relative;
      min-height: 380px;
    }

    .residents-float {
      position: absolute;
      display: flex;
      align-items: flex-start;
      gap: 0.7rem;
      max-width: 270px;
      padding: 0.9rem 1rem;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 14px;
      box-shadow: 0 10px 28px rgba(11, 34, 48, 0.08);
      animation: residentsFloat 5s ease-in-out infinite;
    }

    .residents-float-icon {
      flex-shrink: 0;
      width: 38px;
      height: 38px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      background: var(--teal-soft);
      color: var(--teal);
    }

    .residents-float-icon svg {
      width: 20px;
      height: 20px;
    }

    .residents-float p {
      font-size: 0.82rem;
      line-height: 1.45;
      font-weight: 600;
      color: var(--navy);
      margin: 0;
    }

    .residents-float--1 {
      top: 0;
      right: 4%;
      animation-delay: 0s;
    }

    .residents-float--2 {
      top: 26%;
      left: 0;
      animation-delay: -1.2s;
    }

    .residents-float--3 {
      top: 52%;
      right: 8%;
      animation-delay: -2.4s;
    }

    .residents-float--4 {
      bottom: 0;
      left: 10%;
      animation-delay: -3.6s;
    }

    @keyframes residentsFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    .audience--pharmacies {
      background: transparent;
      padding-bottom: 5rem;
      overflow: hidden;
    }

    .audience--pharmacies .audience-visual {
      order: -1;
    }

    .audience-inner {
      max-width: 640px;
      margin: 0 auto;
      text-align: center;
    }

    .audience h2 {
      font-size: clamp(1.75rem, 3vw, 2.25rem);
      font-weight: 800;
      color: var(--navy);
      letter-spacing: -0.03em;
      margin-bottom: 0.75rem;
    }

    .audience-tagline {
      font-size: 1.05rem;
      line-height: 1.65;
      color: var(--body-text);
      max-width: 520px;
      margin: 0 auto 2rem;
    }

    .audience-card {
      background: var(--mint);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 2rem 1.75rem;
      display: flex;
      flex-direction: column;
      text-align: left;
    }

    .audience--pharmacies .audience-card {
      background: var(--white);
    }

    .audience-card h3 {
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--green);
      margin-bottom: 0.85rem;
    }

    .audience-card-copy {
      font-size: 0.95rem;
      line-height: 1.65;
      color: var(--body-text);
      margin: 0;
    }

    .audience-card-note {
      font-size: 0.82rem;
      line-height: 1.55;
      color: var(--body-text);
      margin: 0.85rem 0 0;
    }

    .audience-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
      flex: 1;
    }

    .audience-card .btn-hero {
      margin-top: 1.5rem;
      width: 100%;
      font-size: 0.72rem;
      padding: 0.75rem 1.25rem;
    }

    .audience-list li {
      display: flex;
      align-items: flex-start;
      gap: 0.65rem;
      font-size: 0.95rem;
      line-height: 1.55;
      color: var(--body-text);
    }

    .audience-list li::before {
      content: '✓';
      flex-shrink: 0;
      width: 22px;
      height: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--green);
      color: #fff;
      font-size: 0.75rem;
      font-weight: 700;
      border-radius: 50%;
      margin-top: 0.1rem;
    }

  /* How it works section */
    .how-it-works {
      background: transparent;
      padding: 4.5rem 3.5rem 5rem;
    }

    .how-inner {
      max-width: 1100px;
      margin: 0 auto;
    }

    .how-header {
      text-align: center;
      margin-bottom: 3rem;
    }

    .how-header h2 {
      font-size: clamp(1.75rem, 3vw, 2.25rem);
      font-weight: 800;
      color: var(--navy);
      letter-spacing: -0.03em;
      margin-bottom: 0.75rem;
    }

    .how-header p {
      font-size: 1.05rem;
      line-height: 1.65;
      color: var(--body-text);
      max-width: 560px;
      margin: 0 auto;
    }

    .steps-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1.25rem;
      margin-bottom: 4rem;
    }

    .step-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1.75rem 1.35rem;
      text-align: center;
    }

    .step-number {
      width: 36px;
      height: 36px;
      margin: 0 auto 1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--green);
      color: #fff;
      font-size: 0.95rem;
      font-weight: 700;
      border-radius: 50%;
    }

    .step-card h3 {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 0.5rem;
      line-height: 1.35;
    }

    .step-card p {
      font-size: 0.82rem;
      line-height: 1.55;
      color: var(--body-text);
      margin: 0;
    }

    .features-block h3 {
      font-size: clamp(1.35rem, 2.5vw, 1.65rem);
      font-weight: 800;
      color: var(--navy);
      text-align: center;
      letter-spacing: -0.03em;
      margin-bottom: 2rem;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.25rem;
    }

    .feature-box {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 1.5rem 1.35rem;
    }

    .feature-box-icon {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--green);
      margin-bottom: 0.85rem;
    }

    .feature-box-icon svg {
      width: 28px;
      height: 28px;
    }

    .feature-box h4 {
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 0.4rem;
      line-height: 1.35;
    }

    .feature-box p {
      font-size: 0.82rem;
      line-height: 1.55;
      color: var(--body-text);
      margin: 0;
    }

  /* FAQ section */
    .faq {
      background: transparent;
      padding: 5rem 3.5rem 5.5rem;
    }

    .faq-inner {
      max-width: 900px;
      margin: 0 auto;
    }

    .faq h2 {
      font-size: clamp(1.75rem, 3vw, 2.25rem);
      font-weight: 800;
      color: var(--navy);
      text-align: center;
      letter-spacing: -0.03em;
      margin-bottom: 3rem;
    }

    .faq-list {
      list-style: none;
    }

    .faq-item {
      border-bottom: 1px solid var(--border);
    }

    .faq-item:first-child {
      border-top: 1px solid var(--border);
    }

    .faq-question {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.5rem;
      padding: 1.35rem 0;
      background: none;
      border: none;
      cursor: pointer;
      text-align: left;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 600;
      color: var(--navy);
      line-height: 1.4;
    }

    .faq-question:hover {
      opacity: 0.8;
    }

    .faq-icon {
      flex-shrink: 0;
      width: 20px;
      height: 20px;
      position: relative;
      color: var(--navy);
    }

    .faq-icon::before,
    .faq-icon::after {
      content: '';
      position: absolute;
      background: currentColor;
      transition: transform 0.2s ease;
    }

    .faq-icon::before {
      top: 9px;
      left: 2px;
      width: 16px;
      height: 2px;
    }

    .faq-icon::after {
      top: 2px;
      left: 9px;
      width: 2px;
      height: 16px;
    }

    .faq-item.open .faq-icon::after {
      transform: rotate(90deg);
      opacity: 0;
    }

    .faq-answer {
      max-height: 0;
      overflow: hidden;
      opacity: 0;
      padding-bottom: 0;
      font-size: 0.95rem;
      line-height: 1.75;
      color: var(--body-text);
      transition: max-height 0.4s ease, opacity 0.35s ease, padding-bottom 0.4s ease;
    }

    .faq-item.open .faq-answer {
      max-height: 320px;
      opacity: 1;
      padding-bottom: 1.35rem;
    }

  /* Footer */
    .footer {
      background: rgba(231, 244, 241, 0.55);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      color: var(--text);
      padding: 4rem 3.5rem 2rem;
      border-top: 1px solid var(--border);
    }

    .footer-inner {
      max-width: 1200px;
      margin: 0 auto;
    }

    .footer-grid {
      display: grid;
      grid-template-columns: 1.4fr 1fr 1fr;
      gap: 3rem;
      padding-bottom: 3rem;
      border-bottom: 1px solid var(--border);
    }

    .footer-brand {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
    }

    .footer-logo {
      display: inline-block;
      margin-bottom: 1.25rem;
      text-decoration: none;
    }

    .footer-logo img {
      height: 48px;
      width: auto;
      display: block;
    }

    .footer-brand p {
      font-size: 0.95rem;
      line-height: 1.7;
      color: var(--body-text);
      max-width: 320px;
    }

    .footer-col h3 {
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--navy);
      opacity: 0.55;
      margin-bottom: 1.25rem;
    }

    .footer-links {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .footer-links a {
      color: var(--navy);
      text-decoration: none;
      font-size: 0.95rem;
      transition: color 0.2s;
    }

    .footer-links a:hover {
      color: var(--accent);
    }

    .footer-contact {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
    }

    .footer-contact li {
      font-size: 0.95rem;
      color: var(--body-text);
      line-height: 1.5;
    }

    .footer-contact a {
      color: var(--navy);
      text-decoration: none;
      transition: color 0.2s;
    }

    .footer-contact a:hover {
      color: var(--accent);
    }

    .footer-bottom {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding-top: 2rem;
      font-size: 0.85rem;
      color: var(--body-text);
    }

    .footer-bottom a {
      color: var(--navy);
      text-decoration: none;
      transition: color 0.2s;
    }

    .footer-bottom a:hover {
      color: var(--accent);
    }

    @media (max-width: 1024px) {
      header {
        padding: 1.25rem 2rem;
      }

      .hero {
        padding: 0.5rem 2rem 1.5rem;
      }

      .features-bar {
        margin-top: 1rem;
        padding: 0 0.5rem;
      }

      .hero-image img {
        max-width: 560px;
      }

      .features-bar-inner {
        grid-template-columns: repeat(2, 1fr);
      }

      .feature-item:nth-child(2) {
        border-right: none;
      }

      .feature-item:nth-child(1),
      .feature-item:nth-child(2) {
        border-bottom: 1px solid var(--border);
      }

      .delivery {
        padding: 4rem 2rem;
      }

      .delivery-inner {
        gap: 2.5rem;
      }

      .partners {
        padding: 3.5rem 2rem 4rem;
      }

      .partners-browse-grid {
        gap: 0.85rem;
      }

      .pharmacy-browse-card {
        flex: 0 0 calc(50% - 0.45rem);
        min-width: 200px;
      }

      .partners-carousel-arrow {
        width: 40px;
        height: 40px;
      }

      .community {
        padding: 2.5rem 2rem 3.5rem;
      }

      .community-chips {
        gap: 0.65rem;
        margin-bottom: 2rem;
      }

      .community-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .audience {
        padding: 3.5rem 2rem;
      }

      .audience--pharmacies {
        padding-bottom: 4rem;
      }

      .audience-layout {
        grid-template-columns: 1fr;
        gap: 2.5rem;
      }

      .audience--pharmacies .audience-visual {
        order: 0;
      }

      .audience-visual {
        min-height: 340px;
        max-width: 420px;
        margin: 0 auto;
        width: 100%;
      }

      .how-it-works {
        padding: 3.5rem 2rem 4rem;
      }

      .steps-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .features-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .faq {
        padding: 4rem 2rem 4.5rem;
      }

      .footer {
        padding: 3.5rem 2rem 2rem;
      }

      .footer-grid {
        grid-template-columns: 1fr 1fr;
        gap: 2.5rem;
      }

      .footer-brand {
        grid-column: 1 / -1;
      }
    }

    @media (max-width: 860px) {
      .nav {
        grid-template-columns: 1fr auto;
      }

      .nav-links {
        display: none;
      }

      .menu-toggle {
        display: block;
      }

      .nav-actions {
        gap: 0.5rem;
      }

      .hero-body {
        grid-template-columns: 1fr;
        text-align: center;
      }

      .hero-content {
        max-width: none;
        padding-left: 0;
        order: 1;
      }

      .hero-content p {
        margin-left: auto;
        margin-right: auto;
      }

      .location-selector {
        margin-left: auto;
        margin-right: auto;
      }

      .hero-actions {
        justify-content: center;
      }

      .hero-image {
        order: 0;
      }

      .hero-image img {
        max-width: 480px;
      }

      .features-bar-inner {
        grid-template-columns: 1fr;
      }

      .feature-item {
        border-right: none;
        border-bottom: 1px solid var(--border);
      }

      .feature-item:last-child {
        border-bottom: none;
      }

      .delivery-inner {
        grid-template-columns: 1fr;
        gap: 2rem;
      }
    }

    @media (max-width: 480px) {
      header {
        padding: 1rem 1.25rem;
      }

      .hero {
        padding: 0.25rem 1.25rem 1.25rem;
      }

      .features-bar {
        margin-top: 0.85rem;
      }

      .feature-item {
        padding: 1rem 1rem;
      }

      .delivery {
        padding: 3rem 1.25rem;
      }

      .partners {
        padding: 3rem 1.25rem 3.5rem;
      }

      .pharmacy-browse-card {
        flex: 0 0 82%;
        min-width: 0;
      }

      .partners-carousel {
        gap: 0.5rem;
      }

      .partners-carousel-arrow {
        width: 38px;
        height: 38px;
      }

      .partners-tabs {
        gap: 1rem;
      }

      .community {
        padding: 2.25rem 1.25rem 3rem;
      }

      .audience {
        padding: 3rem 1.25rem 3.5rem;
      }

      .audience-visual {
        min-height: auto;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
      }

      .residents-float {
        position: static;
        max-width: none;
        animation: none;
      }

      .how-it-works {
        padding: 3rem 1.25rem 3.5rem;
      }

      .steps-grid,
      .features-grid {
        grid-template-columns: 1fr;
      }

      .faq {
        padding: 3rem 1.25rem 3.5rem;
      }

      .faq-question {
        font-size: 0.95rem;
        padding: 1.15rem 0;
      }

      .footer {
        padding: 3rem 1.25rem 1.5rem;
      }

      .footer-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
        padding-bottom: 2rem;
      }

      .footer-bottom {
        flex-direction: column;
        text-align: center;
        padding-top: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="page">
    <header>
      <nav class="nav">
        <a href="index.php" class="logo">
          <img src="2.png" alt="">
          <span class="logo-text">AddToMar</span>
        </a>

        <ul class="nav-links">
          <li><a href="#partners">Partners</a></li>
          <li><a href="#how-it-works">How It Works</a></li>
          <li><a href="#residents">Residents</a></li>
          <li><a href="#for-pharmacies">Pharmacies</a></li>
          <li><a href="#faq">FAQ</a></li>
          <li><a href="#contact">Contact Us</a></li>
        </ul>

        <div class="nav-actions">
          <a href="login.php" class="login-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            Login
          </a>
          <button class="menu-toggle" aria-label="Open menu">☰</button>
        </div>
      </nav>
    </header>

    <div class="hero-wrap">
    <div class="hero-bg" aria-hidden="true">
      <span></span>
      <span></span>
      <span></span>
    </div>

    <section class="hero">
      <div class="hero-body">
        <div class="hero-content">
          <h1>Find medicine near you.</h1>
          <p>Search nearby pharmacies. Check live stock.
            Reserve online. Pick up when it's ready.</p>
          <div class="location-selector" aria-label="Serving Laoag City, San Nicolas, and Batac">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/>
            </svg>
            <span class="location-areas">Serving Laoag City · San Nicolas · Batac</span>
          </div>
          <div class="hero-actions">
            <a href="login.php" class="btn-hero btn-hero-primary">Browse Medicines</a>
            <a href="login.php#register-pharmacy" class="btn-hero btn-hero-secondary">Run a Pharmacy</a>
          </div>
        </div>
        <div class="hero-image">
          <img src="fp1.png" alt="AddToMar app showing nearby pharmacies and medicines in Laoag City, San Nicolas, and Batac">
        </div>
      </div>

      <div class="features-bar">
        <div class="features-bar-inner">
          <div class="feature-item">
            <div class="feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                <circle cx="12" cy="10" r="3"/>
              </svg>
            </div>
            <div class="feature-text">
              <h3>Search nearby pharmacies</h3>
              <p>Find medicines available at pharmacies in your area.</p>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                <rect x="9" y="3" width="6" height="4" rx="1"/>
                <path d="m9 14 2 2 4-4"/>
              </svg>
            </div>
            <div class="feature-text">
              <h3>Check live stock</h3>
              <p>See medicine availability before you go.</p>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                <path d="M12 18h.01"/>
              </svg>
            </div>
            <div class="feature-text">
              <h3>Pay with GCash</h3>
              <p>Pay a down payment to reserve your order.</p>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 21v-5a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v5"/>
                <path d="M17.774 10.31a1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.451 0 1.12 1.12 0 0 0-1.548 0 2.5 2.5 0 0 1-3.452 0 1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.77-3.248l2.889-4.184A2 2 0 0 1 7 2h10a2 2 0 0 1 1.653.873l2.895 4.192a2.5 2.5 0 0 1-3.774 3.245"/>
                <path d="M4 10.95V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8.05"/>
              </svg>
            </div>
            <div class="feature-text">
              <h3>Pick up locally</h3>
              <p>Collect your order and pay the balance at the pharmacy.</p>
            </div>
          </div>
        </div>
      </div>
    </section>
    </div>

    <section class="community reveal">
      <div class="community-inner">
        <div class="community-header">
          <h2>Built for your community</h2>
          <p>Find trusted pharmacy partners across <span class="community-highlight">Laoag City, San Nicolas, and Batac.</span> Check medicine availability, reserve your order, and pick up at a pharmacy near you.</p>
        </div>
        <div class="community-chips">
          <a href="#partners" class="community-chip">Laoag City</a>
          <a href="#partners" class="community-chip">San Nicolas</a>
          <a href="#partners" class="community-chip">Batac</a>
        </div>
        <div class="community-grid">
          <div class="community-card">
            <div class="community-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 21v-5a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v5"/>
                <path d="M17.774 10.31a1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.451 0 1.12 1.12 0 0 0-1.548 0 2.5 2.5 0 0 1-3.452 0 1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.77-3.248l2.889-4.184A2 2 0 0 1 7 2h10a2 2 0 0 1 1.653.873l2.895 4.192a2.5 2.5 0 0 1-3.774 3.245"/>
                <path d="M4 10.95V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8.05"/>
              </svg>
            </div>
            <h3>Neighborhood pharmacies</h3>
            <p>Work with trusted drugstores already serving your community — not distant warehouses.</p>
          </div>
          <div class="community-card">
            <div class="community-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </div>
            <h3>Families &amp; caregivers</h3>
            <p>Search, reserve, and track medicine orders for yourself or the people you care for.</p>
          </div>
          <div class="community-card">
            <div class="community-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                <circle cx="12" cy="10" r="3"/>
              </svg>
            </div>
            <h3>Local first</h3>
            <p>Built around real pharmacies in your area, with live stock and pickup close to home.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="delivery reveal">
      <div class="delivery-inner">
        <div class="delivery-left">
          <h2 class="delivery-heading">Order prescription medicines for pickup</h2>
          <div class="brand-logo">
            <img src="2.png" alt="AddToMar">
            <span>AddToMar</span>
          </div>
        </div>
        <div class="delivery-text">
          <p>Reserve prescription medicines online and pick them up at your chosen pharmacy partner when your order is ready.</p>
          <p>This feature saves time, energy and money spent on waiting at the pharmacy. Let our partner pharmacy prepare your prescription order, so you can focus on getting well or helping your family member get better.</p>
          <p>Order prescription medicine from any of our pharmacy partners.</p>
          <a href="login.php" class="delivery-link">Download the app, register and learn more.</a>
        </div>
      </div>
    </section>

    <section class="partners reveal" id="partners">
      <div class="partners-inner">
        <h2>Pharmacies near you</h2>
        <p class="partners-tagline">Real-time medicine availability from pharmacies in your area.</p>

        <form class="partners-search" role="search" aria-label="Search pharmacies">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
          </svg>
          <input type="search" id="partners-search-input" placeholder="Search for a medicine or pharmacy..." aria-label="Search for a medicine or pharmacy">
          <button class="partners-search-btn" type="submit" aria-label="Search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M5 12h14"/>
              <path d="m12 5 7 7-7 7"/>
            </svg>
          </button>
        </form>

        <div class="partners-tabs" role="tablist" aria-label="Filter by city">
          <button class="partners-tab active" type="button" role="tab" aria-selected="true" data-city="laoag">Laoag City</button>
          <button class="partners-tab" type="button" role="tab" aria-selected="false" data-city="san-nicolas">San Nicolas</button>
          <button class="partners-tab" type="button" role="tab" aria-selected="false" data-city="batac">Batac</button>
        </div>

        <h3 class="partners-browse-title">Popular nearby pharmacies</h3>

        <div data-live-region="public-pharmacies" data-live-keys="pharmacies">
        <?php if (!$registeredPharmacies): ?>
        <p class="partners-empty">No pharmacies are registered yet.</p>
        <?php endif; ?>
        <div class="partners-carousel<?= !$registeredPharmacies ? ' is-empty' : '' ?>" id="partners-carousel">
          <button class="partners-carousel-arrow partners-carousel-arrow--prev" type="button" aria-label="Previous pharmacies">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M15 18l-6-6 6-6"/>
            </svg>
          </button>
          <div class="partners-carousel-viewport" id="partners-carousel-viewport">
            <div class="partners-browse-grid<?= $registeredPharmacies ? ' has-registered-pharmacies' : '' ?>" id="partners-browse-grid">
              <?php foreach ($registeredPharmacies as $registeredPharmacy):
                  $registeredCity = $homePharmacyCity($registeredPharmacy);
                  $registeredLogo = pharmacy_accounts_public_logo_url($registeredPharmacy);
                  if ($registeredLogo === '') {
                      $registeredLogo = '2.png';
                  }
                  $registeredName = trim((string) ($registeredPharmacy['pharmacy_name'] ?? '')) ?: 'Registered Pharmacy';
              ?>
              <article class="pharmacy-browse-card registered-pharmacy-card" data-city="<?= htmlspecialchars($registeredCity['key'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars(strtolower($registeredName), ENT_QUOTES, 'UTF-8') ?>">
                <div class="pharmacy-browse-logo">
                  <img src="<?= htmlspecialchars($registeredLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($registeredName, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <h3><?= htmlspecialchars($registeredName, ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    <?= htmlspecialchars($registeredCity['label'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <?php endforeach; ?>
              <?php if (false): ?>
              <article class="pharmacy-browse-card" data-city="laoag" data-name="mariano marcos hospital pharmacy">
                <div class="pharmacy-browse-logo">
                  <img src="images/logos/mariano-marcos.svg" alt="">
                </div>
                <h3>Mariano Marcos</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Laoag
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <?php endif; ?>
              <article class="pharmacy-browse-card" data-city="laoag" data-name="mercury drug laoag branch">
                <div class="pharmacy-browse-logo">
                  <img src="images/logos/mercury-drug.svg" alt="">
                </div>
                <h3>Mercury Drug</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Laoag
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="laoag" data-name="watson's drugstore laoag">
                <div class="pharmacy-browse-logo">
                  <img src="images/logos/watsons.svg" alt="">
                </div>
                <h3>Watson's Drugstore</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Laoag
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="laoag" data-name="generika drugstore laoag">
                <div class="pharmacy-browse-logo pharmacy-browse-logo--placeholder"><span>GD</span></div>
                <h3>Generika Drugstore</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Laoag
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="laoag" data-name="southstar drug laoag">
                <div class="pharmacy-browse-logo pharmacy-browse-logo--placeholder"><span>SD</span></div>
                <h3>Southstar Drug</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Laoag
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="san-nicolas" data-name="mercury drug san nicolas">
                <div class="pharmacy-browse-logo">
                  <img src="images/logos/mercury-drug.svg" alt="">
                </div>
                <h3>Mercury Drug</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    San Nicolas
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="san-nicolas" data-name="generika san nicolas">
                <div class="pharmacy-browse-logo pharmacy-browse-logo--placeholder"><span>GD</span></div>
                <h3>Generika Drugstore</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    San Nicolas
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="san-nicolas" data-name="ilocos norte pharmacy san nicolas">
                <div class="pharmacy-browse-logo pharmacy-browse-logo--placeholder"><span>IN</span></div>
                <h3>Ilocos Norte Pharmacy</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    San Nicolas
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="san-nicolas" data-name="community pharmacy san nicolas">
                <div class="pharmacy-browse-logo pharmacy-browse-logo--placeholder"><span>CP</span></div>
                <h3>Community Pharmacy</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    San Nicolas
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="batac" data-name="batac central pharmacy">
                <div class="pharmacy-browse-logo">
                  <img src="images/logos/batac-central.svg" alt="">
                </div>
                <h3>Batac Central</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Batac
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="batac" data-name="mercury drug batac branch">
                <div class="pharmacy-browse-logo">
                  <img src="images/logos/mercury-drug.svg" alt="">
                </div>
                <h3>Mercury Drug</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Batac
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="batac" data-name="watson's drugstore batac">
                <div class="pharmacy-browse-logo">
                  <img src="images/logos/watsons.svg" alt="">
                </div>
                <h3>Watson's Drugstore</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Batac
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="batac" data-name="northstar drug batac">
                <div class="pharmacy-browse-logo pharmacy-browse-logo--placeholder"><span>ND</span></div>
                <h3>Northstar Drug</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Batac
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="batac" data-name="divine mercy pharmacy batac">
                <div class="pharmacy-browse-logo pharmacy-browse-logo--placeholder"><span>DM</span></div>
                <h3>Divine Mercy Pharmacy</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Batac
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
              <article class="pharmacy-browse-card" data-city="batac" data-name="rose pharmacy batac">
                <div class="pharmacy-browse-logo pharmacy-browse-logo--placeholder"><span>RP</span></div>
                <h3>Rose Pharmacy</h3>
                <div class="pharmacy-browse-footer">
                  <span class="pharmacy-browse-location">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                    Batac
                  </span>
                  <a href="login.php" class="pharmacy-browse-link">View →</a>
                </div>
              </article>
            </div>
          </div>
          <button class="partners-carousel-arrow partners-carousel-arrow--next" type="button" aria-label="Next pharmacies">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </button>
        </div>
        </div>

        <div class="partners-view-all">
          <a href="login.php">View all pharmacies</a>
        </div>
      </div>
    </section>

    <section class="audience audience--residents reveal" id="residents">
      <div class="audience-layout">
        <div class="audience-content">
          <h2>For Residents</h2>
          <div class="audience-card">
            <h3>Find what you need, close to home</h3>
            <p class="audience-card-copy">Search for medicines, check availability at nearby pharmacies, and reserve your order for pickup.</p>
            <p class="audience-card-note">Available across Laoag City, San Nicolas, and Batac.</p>
            <a href="login.php" class="btn-hero btn-hero-primary">Find Pharmacies</a>
          </div>
        </div>
        <div class="audience-visual" aria-hidden="true">
          <div class="residents-float residents-float--1">
            <span class="residents-float-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <path d="m21 21-4.35-4.35"/>
              </svg>
            </span>
            <p>Search medicines</p>
          </div>
          <div class="residents-float residents-float--2">
            <span class="residents-float-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                <circle cx="12" cy="10" r="3"/>
              </svg>
            </span>
            <p>Find nearby pharmacies</p>
          </div>
          <div class="residents-float residents-float--3">
            <span class="residents-float-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </span>
            <p>Check live availability</p>
          </div>
          <div class="residents-float residents-float--4">
            <span class="residents-float-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                <path d="M12 18h.01"/>
              </svg>
            </span>
            <p>Reserve with GCash</p>
          </div>
        </div>
      </div>
    </section>

    <section class="audience audience--pharmacies reveal" id="for-pharmacies">
      <div class="audience-layout">
        <div class="audience-content">
          <h2>For Pharmacies</h2>
          <div class="audience-card">
            <h3>Grow your local pharmacy presence</h3>
            <p class="audience-card-copy">Connect with residents looking for medicines in your area. Keep your inventory updated, receive reservations, and prepare orders for pickup.</p>
            <p class="audience-card-note">Serve customers across Laoag City, San Nicolas, and Batac.</p>
            <a href="login.php#register-pharmacy" class="btn-hero btn-hero-secondary">Run Pharmacies</a>
          </div>
        </div>
        <div class="audience-visual" aria-hidden="true">
          <div class="residents-float residents-float--1">
            <span class="residents-float-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                <path d="M3.3 7 12 12l8.7-5"/>
                <path d="M12 22V12"/>
              </svg>
            </span>
            <p>Manage medicine inventory</p>
          </div>
          <div class="residents-float residents-float--2">
            <span class="residents-float-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
              </svg>
            </span>
            <p>Receive customer orders</p>
          </div>
          <div class="residents-float residents-float--3">
            <span class="residents-float-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                <path d="M12 18h.01"/>
              </svg>
            </span>
            <p>Verify GCash payments</p>
          </div>
          <div class="residents-float residents-float--4">
            <span class="residents-float-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 3v18h18"/>
                <path d="M7 14l4-4 4 4 6-6"/>
              </svg>
            </span>
            <p>Update live availability</p>
          </div>
        </div>
      </div>
    </section>

    <section class="how-it-works reveal" id="how-it-works">
      <div class="how-inner">
        <div class="how-header">
          <h2>How it works</h2>
          <p>From search to pickup — order medicines online in a few simple steps.</p>
        </div>

        <div class="steps-grid">
          <div class="step-card">
            <div class="step-number">1</div>
            <h3>Search &amp; browse</h3>
            <p>Find medicines by name or browse stock at nearby partner pharmacies.</p>
          </div>
          <div class="step-card">
            <div class="step-number">2</div>
            <h3>Add to cart</h3>
            <p>Select your items and upload a prescription photo when required.</p>
          </div>
          <div class="step-card">
            <div class="step-number">3</div>
            <h3>Pay with GCash</h3>
            <p>Pay a down payment through GCash to reserve your order at the pharmacy.</p>
          </div>
          <div class="step-card">
            <div class="step-number">4</div>
            <h3>Pick up</h3>
            <p>Get notified when ready, then collect and pay the balance at the pharmacy.</p>
          </div>
        </div>

        <div class="features-block">
          <h3>Features</h3>
          <div class="features-grid">
            <div class="feature-box">
              <div class="feature-box-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
              </div>
              <h4>Pharmacy locator</h4>
              <p>View partner pharmacies on a map, sorted by distance from you.</p>
            </div>
            <div class="feature-box">
              <div class="feature-box-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="11" cy="11" r="8"/>
                  <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
              </div>
              <h4>Live stock search</h4>
              <p>Check medicine availability in real time before you order.</p>
            </div>
            <div class="feature-box">
              <div class="feature-box-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                  <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
              </div>
              <h4>GCash down payment</h4>
              <p>Secure partial payment via GCash to confirm your reservation.</p>
            </div>
            <div class="feature-box">
              <div class="feature-box-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                  <polyline points="14 2 14 8 20 8"/>
                  <line x1="16" y1="13" x2="8" y2="13"/>
                  <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
              </div>
              <h4>Prescription upload</h4>
              <p>Submit prescription photos online for pharmacy verification.</p>
            </div>
            <div class="feature-box">
              <div class="feature-box-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
              </div>
              <h4>Order tracking</h4>
              <p>Follow your order from confirmation through to pickup.</p>
            </div>
            <div class="feature-box">
              <div class="feature-box-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
              </div>
              <h4>Trusted partners</h4>
              <p>Order only from licensed pharmacies in the AddToMar network.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="faq reveal" id="faq">
      <div class="faq-inner">
        <h2>Frequently Asked Questions (FAQs)</h2>
        <ul class="faq-list">
          <li class="faq-item">
            <button class="faq-question" type="button" aria-expanded="false">
              <span>Can I order from a pharmacy near me?</span>
              <span class="faq-icon" aria-hidden="true"></span>
            </button>
            <div class="faq-answer">
              Yes. Search for medicines or browse partner pharmacies in your area to see live stock, then place your order for pickup at the pharmacy you choose.
            </div>
          </li>
          <li class="faq-item">
            <button class="faq-question" type="button" aria-expanded="false">
              <span>Can someone else pick up my order?</span>
              <span class="faq-icon" aria-hidden="true"></span>
            </button>
            <div class="faq-answer">
              Yes. A family member or authorized person can pick up your order at the pharmacy. They may need to present valid ID and your order confirmation.
            </div>
          </li>
          <li class="faq-item">
            <button class="faq-question" type="button" aria-expanded="false">
              <span>Can I pay using GCash?</span>
              <span class="faq-icon" aria-hidden="true"></span>
            </button>
            <div class="faq-answer">
              Yes. You can pay a down payment through GCash at checkout to reserve your order. The pharmacy verifies your payment before preparing your items.
            </div>
          </li>
          <li class="faq-item">
            <button class="faq-question" type="button" aria-expanded="false">
              <span>Do I need a prescription?</span>
              <span class="faq-icon" aria-hidden="true"></span>
            </button>
            <div class="faq-answer">
              It depends on the medicine. Over-the-counter items usually don't require one. Prescription medicines will prompt you to upload a photo of your prescription before checkout.
            </div>
          </li>
          <li class="faq-item">
            <button class="faq-question" type="button" aria-expanded="false">
              <span>What happens if my pharmacy doesn't have the medicine?</span>
              <span class="faq-icon" aria-hidden="true"></span>
            </button>
            <div class="faq-answer">
              If an item is unavailable, the pharmacy may suggest an alternative, help you find another partner pharmacy with stock, or remove that item from your order. You'll be notified before pickup is confirmed.
            </div>
          </li>
          <li class="faq-item">
            <button class="faq-question" type="button" aria-expanded="false">
              <span>When do I pay the remaining balance?</span>
              <span class="faq-icon" aria-hidden="true"></span>
            </button>
            <div class="faq-answer">
              You pay the remaining balance in person at the pharmacy when you collect your order, after your GCash down payment has been verified.
            </div>
          </li>
        </ul>
      </div>
    </section>

    <footer class="footer" id="contact">
      <div class="footer-inner">
        <div class="footer-grid">
          <div class="footer-brand">
            <a href="index.php" class="footer-logo">
              <img src="2.png" alt="AddToMar">
            </a>
            <p>Order medicines online from trusted pharmacy partners near you. Search, reserve with GCash, and pick up when ready.</p>
          </div>
          <div class="footer-col">
            <h3>Quick Links</h3>
            <ul class="footer-links">
              <li><a href="#partners">Partners</a></li>
              <li><a href="#how-it-works">How It Works</a></li>
              <li><a href="#residents">Residents</a></li>
              <li><a href="#for-pharmacies">Pharmacies</a></li>
              <li><a href="#faq">FAQ</a></li>
              <li><a href="#contact">Contact Us</a></li>
            </ul>
          </div>
          <div class="footer-col">
            <h3>Contact</h3>
            <ul class="footer-contact">
              <li><a href="mailto:support@addtomar.com">support@addtomar.com</a></li>
            </ul>
          </div>
        </div>
        <div class="footer-bottom">
          <span>&copy; 2026 AddToMar. All rights reserved.</span>
          <a href="#">Privacy Policy</a>
        </div>
      </div>
    </footer>
  </div>

  <script>
    document.querySelectorAll('.reveal').forEach(function (el) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
      observer.observe(el);
    });

    document.querySelectorAll('.faq-question').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var item = btn.parentElement;
        var isOpen = item.classList.contains('open');

        document.querySelectorAll('.faq-item').forEach(function (el) {
          el.classList.remove('open');
          el.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
        });

        if (!isOpen) {
          item.classList.add('open');
          btn.setAttribute('aria-expanded', 'true');
        }
      });
    });

    (function () {
      var tabs = document.querySelectorAll('.partners-tab');
      var searchInput = document.getElementById('partners-search-input');
      var searchForm = document.querySelector('.partners-search');
      var carousel = document.getElementById('partners-carousel');
      var viewport = document.getElementById('partners-carousel-viewport');
      var track = document.getElementById('partners-browse-grid');
      var prevBtn = document.querySelector('.partners-carousel-arrow--prev');
      var nextBtn = document.querySelector('.partners-carousel-arrow--next');
      var activeCity = 'laoag';
      var currentIndex = 0;
      var autoTimer = null;

      function getCards() {
        return document.querySelectorAll('.pharmacy-browse-card');
      }

      function getVisibleCards() {
        return Array.prototype.filter.call(getCards(), function (card) {
          return !card.classList.contains('is-hidden');
        });
      }

function getStepSize() {
        var visibleCards = getVisibleCards();
        if (!visibleCards.length) {
          return 0;
        }
        var card = visibleCards[0];
        track = document.getElementById('partners-browse-grid');
        if (!track) {
          return 0;
        }
        var styles = window.getComputedStyle(track);
        var gap = parseFloat(styles.columnGap || styles.gap || '16');
        return card.offsetWidth + gap;
      }

      function getCardsPerView() {
        var step = getStepSize();
        if (!step || !viewport) {
          return 1;
        }
        return Math.max(1, Math.floor(viewport.clientWidth / step));
      }

      function getMaxIndex() {
        var visibleCards = getVisibleCards();
        return Math.max(0, visibleCards.length - getCardsPerView());
      }

      function updateArrows() {
        prevBtn = document.querySelector('.partners-carousel-arrow--prev');
        nextBtn = document.querySelector('.partners-carousel-arrow--next');
        var maxIndex = getMaxIndex();

        if (prevBtn) {
          prevBtn.disabled = currentIndex <= 0;
        }
        if (nextBtn) {
          nextBtn.disabled = currentIndex >= maxIndex;
        }
      }

      function moveToIndex(index, animate) {
        var maxIndex = getMaxIndex();

        if (!getVisibleCards().length) {
          if (track) {
            track.style.transform = 'translateX(0)';
          }
          currentIndex = 0;
          updateArrows();
          return;
        }

        currentIndex = Math.max(0, Math.min(index, maxIndex));

        if (track) {
          track.style.transition = animate === false ? 'none' : '';
          track.style.transform = 'translateX(-' + (currentIndex * getStepSize()) + 'px)';
        }

        updateArrows();
      }

      function moveBy(direction) {
        var maxIndex = getMaxIndex();
        if (direction > 0 && currentIndex >= maxIndex) {
          moveToIndex(0);
          return;
        }
        if (direction < 0 && currentIndex <= 0) {
          moveToIndex(maxIndex);
          return;
        }
        moveToIndex(currentIndex + direction);
      }

      function startAutoScroll() {
        stopAutoScroll();
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
          return;
        }
        autoTimer = window.setInterval(function () {
          if (getMaxIndex() <= 0) {
            return;
          }
          moveBy(1);
        }, 3500);
      }

      function stopAutoScroll() {
        if (autoTimer) {
          window.clearInterval(autoTimer);
          autoTimer = null;
        }
      }

      function filterPharmacies() {
        var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        track = document.getElementById('partners-browse-grid');
        viewport = document.getElementById('partners-carousel-viewport');
        carousel = document.getElementById('partners-carousel');

        getCards().forEach(function (card) {
          var city = card.getAttribute('data-city');
          var name = card.getAttribute('data-name') || '';
          var matchesCity = city === activeCity;
          var matchesSearch = !query || name.indexOf(query) !== -1;
          card.classList.toggle('is-hidden', !(matchesCity && matchesSearch));
        });

        moveToIndex(0, false);
        startAutoScroll();
      }

      filterPharmacies();
      window.__partnersFilterPharmacies = filterPharmacies;
      document.addEventListener('livesync:applied', function () {
        track = document.getElementById('partners-browse-grid');
        viewport = document.getElementById('partners-carousel-viewport');
        carousel = document.getElementById('partners-carousel');
        filterPharmacies();
      });

      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          tabs.forEach(function (el) {
            el.classList.remove('active');
            el.setAttribute('aria-selected', 'false');
          });
          tab.classList.add('active');
          tab.setAttribute('aria-selected', 'true');
          activeCity = tab.getAttribute('data-city');
          filterPharmacies();
        });
      });

      if (searchInput) {
        searchInput.addEventListener('input', filterPharmacies);
      }

      if (searchForm) {
        searchForm.addEventListener('submit', function (event) {
          event.preventDefault();
          filterPharmacies();
        });
      }

      document.addEventListener('click', function (event) {
        if (event.target.closest('.partners-carousel-arrow--prev')) {
          event.preventDefault();
          moveBy(-1);
          startAutoScroll();
        }
        if (event.target.closest('.partners-carousel-arrow--next')) {
          event.preventDefault();
          moveBy(1);
          startAutoScroll();
        }
      });

      var partnersInner = document.querySelector('.partners-inner');
      if (partnersInner) {
        partnersInner.addEventListener('mouseenter', stopAutoScroll);
        partnersInner.addEventListener('mouseleave', startAutoScroll);
        partnersInner.addEventListener('focusin', stopAutoScroll);
        partnersInner.addEventListener('focusout', startAutoScroll);
      }

      window.addEventListener('resize', function () {
        moveToIndex(currentIndex, false);
      });
    })();
  </script>
<?php live_sync_render_script('public'); ?>
</body>
</html>
