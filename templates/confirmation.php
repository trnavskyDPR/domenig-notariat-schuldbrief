<?php
/**
 * Bestätigungsseite. Zeigt bewusst keine Antragsdaten (die URL enthält nur Sprache und Versandstatus).
 *
 * @var bool $mailFailed
 * @var callable $t @var callable $th @var callable $e
 * @var \Schuldbrief\I18n $i18n @var \Schuldbrief\Config $config
 */
$phone = $config->contactPhone();
$tel = preg_replace('/[^\d+]/', '', $phone);
$mail = $config->contactEmail();
$text = match (true) {
    $config->isMock() => 'confirm.mock',
    !empty($mailFailed) => 'confirm.mail_failed',
    $config->mode() === 'test' => 'confirm.test',
    default => 'confirm.text',
};
?>
<div class="card">
  <div class="card__head card__head--success">
    <h1 tabindex="-1" data-autofocus><?= $t('confirm.title') ?></h1>
    <p><?= $t($text) ?></p>
  </div>
  <div class="block">
    <p><?= $t('confirm.next') ?></p>

    <div class="info">
      <h2 class="info__title"><?= $t('fees.heading') ?></h2>
      <p><?= $t('fees.text') ?></p>
    </div>

    <p class="spaced"><?= $th('confirm.questions', [
        'phone' => '<a href="tel:' . $e($tel) . '">' . $e($phone) . '</a>',
        'email' => '<a href="mailto:' . $e($mail) . '">' . $e($mail) . '</a>',
    ]) ?></p>

    <p><a class="btn btn--secondary" href="?lang=<?= $e($i18n->lang) ?>"><?= $t('confirm.again') ?></a></p>
  </div>
</div>
