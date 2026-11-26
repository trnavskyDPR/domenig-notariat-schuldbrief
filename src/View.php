<?php

declare(strict_types=1);

namespace Schuldbrief;

/**
 * Rendert PHP-Templates. In Templates stehen bereit:
 *   $t(key, params)   Text, HTML-escaped
 *   $th(key, params)  Text escaped, Parameter sind bereits sicheres HTML (z. B. Links)
 *   $e(string)        HTML-Escaping
 *   $asset(path)      Pfad unter assets/ mit Cache-Version
 *   $i18n, $config
 */
final class View
{
    public function __construct(
        private string $root,
        private I18n $i18n,
        private Config $config,
    ) {
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /** @param array<string, mixed> $vars */
    public function render(string $template, array $vars = []): string
    {
        $i18n = $this->i18n;
        $config = $this->config;
        $e = [self::class, 'e'];
        $t = static fn (string $key, array $params = []): string => self::e($i18n->t($key, $params));
        $th = static function (string $key, array $html = []) use ($i18n): string {
            $text = self::e($i18n->t($key));
            foreach ($html as $name => $value) {
                $text = str_replace('{' . $name . '}', (string) $value, $text);
            }

            return $text;
        };
        $root = $this->root;
        $asset = static function (string $path) use ($root): string {
            $file = $root . '/public/assets/' . $path;
            $version = is_file($file) ? (string) filemtime($file) : '0';

            return self::e('assets/' . $path . '?v=' . $version);
        };

        extract($vars, EXTR_SKIP);
        ob_start();
        require $this->root . '/templates/' . $template . '.php';

        return (string) ob_get_clean();
    }

    /** Seite mit Layout. @param array<string, mixed> $vars */
    public function page(string $template, array $vars = []): string
    {
        return $this->render('layout', $vars + ['content' => $this->render($template, $vars)]);
    }
}
