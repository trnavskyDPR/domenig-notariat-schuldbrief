<?php
/**
 * Antragsformular. Alle Felder sind Pflicht.
 *
 * @var array<string, string> $values @var array $errors
 * @var callable $t @var callable $th @var callable $e
 * @var \Schuldbrief\I18n $i18n @var \Schuldbrief\Config $config
 */
use Schuldbrief\Order\Options;
use Schuldbrief\Order\OrderValidator;

$val = static fn (string $f): string => $e($values[$f] ?? '');
$has = static fn (string $f): bool => isset($errors[$f]);
$fieldClass = static fn (string ...$fields): string => array_filter($fields, $has) ? ' has-error' : '';
// id, name, maxlength, aria-describedby (Hinweis + Fehler) und aria-invalid für ein Feld
$aria = static function (string $f, ?string $hint = null) use ($has): string {
    $ids = array_filter([$hint, $has($f) ? 'err-' . $f : null]);
    $max = OrderValidator::MAX_LENGTH[$f] ?? null;

    return ' id="f-' . $f . '" name="' . $f . '"'
        . ($max !== null ? ' maxlength="' . $max . '"' : '')
        . ($ids ? ' aria-describedby="' . implode(' ', $ids) . '"' : '')
        . ($has($f) ? ' aria-invalid="true"' : '');
};
$error = static function (string $f) use ($errors, $t): string {
    if (!isset($errors[$f])) {
        return '';
    }

    return '<p class="field__error" id="err-' . $f . '"><span class="sr-only">' . $t('error.prefix') . ' </span>'
        . $t($errors[$f][0], $errors[$f][1]) . '</p>';
};
$req = '<span class="req" aria-hidden="true">*</span>';

$vorwahl = $values['vorwahl'] ?? Options::DIAL_DEFAULT;
$privacyLink = '<a href="' . $e($config->privacyUrl($i18n->lang)) . '" target="_blank" rel="noopener">'
    . $t('field.datenschutz.link') . '<span class="sr-only"> ' . $t('a11y.new_window') . '</span></a>';
