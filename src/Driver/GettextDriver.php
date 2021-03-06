<?php

namespace Clusteramaryllis\Gettext\Driver;

use Closure;
use FileReader;
use gettext_reader;
use InvalidArgumentException;
use Clusteramaryllis\Gettext\Contracts\GettextDriver as GettextDriverContract;

class GettextDriver implements GettextDriverContract
{
    /**
     * Emulate non-native php-gettext or not.
     *
     * @var bool
     */
    protected $emulateGettext = false;

    /**
     * Force to use GettextDriver or not.
     *
     * @var bool
     */
    protected $rule = false;

    /**
     * Text domains.
     *
     * @var array
     */
    protected $textDomains = [];

    /**
     * Default domain.
     *
     * @var string
     */
    protected $defaultDomain = 'messages';

    /**
     * Current locale.
     *
     * @var string
     */
    protected $currentLocale = '';

    /**
     * LC categories.
     *
     * @var array
     */
    protected $lcCategories = [
        'LC_CTYPE',
        'LC_NUMERIC',
        'LC_TIME',
        'LC_COLLATE',
        'LC_MONETARY',
        'LC_MESSAGES',
        'LC_ALL',
    ];

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        defined('LC_MESSAGES') || define('LC_MESSAGES', 5);
    }

    /**
     * {@inheritDoc}
     */
    public function getLocalesList($locale)
    {
        $localeNames = [];
        $pattern = "/^(?P<lang>[a-z]{2,3})"
           ."(?:_(?P<country>[A-Z]{2}))?"
           ."(?:\.(?P<charset>[-A-Za-z0-9_]+))?"
           ."(?:@(?P<modifier>[-A-Za-z0-9_]+))?$/";

        if (is_string($locale) && $locale !== '') {
            if (preg_match($pattern, $locale, $matches)) {
                if (array_key_exists("modifier", $matches)) {
                    if (array_key_exists("country", $matches)) {
                        if (array_key_exists("charset", $matches)) {
                            $localeNames[] = $matches["lang"]."_".$matches["country"].".".$matches["charset"]."@".$matches["modifier"];
                        }

                        $localeNames[] = $matches["lang"]."_".$matches["country"]."@".$matches["modifier"];
                    }

                    if (array_key_exists("charset", $matches)) {
                        $localeNames[] = $matches["lang"].".".$matches["charset"]."@".$matches["modifier"];
                    }

                    $localeNames[] = $matches["lang"]."@".$matches["modifier"];
                }

                if (array_key_exists("country", $matches)) {
                    if (array_key_exists("charset", $matches)) {
                        $localeNames[] = $matches["lang"]."_".$matches["country"].".".$matches["charset"];
                    }

                    $localeNames[] = $matches["lang"]."_".$matches["country"];
                }

                if (array_key_exists("charset", $matches)) {
                    $localeNames[] = $matches["lang"].".".$matches["charset"];
                }

                $localeNames[] = $matches["lang"];
            }

            if (! in_array($locale, $localeNames)) {
                $localeNames[] = $locale;
            }
        }

        return $localeNames;
    }

    /**
     * {@inheritDoc}
     */
    public function getReader($domain = null, $category = LC_MESSAGES, $cache = true)
    {
        if (! is_string($domain) || $domain === '') {
            $domain = $this->defaultDomain;
        }

        if (! array_key_exists($domain, $this->textDomains)) {
            $this->textDomains[$domain] = [];
        }

        if (! array_key_exists('l10n', $this->textDomains[$domain])) {
            $locale = $this->setLocale(LC_MESSAGES, 0);
            $boundPath = array_key_exists('path', $this->textDomains[$domain]) ?
                $this->textDomains[$domain]['path'] : './';
            $subPath = $this->lcCategories[$category]."/{$domain}.mo";
            $localeNames = $this->getLocalesList($locale);
            $input = null;

            foreach ($localeNames as $locale) {
                $fullPath = $boundPath.$locale."/".$subPath;

                if (file_exists($fullPath)) {
                    $input = new FileReader($fullPath);

                    break;
                }
            }

            $this->textDomains[$domain]['l10n'] = new gettext_reader($input, $cache);
        }

        return $this->textDomains[$domain]['l10n'];
    }

    /**
     * {@inheritDoc}
     */
    public function getEmulateGettext()
    {
        return $this->emulateGettext;
    }

    /**
     * {@inheritDoc}
     */
    public function getCodeset($domain = null)
    {
        if (! is_string($domain) || $domain === '') {
            $domain = $this->defaultDomain;
        }

        if (array_key_exists('codeset', $this->textDomains[$domain])) {
            return $this->textDomains[$domain]['codeset'];
        }

        if (@ini_get('mbstring.internal_encoding')) {
            return @ini_get('mbstring.internal_encoding');
        }

        return 'UTF-8';
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultLocale($locale, $category = "LC_ALL")
    {
        if (! is_string($locale) || $locale === '') {
            if (@getenv('LANG')) {
                return @getenv('LANG');
            }

            if (@getenv('LANGUAGE')) {
                return @getenv('LANGUAGE');
            }

            if (@getenv($category)) {
                return @getenv($category);
            }
        }

        $locales = explode(';', $locale);

        foreach ($locales as $lc) {
            if (strpos($lc, "=") !== false) {
                $value = explode('=', $lc);

                return $value[1];
            }
        }

        return reset($locales);
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentLocale()
    {
        return $this->currentLocale;
    }

    /**
     * {@inheritDoc}
     */
    public function hasLocaleAndFunction($func = null)
    {
        if ($func && ! function_exists($func)) {
            return false;
        }

        return ! $this->emulateGettext;
    }

    /**
     * {@inheritDoc}
     */
    public function setLocale($category)
    {
        $argsCount = func_num_args();

        if ($argsCount < 2) {
            throw new InvalidArgumentException(
                __CLASS__."::setLocale() expects at least 2 parameters, {$argsCount} given"
            );
        }

        $strCategory = $this->checkCategory($category);
        $preLocales  = func_get_args();

        array_shift($preLocales);

        if (! is_array($preLocales[0]) && $preLocales[0] === 0) {
            if ($this->currentLocale === '') {
                return $this->setLocale($category, $this->currentLocale);
            }

            return $this->currentLocale;
        }

        if (is_array($preLocales[0])) {
            $locales = $preLocales[0];
        } else {
            $locales = $preLocales;
        }

        $result = setlocale($category, $locales);

        if (! $result || $this->rule) {
            @putenv("{$strCategory}={$locales[0]}");
            @putenv("LANG={$locales[0]}");
            @putenv("LANGUAGE={$locales[0]}");

            $this->currentLocale = $this->getDefaultLocale('', $strCategory);
            $this->emulateGettext = true;
        } else {
            $this->currentLocale = $this->getDefaultLocale($result, $strCategory);
            $this->emulateGettext = false;
        }

        if (array_key_exists($this->defaultDomain, $this->textDomains)) {
            unset($this->textDomains[$this->defaultDomain]['l10n']);
        }

        return $this->currentLocale;
    }

    /**
     * {@inheritDoc}
     */
    public function encode($text, $domain = null)
    {
        $targetEncoding = $this->getCodeset($domain);

        if (function_exists('mb_detect_encoding')) {
            $sourceEncoding = mb_detect_encoding($text);

            if ($sourceEncoding !== $targetEncoding) {
                $text = mb_convert_encoding($text, $targetEncoding, $sourceEncoding);
            }
        }

        return $text;
    }

    /**
     * {@inheritDoc}
     */
    public function bindTextDomain($domain, $path)
    {
        $path = rtrim($path, "/\\")."/";

        if (! array_key_exists($domain, $this->textDomains)) {
            $this->textDomains[$domain] = [];
        }

        return $this->textDomains[$domain]['path'] = $path;
    }

    /**
     * {@inheritDoc}
     */
    public function bindTextDomainCodeset($domain, $codeset)
    {
        if (! array_key_exists($domain, $this->textDomains)) {
            $this->textDomains[$domain] = [];
        }

        return $this->textDomains[$domain]['codeset'] = $codeset;
    }

    /**
     * {@inheritDoc}
     */
    public function textDomain($domain = null)
    {
        if (is_string($domain) && $domain !== '') {
            return $this->defaultDomain = $domain;
        }

        return $this->defaultDomain;
    }

    /**
     * {@inheritDoc}
     */
    public function getText($msgid)
    {
        $l10n = $this->getReader();

        return $this->encode($l10n->translate($msgid));
    }

    /**
     * {@inheritDoc}
     */
    public function nGetText($msgid1, $msgid2, $n)
    {
        $l10n = $this->getReader();

        return $this->encode($l10n->ngettext($msgid1, $msgid2, $n));
    }

    /**
     * {@inheritDoc}
     */
    public function dGetText($domain, $msgid)
    {
        $l10n = $this->getReader($domain);

        return $this->encode($l10n->translate($msgid), $domain);
    }

    /**
     * {@inheritDoc}
     */
    public function dNGetText($domain, $msgid1, $msgid2, $n)
    {
        $l10n = $this->getReader($domain);

        return $this->encode($l10n->ngettext($msgid1, $msgid2, $n), $domain);
    }

    /**
     * {@inheritDoc}
     */
    public function dCGetText($domain, $msgid, $category)
    {
        $l10n = $this->getReader($domain, $category);

        return $this->encode($l10n->translate($msgid), $domain);
    }

    /**
     * {@inheritDoc}
     */
    public function dCNGetText($domain, $msgid1, $msgid2, $n, $category)
    {
        $l10n = $this->getReader($domain, $category);

        return $this->encode($l10n->ngettext($msgid1, $msgid2, $n), $domain);
    }

    /**
     * {@inheritDoc}
     */
    public function pGetText($context, $msgid)
    {
        $l10n = $this->getReader();

        return $this->encode($l10n->pgettext($context, $msgid));
    }

    /**
     * {@inheritDoc}
     */
    public function dPGetText($domain, $context, $msgid)
    {
        $l10n = $this->getReader($domain);

        return $this->encode($l10n->pgettext($context, $msgid), $domain);
    }

    /**
     * {@inheritDoc}
     */
    public function dCPGetText($domain, $context, $msgid, $category)
    {
        $l10n = $this->getReader($domain, $category);

        return $this->encode($l10n->pgettext($context, $msgid), $domain);
    }

    /**
     * {@inheritDoc}
     */
    public function nPGetText($context, $msgid1, $msgid2, $n)
    {
        $l10n = $this->getReader();

        return $this->encode($l10n->npgettext($context, $msgid1, $msgid2, $n));
    }

    /**
     * {@inheritDoc}
     */
    public function dNPGetText($domain, $context, $msgid1, $msgid2, $n)
    {
        $l10n = $this->getReader($domain);

        return $this->encode($l10n->npgettext($context, $msgid1, $msgid2, $n), $domain);
    }

    /**
     * {@inheritDoc}
     */
    public function dCNPGetText($domain, $context, $msgid1, $msgid2, $n, $category)
    {
        $l10n = $this->getReader($domain, $category);

        return $this->encode($l10n->npgettext($context, $msgid1, $msgid2, $n), $domain);
    }

    /**
     * Enable emulator.
     *
     * @param  bool|\Closure $rule
     * @return $this
     */
    public function enableEmulator($rule)
    {
        $this->rule = ($rule instanceof Closure) ? $rule->__invoke() : $rule;
        // make sure to check again

        return $this;
    }

    /**
     * Determine the correct category.
     *
     * @param  int $category
     * @return string
     */
    protected function checkCategory($category)
    {
        if (in_array($category, array_keys($this->lcCategories))) {
            return $this->lcCategories[$category];
        }

        return 'LC_ALL';
    }
}
