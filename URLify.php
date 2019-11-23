<?php

/**
 * A PHP port of URLify.js from the Django project + fallback via "Portable ASCII".
 *
 * - https://github.com/django/django/blob/master/django/contrib/admin/static/admin/js/urlify.js
 * - https://github.com/voku/portable-ascii
 *
 * Handles symbols from latin languages, Arabic, Azerbaijani, Bulgarian, Burmese, Croatian, Czech, Danish, Esperanto,
 * Estonian, Finnish, French, Switzerland (French), Austrian (French), Georgian, German, Switzerland (German),
 * Austrian (German), Greek, Hindi, Kazakh, Latvian, Lithuanian, Norwegian, Persian, Polish, Romanian, Russian, Swedish,
 * Serbian, Slovak, Turkish, Ukrainian and Vietnamese ... and many other via "ASCII::to_transliterate()".
 */
class URLify
{
    /**
     * The language-mapping array.
     *
     * ISO 639-1 codes: https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
     *
     * @var array
     */
    public static $maps = [];

    /**
     * List of words to remove from URLs.
     *
     * @var array
     */
    public static $remove_list = [];

    /**
     * An array of strings that will convert into the separator-char - used by "URLify::filter()".
     *
     * @var array
     */
    private static $arrayToSeparator = [];

    /**
     * Add new strings the will be replaced with the separator.
     *
     * @param array $array <p>An array of things that should replaced by the separator.</p>
     * @param bool  $merge <p>Keep the previous (default) array-to-separator array.</p>
     */
    public static function add_array_to_separator(array $array, bool $merge = true)
    {
        if ($merge === true) {
            self::$arrayToSeparator = \array_unique(
                \array_merge(
                    self::$arrayToSeparator,
                    $array
                )
            );
        } else {
            self::$arrayToSeparator = $array;
        }
    }

    /**
     * Add new characters to the list. `$map` should be a hash.
     *
     * @param array       $map
     * @param string|null $language
     */
    public static function add_chars(array $map, string $language = null)
    {
        $language_key = $language ?? \uniqid('urlify', true);

        if (isset(self::$maps[$language_key])) {
            self::$maps[$language_key] = \array_merge($map, self::$maps[$language_key]);
        } else {
            self::$maps[$language_key] = $map;
        }
    }

    /**
     * @return void
     */
    public static function reset_chars()
    {
        self::$maps = [];
    }

    /**
     * Transliterates characters to their ASCII equivalents.
     * $language specifies a priority for a specific language.
     * The latter is useful if languages have different rules for the same character.
     *
     * @param string $string                            <p>The input string.</p>
     * @param string $language                          <p>Your primary language.</p>
     * @param string $unknown                           <p>Character use if character unknown. (default is ?).</p>
     *
     * @return string
     */
    public static function downcode(
        string $string,
        string $language = 'en',
        string $unknown = ''
    ): string {
        $string = self::expandString($string, $language);

        foreach (self::$maps as $mapsInner) {
            foreach ($mapsInner as $orig => $replace) {
                $string = \str_replace($orig, $replace, $string);
            }
        }

        $langSpecific = \voku\helper\ASCII::charsArrayWithOneLanguage($language, true);
        if (!empty($langSpecific)) {
            $string = \str_replace(
                $langSpecific['orig'],
                $langSpecific['replace'],
                $string
            );
        }

        foreach (\voku\helper\ASCII::charsArrayWithMultiLanguageValues(true) as $replace => $orig) {
            $string = \str_replace($orig, $replace, $string);
        }

        return \voku\helper\ASCII::to_transliterate($string, $unknown, false);
    }