?>
<div class="card">
  <div class="card__head">
    <h1><?= $t('app.title') ?></h1>
    <p><?= $t('app.intro') ?></p>
    <p class="card__note"><?= $th('form.required_note', ['star' => $req]) ?></p>
  </div>

  <?php if ($errors): ?>
  <div class="error-summary" id="error-summary" role="alert" tabindex="-1">
    <h2><?= $t('errors.summary') ?></h2>
    <ul>
      <?php foreach ($errors as $field => [$key, $params]): ?>
      <li><a href="#f-<?= $e($field) ?>"><?= $t($key, $params) ?></a></li>
      <?php endforeach ?>
    </ul>
  </div>
  <?php endif ?>

  <form class="form" id="f-form" method="post" action="?lang=<?= $e($i18n->lang) ?>" novalidate data-form>
    <input type="hidden" name="lang" value="<?= $e($i18n->lang) ?>">

    <section class="block" aria-labelledby="h-property">
      <h2 id="h-property"><?= $t('section.property') ?></h2>
      <div class="field field--narrow<?= $fieldClass('grundbuchblatt') ?>">
        <label for="f-grundbuchblatt"><?= $t('field.grundbuchblatt') ?> <?= $req ?></label>
        <input type="text"<?= $aria('grundbuchblatt', 'hint-grundbuchblatt') ?> value="<?= $val('grundbuchblatt') ?>" autocomplete="off" spellcheck="false" required>
        <p class="hint" id="hint-grundbuchblatt"><?= $t('field.grundbuchblatt.hint') ?></p>
        <?= $error('grundbuchblatt') ?>
      </div>
      <div class="field<?= $fieldClass('adresse') ?>">
        <label for="f-adresse"><?= $t('field.adresse') ?> <?= $req ?></label>
        <input type="text"<?= $aria('adresse', 'hint-adresse') ?> value="<?= $val('adresse') ?>" autocomplete="off" required>
        <p class="hint" id="hint-adresse"><?= $t('field.adresse.hint') ?></p>
        <?= $error('adresse') ?>
      </div>
      <div class="field<?= $fieldClass('rang') ?>">
        <label for="f-rang"><?= $t('field.rang') ?> <?= $req ?></label>
        <input type="text"<?= $aria('rang', 'hint-rang') ?> value="<?= $val('rang') ?>" autocomplete="off" required>
        <p class="hint" id="hint-rang"><?= $t('field.rang.hint') ?></p>
        <?= $error('rang') ?>
      </div>
      <div class="field field--narrow<?= $fieldClass('betrag') ?>">
        <label for="f-betrag"><?= $t('field.betrag') ?> <?= $req ?></label>
        <?php // type="text" statt "number": erlaubt die Schweizer Schreibweise 1'000; geprüft wird auf dem Server (Amount). ?>
        <input type="text"<?= $aria('betrag', 'hint-betrag') ?> value="<?= $val('betrag') ?>" autocomplete="off" inputmode="numeric" spellcheck="false" required>
        <p class="hint" id="hint-betrag"><?= $t('field.betrag.hint') ?></p>
        <?= $error('betrag') ?>
      </div>
    </section>

    <section class="block" aria-labelledby="h-bank">
      <h2 id="h-bank"><?= $t('section.bank') ?></h2>
      <div class="field<?= $fieldClass('glaeubigerin') ?>">
        <label for="f-glaeubigerin"><?= $t('field.glaeubigerin') ?> <?= $req ?></label>
        <input type="text"<?= $aria('glaeubigerin') ?> value="<?= $val('glaeubigerin') ?>" autocomplete="organization" required>
        <?= $error('glaeubigerin') ?>
      </div>
      <div class="field<?= $fieldClass('bank') ?>">
        <label for="f-bank"><?= $t('field.bank') ?> <?= $req ?></label>
        <input type="text"<?= $aria('bank') ?> value="<?= $val('bank') ?>" autocomplete="off" required>
        <?= $error('bank') ?>
      </div>
    </section>

    <section class="block" aria-labelledby="h-contact">
      <h2 id="h-contact"><?= $t('section.contact') ?></h2>
      <div class="row row--2">
        <div class="field<?= $fieldClass('vorname') ?>">
          <label for="f-vorname"><?= $t('field.vorname') ?> <?= $req ?></label>
          <input type="text"<?= $aria('vorname') ?> value="<?= $val('vorname') ?>" autocomplete="given-name" required>
          <?= $error('vorname') ?>
        </div>
        <div class="field<?= $fieldClass('nachname') ?>">
          <label for="f-nachname"><?= $t('field.nachname') ?> <?= $req ?></label>
          <input type="text"<?= $aria('nachname') ?> value="<?= $val('nachname') ?>" autocomplete="family-name" required>
          <?= $error('nachname') ?>
        </div>
      </div>

      <div class="field<?= $fieldClass('email') ?>">
        <label for="f-email"><?= $t('field.email') ?> <?= $req ?></label>
        <input type="email"<?= $aria('email', 'hint-email') ?> value="<?= $val('email') ?>" autocomplete="email" spellcheck="false" required>
        <p class="hint" id="hint-email"><?= $t('field.email.hint') ?></p>
        <?= $error('email') ?>
      </div>

      <fieldset class="field group<?= $fieldClass('vorwahl', 'telefon') ?>">
        <legend><?= $t('field.telefon') ?> <?= $req ?></legend>
        <div class="phone">
          <div>
            <label class="sublabel" for="f-vorwahl"><?= $t('field.vorwahl') ?></label>
            <select<?= $aria('vorwahl') ?> autocomplete="tel-country-code">
              <?php foreach (Options::DIAL_CODES as $code => $label): ?>
              <option value="<?= $e($code) ?>"<?= $vorwahl === $code ? ' selected' : '' ?>><?= $e($label) ?></option>
              <?php endforeach ?>
              <option value="<?= Options::DIAL_OTHER ?>"<?= $vorwahl === Options::DIAL_OTHER ? ' selected' : '' ?>><?= $t('field.vorwahl.other') ?></option>
            </select>
          </div>
          <div>
            <label class="sublabel" for="f-telefon"><?= $t('field.telefon.number') ?></label>
            <input type="tel"<?= $aria('telefon', 'hint-telefon') ?> value="<?= $val('telefon') ?>" autocomplete="tel-national" inputmode="tel" required>
          </div>
        </div>
        <p class="hint" id="hint-telefon"><?= $t('field.telefon.hint') ?></p>
        <?= $error('vorwahl') ?><?= $error('telefon') ?>
      </fieldset>
    </section>

    <section class="block" aria-labelledby="h-fees">
      <div class="info">
        <h2 class="info__title" id="h-fees"><?= $t('fees.heading') ?></h2>
        <p><?= $t('fees.text') ?></p>
      </div>

      <div class="field check<?= $fieldClass('datenschutz') ?>">
        <input type="checkbox" value="1"<?= $aria('datenschutz') ?><?= ($values['datenschutz'] ?? '') === '1' ? ' checked' : '' ?> required>
        <label for="f-datenschutz"><?= $th('field.datenschutz', ['link' => $privacyLink]) ?> <?= $req ?></label>
        <?= $error('datenschutz') ?>
      </div>

      <button type="submit" class="btn" data-submit data-busy-text="<?= $t('submit.busy') ?>"><?= $t('submit') ?></button>
    </section>
  </form>
</div>
