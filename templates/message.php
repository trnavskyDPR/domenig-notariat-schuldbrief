<?php
/**
 * Einfache Meldungsseite (Fehler, abgelaufene Bestätigung).
 *
 * @var string $title @var string $text
 * @var callable $t @var callable $th @var callable $e
 * @var \Schuldbrief\I18n $i18n @var \Schuldbrief\Config $config
 */
$phone = $config->contactPhone();
$tel = preg_replace('/[^\d+]/', '', $phone);
?>
<div class="card">
  <div class="card__head">
    <h1 tabindex="-1" data-autofocus><?= $t($title) ?></h1>
  </div>
  <div class="block">
    <p><?= $th($text, ['phone' => '<a href="tel:' . $e($tel) . '">' . $e($phone) . '</a>']) ?></p>
    <p><a class="btn btn--secondary" href="?lang=<?= $e($i18n->lang) ?>"><?= $t('back') ?></a></p>
  </div>
</div>
