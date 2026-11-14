<?php

use PHPUnit\Framework\TestCase;

final class TranslationsTest extends TestCase
{
    public function test_all_five_languages_have_the_same_keys_and_nonempty_values(): void
    {
        $base = require dirname(__DIR__).'/lang/en/file.php';
        $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
            $result = [];
            foreach ($items as $key => $value) {
                if (is_array($value)) {
                    $result += $flatten($value, $prefix.$key.'.');
                } else {
                    $result[$prefix.$key] = $value;
                }
            }
            ksort($result);

            return $result;
        };
        $base = $flatten($base);
        foreach (['en', 'zh_CN', 'zh_TW', 'ja', 'ko'] as $locale) {
            $values = $flatten(require dirname(__DIR__).'/lang/'.$locale.'/file.php');
            self::assertSame(array_keys($base), array_keys($values), $locale);
            foreach ($values as $value) {
                self::assertIsString($value);
                self::assertNotSame('', trim($value));
            }
        }
    }

    public function test_management_messages_have_all_five_language_keys(): void
    {
        $base = array_keys(require dirname(__DIR__).'/lang/en/storage.php');
        sort($base);
        foreach (['en', 'zh_CN', 'zh_TW', 'ja', 'ko'] as $locale) {
            $messages = require dirname(__DIR__).'/lang/'.$locale.'/storage.php';
            $keys = array_keys($messages);
            sort($keys);
            self::assertSame($base, $keys, $locale);
            foreach ($messages as $message) {
                self::assertNotSame('', trim($message));
            }
        }
    }
}
