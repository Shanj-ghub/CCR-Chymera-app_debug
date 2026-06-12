<?php
// header.php - site header (include this at top of each page)
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/x-icon" href="/chymera/images/favicon.ico">
  <title>CHyMErA Design - National Cancer Institute</title>

  <!-- FontAwesome (for icons) -->
  <link rel="stylesheet" href="/chymera/fontawesome/css/fontawesome.min.css"/>
  <link rel="stylesheet" href="/chymera/fontawesome/css/brands.min.css"/>
  <link rel="stylesheet" href="/chymera/fontawesome/css/solid.min.css">

  <!-- Main CSS -->
  <link rel="stylesheet" href="/chymera/css/styles.css" />
  <link rel="stylesheet" href="/chymera/css/chymera-search.css">

  <!-- Montserrat font (local) -->
  <style>
    /* Update path if needed; using forward slashes */
    @font-face {
      font-family: 'MontserratCustom';
      src: url('/chymera/themes/custom/ccr/assets/fonts/custom/montserrat/Montserrat-Regular.woff2') format('woff2'),
           url('/chymera/themes/custom/ccr/assets/fonts/custom/montserrat/Montserrat-Regular.woff') format('woff');
      font-weight: 400;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: 'MontserratCustom';
      src: url('/chymera/themes/custom/ccr/assets/fonts/custom/montserrat/Montserrat-Bold.woff2') format('woff2'),
           url('/chymera/themes/custom/ccr/assets/fonts/custom/montserrat/Montserrat-Bold.woff') format('woff');
      font-weight: 700;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: 'MontserratCustom';
      src: url('/chymera/themes/custom/ccr/assets/fonts/custom/montserrat/Montserrat-Italic.woff2') format('woff2'),
           url('/chymera/themes/custom/ccr/assets/fonts/custom/montserrat/Montserrat-Italic.woff') format('woff');
      font-weight: 400;
      font-style: italic;
      font-display: swap;
    }
  </style>

</head>
<body>
  <header class="site-header">
    <div class="header-top">
      <div class="container header-inner">
        <div class="logo">
          <a href="/"><img src="/chymera/images/SiteIcon.svg" alt="NCI logo" /></a>
        </div>

        <div class="site-title">
          <h1>CHyMErA Combinatorial Genome Editing System</h1>
        </div>

      </div>
    </div>
  </header>

  <div class="page-wrap container">
    <?php if (empty($hideSidebar)): ?>
        <aside class="sidebar-wrap">
            <?php include __DIR__ . '/sidebar.php'; ?>
        </aside>
    <?php endif; ?>

    <main class="content-wrap">
