<?php
/**
 * @var string $content
 * @var callable $t @var callable $th @var callable $e @var callable $asset
 * @var \Schuldbrief\I18n $i18n @var \Schuldbrief\Config $config
 */
use Schuldbrief\I18n;

$langParams = $langParams ?? [];
$langNames = ['de' => 'Deutsch', 'fr' => 'Français', 'en' => 'English'];
$phone = $config->contactPhone();
$tel = preg_replace('/[^\d+]/', '', $phone);
$mode = $config->mode();
?>
<!doctype html>
<html lang="<?= $e($i18n->htmlLang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $t('app.title') ?> – <?= $t('org.name') ?></title>
<?php if (!empty($noindex)): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif ?>
<meta name="description" content="<?= $t('app.intro') ?>">
<link rel="icon" href="<?= $asset('img/favicon.png') ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= $asset('img/apple-touch-icon.png') ?>">
<link rel="preload" href="assets/fonts/barlow-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $asset('css/app.css') ?>">
<script src="<?= $asset('js/app.js') ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main"><?= $t('a11y.skip') ?></a>
<?php if ($mode !== 'live'): ?>
<p class="test-banner"><?= $t('test.banner.' . $mode) ?></p>
<?php endif ?>
<header class="site-header">
  <nav class="lang-switch" aria-label="<?= $t('lang.label') ?>">
    <ul>
      <?php foreach (I18n::LANGS as $l): ?>
      <li><a href="?<?= $e(http_build_query(['lang' => $l] + $langParams)) ?>" lang="<?= $l ?>" hreflang="<?= $l ?>"
        title="<?= $e($langNames[$l]) ?>"<?= $l === $i18n->lang ? ' aria-current="true"' : '' ?>><?= strtoupper($l) ?></a></li>
      <?php endforeach ?>
    </ul>
  </nav>
  <a class="logo-link" href="?lang=<?= $e($i18n->lang) ?>">
    <img class="logo" src="<?= $asset('img/logo-dpn.png') ?>" alt="<?= $t('org.name') ?>" width="1134" height="193">
  </a>
</header>
<main id="main" class="page" tabindex="-1">
<?= $content ?>
</main>
<footer class="site-footer">
  <p>
    <strong><?= $t('org.name') ?></strong><br>
    <?= $e(str_replace(', ', ' · ', $config->officeAddress())) ?><br>
    <a href="tel:<?= $e($tel) ?>"><?= $e($phone) ?></a> ·
    <a href="mailto:<?= $e($config->contactEmail()) ?>"><?= $e($config->contactEmail()) ?></a> ·
    <a href="<?= $e($config->privacyUrl($i18n->lang)) ?>" rel="noopener"><?= $t('footer.privacy') ?></a>
  </p>
</footer>
</body>
</html>