    /**
     * Convert a String to URL.
     *
     * e.g.: "Petty<br>theft" to "Petty-theft"
     *
     * @param string $string                            <p>The text you want to convert.</p>
     * @param int    $maxLength                         <p>Max. length of the output string, set to "0" (zero) to
     *                                                  disable it</p>
     * @param string $language                          <p>The language you want to convert to.</p>
     * @param bool   $fileName                          <p>
     *                                                  Keep the "." from the extension e.g.: "imaäe.jpg" =>
     *                                                  "image.jpg"
     *                                                  </p>
     * @param bool   $removeWords                       <p>
     *                                                  Remove some "words" from the string.<br />
     *                                                  Info: Set extra words via <strong>remove_words()</strong>.
     *                                                  </p>
     * @param bool   $strToLower                        <p>Use <strong>strtolower()</strong> at the end.</p>
     * @param bool|string $separator                         <p>Define a new separator for the words.</p>
     *
     * @return string
     */
    public static function filter(
        string $string,
        int $maxLength = 200,
        string $language = 'en',
        bool $fileName = false,
        bool $removeWords = false,
        bool $strToLower = true,
        $separator = '-'
    ): string {
        if ($string === '') {
            return '';
        }

        // fallback
        if ($language === '') {
            $language = 'en';
        }

        // separator-fallback
        if ($separator === false) {
            $separator = '_';
        }
        if ($separator === true || $separator === '') {
            $separator = '-';
        }

        // escaped separator
        $separatorEscaped = \preg_quote($separator, '/');

        // use defaults, if there are no values
        if (self::$arrayToSeparator === []) {
            self::reset_array_to_separator();
        }

        // remove apostrophes which are not used as quotes around a string
        $stringTmp = \preg_replace("/(\w)'(\w)/u", '${1}${2}', $string);
        if ($stringTmp !== null) {
            $string = (string) $stringTmp;
        }

        // replace with $separator
        // +
        // remove all other html-tags
        $string = \strip_tags(
            (string) \preg_replace(
                self::$arrayToSeparator,
                $separator,
                $string
            )
        );

        // use special language replacer
        $string = self::downcode($string, $language);

        // replace with $separator, again
        $string = (string) \preg_replace(
            self::$arrayToSeparator,
            $separator,
            $string
        );

        // remove all these words from the string before urlifying
        $removeWordsSearch = '//';
        if ($removeWords === true) {
            $removeList = self::get_remove_list($language);
            if ($removeList !== []) {
                $removeWordsSearch = '/\b(?:' . \implode('|', $removeList) . ')\b/ui';
            }
        }

        // keep the "." from e.g.: a file-extension?
        if ($fileName) {
            $removePatternAddOn = '.';
        } else {
            $removePatternAddOn = '';
        }

        $string = (string) \preg_replace(
            [
                '/[^' . $separatorEscaped . $removePatternAddOn . '\-a-zA-Z0-9\s]/u',
                // 1) remove un-needed chars
                '/[\s]+/u',
                // 2) convert spaces to $separator
                $removeWordsSearch,
                // 3) remove some extras words
                '/[' . ($separatorEscaped ?: ' ') . ']+/u',
                // 4) remove double $separator's
                '/[' . ($separatorEscaped ?: ' ') . ']+$/u',
                // 5) remove $separator at the end
            ],
            [
                '',
                $separator,
                '',
                $separator,
                '',
            ],
            $string
        );

        // "substr" only if "$length" is set
        if (
            $maxLength
            &&
            $maxLength > 0
            &&
            \strlen($string) > $maxLength
        ) {
            $string = (string) \substr(\trim($string, $separator), 0, $maxLength);
        }

        // convert to lowercase
        if ($strToLower === true) {
            $string = \strtolower($string);
        }

        // trim "$separator" from beginning and end of the string
        return \trim($string, $separator);
    }

    /**
     * Append words to the remove list. Accepts either single words or an array of words.
     *
     * @param string|string[] $words
     * @param string          $language
     * @param bool            $merge    <p>Keep the previous (default) remove-words array.</p>
     */
    public static function remove_words($words, string $language = 'en', bool $merge = true)
    {
        if (\is_array($words) === false) {
            $words = [$words];
        }

        /** @noinspection ForeachSourceInspection */
        foreach ($words as $removeWordKey => $removeWord) {
            $words[$removeWordKey] = \preg_quote($removeWord, '/');
        }

        if ($merge === true) {
            self::$remove_list[$language] = \array_unique(
                \array_merge(
                    self::get_remove_list($language),
                    $words
                )
            );
        } else {
            self::$remove_list[$language] = $words;
        }
    }

    /**
     * Reset the internal "self::$arrayToSeparator" to the default values.
     */
    public static function reset_array_to_separator()
    {
        self::$arrayToSeparator = [
            '/&quot;|&amp;|&lt;|&gt;|&ndash;|&mdash;/i',  // ", &, <, >, –, —
            '/⁻|-|—|_|"|`|´|\'/',
            "#/\r\n|\r|\n|<br.*/?>#isU",
        ];
    }

    /**
     * @param string $language
     *
     * @return string
     */
    private static function get_language_for_reset_remove_list(string $language): string
    {
        if ($language === '') {
            return '';
        }

        if (
            \strpos($language, '_') === false
            &&
            \strpos($language, '-') === false
        ) {
            $language = \strtolower($language);
        } else {
            $regex = '/(?<first>[a-z]{2}).*/i';
            $language = \strtolower((string) \preg_replace($regex, '$1', $language));
        }

        return $language;
    }

    /**
     * reset the word-remove-array
     *
     * @param string $language
     */
    public static function reset_remove_list(string $language = 'en')
    {
        if ($language === '') {
            return;
        }

        $language_orig = $language;
        $language = self::get_language_for_reset_remove_list($language);
        if ($language === '') {
            return;
        }

        $stopWords = new \voku\helper\StopWords();

        try {
            self::$remove_list[$language_orig] = $stopWords->getStopWordsFromLanguage($language);
        } catch (\voku\helper\StopWordsLanguageNotExists $e) {
            self::$remove_list[$language_orig] = [];
        }
    }

    /**
     * Alias of `URLify::downcode()`.
     *
     * @param string $string
     * @param string $language
     *
     * @return string
     */
    public static function transliterate(string $string, string $language = 'en'): string
    {
        return self::downcode($string, $language);
    }

    /**
     * Expands the given string replacing some special parts for words.
     * e.g. "lorem@ipsum.com" is replaced by "lorem at ipsum dot com".
     *
     * Most of these transformations have been inspired by the pelle/slugger
     * project, distributed under the Eclipse Public License.
     * Copyright 2012 Pelle Braendgaard
     *
     * @param string $string   The string to expand
     * @param string $language
     *
     * @return string The result of expanding the string
     */
    protected static function expandString(string $string, string $language = 'en'): string
    {
        $string = self::expandCurrencies($string, $language);

        return self::expandSymbols($string, $language);
    }

    /**
     * Expands the numeric currencies in euros, dollars, pounds
     * and yens that the given string may include.
     *
     * @param string $string
     * @param string $language
     *
     * @return string
     */
    private static function expandCurrencies(string $string, string $language = 'en'): string
    {
        if ($language === 'de') {
            return (string) \preg_replace(
                [
                    '/(?:\s|^)(\d+)(?: )*€(?:\s|$)/',
                    '/(?:\s|^)\$(?: )*(\d+)(?:\s|$)/',
                    '/(?:\s|^)£(?: )*(\d+)(?:\s|$)/',
                    '/(?:\s|^)¥(?: )*(\d+)(?:\s|$)/',
                    '/(?:\s|^)(\d+)[.|,](\d+)(?: )*€(?:\s|$)/',
                    '/(?:\s|^)\$(?: )*(\d+)[.|,](\d+)(?:\s|$)/',
                    '/(?:\s|^)£(?: )*(\d+)[.|,](\d+)(?:\s|$)/',
                ],
                [
                    ' \1 Euro ',
                    ' \1 Dollar ',
                    ' \1 Pound ',
                    ' \1 Yen ',
                    ' \1 Euro \2 Cent ',
                    ' \1 Dollar \2 Cent ',
                    ' \1 Pound \2 Pence ',
                ],
                $string
            );
        }

        return (string) \preg_replace(
            [
                '/(?:\s|^)1(?: )*€(?:\s|$)/',
                '/(?:\s|^)(\d+)(?: )*€(?:\s|$)/',
                '/(?:\s|^)\$(?: )*1(?:\s|$)/',
                '/(?:\s|^)\$(?: )*(\d+)(?:\s|$)/',
                '/(?:\s|^)£(?: )*1(?:\s|$)/',
                '/(?:\s|^)£(?: )*(\d+)(?:\s|$)/',
                '/(?:\s|^)¥(?: )*(\d+)(?:\s|$)/',
                '/(?:\s|^)1[.|,](\d+)(?: )*€(?:\s|$)/',
                '/(?:\s|^)(\d+)[.|,](\d+)(?: )*€(?:\s|$)/',
                '/(?:\s|^)1[.|,](\d+)(?: )*$(?:\s|$)/',
                '/(?:\s|^)\$(?: )*(\d+)[.|,](\d+)(?:\s|$)/',
                '/(?:\s|^)1[.|,](\d+)(?: )*£(?:\s|$)/',
                '/(?:\s|^)£(?: )*(\d+)[.|,](\d+)(?:\s|$)/',
            ],
            [
                ' 1 Euro ',
                ' \1 Euros ',
                ' 1 Dollar ',
                ' \1 Dollars ',
                ' 1 Pound ',
                ' \1 Pounds ',
                ' \1 Yen ',
                ' 1 Euros \1 Cents ',
                ' \1 Euros \2 Cents ',
                ' 1 Dollars \1 Cents ',
                ' \1 Dollars \2 Cents ',
                ' 1 Pounds \1 Pence ',
                ' \1 Pounds \2 Pence ',
            ],
            $string
        );
    }

    /**
     * Expands the special symbols that the given string may include, such as '@', '.', '#' and '%'.
     *
     * @param string $string
     * @param string $language
     *
     * @return string
     */
    private static function expandSymbols(string $string, string $language = 'en'): string
    {
        $maps = \voku\helper\ASCII::charsArray(true);

        return (string) \preg_replace(
            [
                '/\s*©\s*/',
                '/\s*®\s*/',
                '/\s*@\s*/',
                '/\s*&\s*/',
                '/\s*%\s*/',
                '/(\s*=\s*)/',
            ],
            [
                $maps['latin_symbols']['©'],
                $maps['latin_symbols']['®'],
                $maps['latin_symbols']['@'],
                $maps[$language]['&'] ?? '&',
                $maps[$language]['%'] ?? '%',
                $maps[$language]['='] ?? '=',
            ],
            $string
        );
    }

    /**
     * return the "self::$remove_list[$language]" array
     *
     * @param string $language
     *
     * @return array
     */
    private static function get_remove_list(string $language = 'en'): array
    {
        // check for language
        if ($language === '') {
            return [];
        }

        // set remove-array
        if (!isset(self::$remove_list[$language])) {
            self::reset_remove_list($language);
        }

        // check for array
        if (
            !isset(self::$remove_list[$language])
            ||
            empty(self::$remove_list[$language])
        ) {
            return [];
        }

        return self::$remove_list[$language];
    }
}
